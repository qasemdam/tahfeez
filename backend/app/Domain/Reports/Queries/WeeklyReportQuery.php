<?php

namespace App\Domain\Reports\Queries;

use App\Domain\Attendance\Models\Attendance;
use App\Domain\Evaluation\Models\EvaluationProfileVersion;
use App\Domain\Evaluation\Models\WeeklyEvaluation;
use App\Domain\Evaluation\Support\Achievement;
use App\Domain\Evaluation\Support\RequiredPages;
use App\Domain\Evaluation\ValueObjects\WeekPeriod;
use App\Domain\Groups\Models\GroupMembership;
use App\Domain\Memorization\Models\Memorization;
use App\Domain\Recitation\Models\Recitation;
use App\Domain\Reports\DTOs\StudentWeeklyRow;
use App\Domain\Reports\DTOs\WeeklyReport;
use App\Domain\Reports\Support\AmountTotals;
use App\Domain\Reports\Support\GroupLookup;
use App\Domain\Reports\Support\StudentScope;
use App\Domain\Review\Models\Review;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds the weekly report with a fixed number of queries.
 *
 * The report is a read-only projection over the domain tables: it never runs
 * the evaluation engine, never writes a weekly evaluation and never reads the
 * current configuration to reinterpret a stored one. Every per-student value is
 * gathered in one grouped query and joined in memory, so the query count does
 * not grow with the number of students.
 */
final class WeeklyReportQuery
{
    public function execute(
        User $actor,
        Center $center,
        WeekPeriod $period,
        ?int $groupId = null,
        ?int $studentId = null,
    ): WeeklyReport {
        $memberships = StudentScope::memberships($actor, $center, $groupId, $studentId);

        if ($memberships->isEmpty()) {
            return new WeeklyReport($period, []);
        }

        $studentIds = $memberships->pluck('student_id')->all();

        $attendance = $this->attendanceByStudent($center, $studentIds, $period);
        $memorization = $this->memorizationByStudent($center, $studentIds, $period);
        $review = $this->reviewByStudent($center, $studentIds, $period);
        $recitation = $this->recitationByStudent($center, $studentIds, $period);
        $evaluations = $this->evaluationsByStudent($center, $studentIds, $period);
        $profileRequired = $this->requiredPagesByStudent($center, $studentIds, $period);

        // A stored evaluation froze the group and teacher that graded the week.
        // Those may be a group the student has since left, so they are resolved
        // separately instead of being replaced by the current membership.
        $groups = GroupLookup::resolve(
            $memberships,
            $evaluations->pluck('group_id')->filter()->all(),
        );

        $rows = $memberships->map(function (GroupMembership $membership) use (
            $attendance,
            $memorization,
            $review,
            $recitation,
            $evaluations,
            $profileRequired,
            $groups,
        ): StudentWeeklyRow {
            $id = (int) $membership->student_id;
            $evaluation = $evaluations->get($id);

            $group = $evaluation !== null
                ? ($groups->get((int) $evaluation->group_id) ?? $membership->group)
                : $membership->group;

            $pages = $memorization[$id] ?? ['pages' => '0.0000', 'sessions' => 0];
            $required = $evaluation !== null
                ? (string) $evaluation->required_pages
                : ($profileRequired[$id] ?? RequiredPages::DEFAULT);

            return new StudentWeeklyRow(
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
                    'achievement_percentage' => Achievement::percentage($pages['pages'], $required),
                    'sessions' => $pages['sessions'],
                ],
                review: $review[$id] ?? ['pages' => '0.0000', 'sessions' => 0],
                recitation: $recitation[$id] ?? [
                    'sessions' => 0,
                    'grades' => [],
                    'errors' => ['hifz' => 0, 'tajweed' => 0, 'reading' => 0],
                ],
                evaluation: $evaluation === null ? null : [
                    'id' => (int) $evaluation->id,
                    'weighted_score' => $evaluation->weighted_score === null
                        ? null
                        : (string) $evaluation->weighted_score,
                    'achievement_percentage' => (string) $evaluation->achievement_percentage,
                    'is_complete' => (bool) $evaluation->is_complete,
                    'status' => (string) $evaluation->status,
                ],
            );
        })->all();

