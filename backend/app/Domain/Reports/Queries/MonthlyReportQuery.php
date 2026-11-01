<?php

namespace App\Domain\Reports\Queries;

use App\Domain\Attendance\Models\Attendance;
use App\Domain\Evaluation\Models\WeeklyEvaluation;
use App\Domain\Evaluation\Support\Achievement;
use App\Domain\Groups\Models\GroupMembership;
use App\Domain\Memorization\Models\Memorization;
use App\Domain\Recitation\Models\Recitation;
use App\Domain\Reports\DTOs\MonthlyReport;
use App\Domain\Reports\DTOs\StudentMonthlyRow;
use App\Domain\Reports\Support\AmountTotals;
use App\Domain\Reports\Support\GroupLookup;
use App\Domain\Reports\Support\StudentScope;
use App\Domain\Reports\ValueObjects\MonthPeriod;
use App\Domain\Review\Models\Review;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

/**
 * Builds the monthly report with a fixed number of queries.
 *
 * Read-only: it aggregates the month's records and reads the stored weekly
 * evaluations. It never runs the evaluation engine, never creates an
 * evaluation and never rebuilds a historical requirement from the current
 * profile.
 */
final class MonthlyReportQuery
{
    public function execute(
        User $actor,
        Center $center,
        MonthPeriod $month,
        ?int $groupId = null,
        ?int $studentId = null,
    ): MonthlyReport {
        $memberships = StudentScope::memberships($actor, $center, $groupId, $studentId);

        if ($memberships->isEmpty()) {
            return new MonthlyReport($month, []);
        }

        $studentIds = $memberships->pluck('student_id')->all();

        $attendance = $this->attendanceByStudent($center, $studentIds, $month);
        $memorization = AmountTotals::byStudent(
            Memorization::query()
                ->where('center_id', $center->id)
                ->whereIn('student_id', $studentIds)
                ->whereBetween('memorization_date', [$month->start, $month->end])
                ->get(['student_id', 'amount_pages']),
        );
        $review = AmountTotals::byStudent(
            Review::query()
                ->where('center_id', $center->id)
                ->whereIn('student_id', $studentIds)
                ->whereBetween('review_date', [$month->start, $month->end])
                ->get(['student_id', 'amount_pages']),
        );
        $recitation = $this->recitationByStudent($center, $studentIds, $month);
        $evaluations = $this->evaluationsByStudent($center, $studentIds, $month);

        // A stored evaluation froze the group and teacher that graded its week.
        $groups = GroupLookup::resolve(
            $memberships,
            $evaluations->flatten()->pluck('group_id')->filter()->all(),
        );

        $rows = $memberships->map(function (GroupMembership $membership) use (
            $attendance,
            $memorization,
            $review,
            $recitation,
            $evaluations,
            $groups,
        ): StudentMonthlyRow {
            $id = (int) $membership->student_id;

            /** @var Collection<int, WeeklyEvaluation> $studentEvaluations */
            $studentEvaluations = $evaluations->get($id, collect());

            // The latest stored week carries the most recent historical context.
            $latest = $studentEvaluations
                ->sortBy(static fn (WeeklyEvaluation $evaluation): string => (string) $evaluation->week_start_date?->toDateString())
                ->last();

            $group = $latest !== null
                ? ($groups->get((int) $latest->group_id) ?? $membership->group)
                : $membership->group;

            $pages = $memorization[$id] ?? ['pages' => '0.0000', 'sessions' => 0];
            $required = $this->requiredPages($studentEvaluations);

            return new StudentMonthlyRow(
                studentId: $id,
                studentName: (string) ($membership->student?->full_name ?? ''),
                groupId: $group === null ? null : (int) $group->id,
                groupName: (string) ($group?->name ?? ''),
                teacherId: $group?->teacher_id,
                teacherName: (string) ($group?->teacher?->name ?? ''),
                attendance: $attendance[$id] ?? [
                    'present' => 0,
                    'late' => 0,
                    'excused_absence' => 0,
                    'unexcused_absence' => 0,
                ],
                memorization: [
                    'required_pages' => $required,
                    'actual_pages' => $pages['pages'],
                    // No stored requirement means the ratio is undefined. It is
                    // reported as null, never guessed from the current profile.
                    'achievement_percentage' => BigDecimal::of($required)->isZero()
                        ? null
                        : Achievement::percentage($pages['pages'], $required),
                    'sessions' => $pages['sessions'],
                ],
                review: $review[$id] ?? ['pages' => '0.0000', 'sessions' => 0],
                recitation: $recitation[$id] ?? [
                    'sessions' => 0,
                    'grades' => [],
                    'errors' => ['hifz' => 0, 'tajweed' => 0, 'reading' => 0],
                ],
                weeklyEvaluations: [
                    'count' => $studentEvaluations->count(),
                    'completed' => $studentEvaluations->where('is_complete', true)->count(),
                    'approved' => $studentEvaluations
                        ->where('status', WeeklyEvaluation::STATUS_APPROVED)
                        ->count(),
                    'computed' => $studentEvaluations
                        ->where('status', WeeklyEvaluation::STATUS_COMPUTED)
                        ->count(),
                ],
            );
        })->all();

        return new MonthlyReport($month, $rows);
    }