        return new WeeklyReport($period, $rows);
    }

    /**
     * @param  array<int, int>  $studentIds
     * @return array<int, array{present: int, late: int, excused_absence: int, unexcused_absence: int}>
     */
    private function attendanceByStudent(Center $center, array $studentIds, WeekPeriod $period): array
    {
        $rows = Attendance::query()
            ->where('center_id', $center->id)
            ->whereIn('student_id', $studentIds)
            ->whereBetween('attendance_date', [$period->start, $period->end])
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
     * Amounts are summed in PHP with decimal arithmetic (BR-EVL-02): a SQL
     * `SUM()` over a decimal column returns a float on SQLite and would drift.
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, array{pages: string, sessions: int}>
     */
    private function memorizationByStudent(Center $center, array $studentIds, WeekPeriod $period): array
    {
        return AmountTotals::byStudent(
            Memorization::query()
                ->where('center_id', $center->id)
                ->whereIn('student_id', $studentIds)
                ->whereBetween('memorization_date', [$period->start, $period->end])
                ->get(['student_id', 'amount_pages']),
        );
    }

    /**
     * @param  array<int, int>  $studentIds
     * @return array<int, array{pages: string, sessions: int}>
     */
    private function reviewByStudent(Center $center, array $studentIds, WeekPeriod $period): array
    {
        return AmountTotals::byStudent(
            Review::query()
                ->where('center_id', $center->id)
                ->whereIn('student_id', $studentIds)
                ->whereBetween('review_date', [$period->start, $period->end])
                ->get(['student_id', 'amount_pages']),
        );
    }

    /**
     * Recitation is reported as it is stored: sessions, the recorded grade
     * distribution and the error counters. No score is derived from a grade —
     * that mapping belongs to a rule set, not to a report.
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, array{sessions: int, grades: array<string, int>, errors: array{hifz: int, tajweed: int, reading: int}}>
     */
    private function recitationByStudent(Center $center, array $studentIds, WeekPeriod $period): array
    {
        $rows = Recitation::query()
            ->where('center_id', $center->id)
            ->whereIn('student_id', $studentIds)
            ->whereBetween('recitation_date', [$period->start, $period->end])
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
     * Stored evaluations only. The row is read as-is; the engine is not run and
     * no evaluation is created for a student who has none.
     *
     * @param  array<int, int>  $studentIds
     * @return Collection<int, WeeklyEvaluation>
     */
    private function evaluationsByStudent(Center $center, array $studentIds, WeekPeriod $period): Collection
    {
        return WeeklyEvaluation::query()
            ->where('center_id', $center->id)
            ->where('week_key', $period->key)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy(static fn (WeeklyEvaluation $evaluation): int => (int) $evaluation->student_id);
    }

    /**
     * The requirement in force at the week start, for students without a stored
     * evaluation. One query over the profile versions; the latest version
     * effective on or before the week start wins.
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, string>
     */
    private function requiredPagesByStudent(Center $center, array $studentIds, WeekPeriod $period): array
    {
        $rows = EvaluationProfileVersion::query()
            ->join(
                'evaluation_profiles',
                'evaluation_profiles.id',
                '=',
                'evaluation_profile_versions.profile_id',
            )
            ->where('evaluation_profiles.center_id', $center->id)
            ->whereIn('evaluation_profiles.student_id', $studentIds)
            ->where('evaluation_profile_versions.effective_from', '<=', $period->start)
            ->orderByDesc('evaluation_profile_versions.effective_from')
            ->orderByDesc('evaluation_profile_versions.version_number')
            ->get([
                'evaluation_profiles.student_id as student_id',
                'evaluation_profile_versions.required_pages as required_pages',
            ]);

        $result = [];

        foreach ($rows as $row) {
            $studentId = (int) $row->student_id;
            $result[$studentId] ??= (string) $row->required_pages;
        }

        return $result;
    }
}