    /**
     * The month's requirement is the sum of the requirements frozen in the
     * stored evaluations. A week without an evaluation contributes nothing —
     * it is never replaced by `weeks × default` or by the current profile.
     *
     * @param  Collection<int, WeeklyEvaluation>  $evaluations
     */
    private function requiredPages(Collection $evaluations): string
    {
        $sum = BigDecimal::zero();

        foreach ($evaluations as $evaluation) {
            $sum = $sum->plus(BigDecimal::of((string) $evaluation->required_pages));
        }

        return (string) $sum->toScale(2);
    }

    /**
     * @param  array<int, int>  $studentIds
     * @return array<int, array{present: int, late: int, excused_absence: int, unexcused_absence: int}>
     */
    private function attendanceByStudent(Center $center, array $studentIds, MonthPeriod $month): array
    {
        $rows = Attendance::query()
            ->where('center_id', $center->id)
            ->whereIn('student_id', $studentIds)
            ->whereBetween('attendance_date', [$month->start, $month->end])
            ->selectRaw('student_id, status, COUNT(*) as aggregate')
            ->groupBy('student_id', 'status')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $studentId = (int) $row->student_id;
            $result[$studentId] ??= [
                'present' => 0,
                'late' => 0,
                'excused_absence' => 0,
                'unexcused_absence' => 0,
            ];

            $status = (string) $row->status;

            if (array_key_exists($status, $result[$studentId])) {
                $result[$studentId][$status] = (int) $row->aggregate;
            }
        }

        return $result;
    }

    /**
     * Recitation is reported as stored: sessions, the recorded grade
     * distribution and the error counters. No score is derived from a grade.
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, array{sessions: int, grades: array<string, int>, errors: array{hifz: int, tajweed: int, reading: int}}>
     */
    private function recitationByStudent(Center $center, array $studentIds, MonthPeriod $month): array
    {
        $rows = Recitation::query()
            ->where('center_id', $center->id)
            ->whereIn('student_id', $studentIds)
            ->whereBetween('recitation_date', [$month->start, $month->end])
            ->get(['student_id', 'grade', 'hifz_errors', 'tajweed_errors', 'reading_errors']);

        $result = [];

        foreach ($rows as $row) {
            $studentId = (int) $row->student_id;
            $result[$studentId] ??= [
                'sessions' => 0,
                'grades' => [],
                'errors' => ['hifz' => 0, 'tajweed' => 0, 'reading' => 0],
            ];

            $result[$studentId]['sessions']++;
            $result[$studentId]['errors']['hifz'] += (int) $row->hifz_errors;
            $result[$studentId]['errors']['tajweed'] += (int) $row->tajweed_errors;
            $result[$studentId]['errors']['reading'] += (int) $row->reading_errors;

            $grade = $row->grade;

            if (is_string($grade) && $grade !== '') {
                $result[$studentId]['grades'][$grade] = ($result[$studentId]['grades'][$grade] ?? 0) + 1;
            }
        }

        return $result;
    }

    /**
     * Stored evaluations whose week starts inside the month. One query for the
     * whole report, grouped in memory.
     *
     * @param  array<int, int>  $studentIds
     * @return Collection<int, Collection<int, WeeklyEvaluation>>
     */
    private function evaluationsByStudent(Center $center, array $studentIds, MonthPeriod $month): Collection
    {
        return WeeklyEvaluation::query()
            ->where('center_id', $center->id)
            ->whereIn('student_id', $studentIds)
            ->whereBetween('week_start_date', [$month->start, $month->end])
            ->orderBy('week_start_date')
            ->get()
            ->groupBy(static fn (WeeklyEvaluation $evaluation): int => (int) $evaluation->student_id);
    }
}
