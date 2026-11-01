<?php

namespace App\Domain\Reports\Queries;

use App\Domain\Attendance\Models\Attendance;
use App\Domain\Evaluation\Models\WeeklyEvaluation;
use App\Domain\Groups\Models\Group;
use App\Domain\Memorization\Models\Memorization;
use App\Domain\Recitation\Models\Recitation;
use App\Domain\Reports\DTOs\DashboardSummary;
use App\Domain\Reports\Support\AmountTotals;
use App\Domain\Reports\Support\StudentScope;
use App\Domain\Reports\ValueObjects\MonthPeriod;
use App\Domain\Review\Models\Review;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Centre KPIs for one month, in a fixed number of queries.
 *
 * Read-only: no writes, no evaluation engine, no snapshots. Each source is
 * aggregated by its own query and merged in memory, so no join can multiply
 * the figures (attendance × memorization × review).
 */
final class DashboardQuery
{
    public function execute(
        User $actor,
        Center $center,
        MonthPeriod $month,
        ?int $groupId = null,
    ): DashboardSummary {
        $groupsTotal = Group::query()
            ->visibleTo($actor)
            ->where('groups.center_id', $center->id)
            ->when($groupId !== null, static fn ($query) => $query->whereKey($groupId))
            ->count();

        $studentIds = StudentScope::studentIds($actor, $center, $groupId);

        if ($studentIds->isEmpty()) {
            return new DashboardSummary(
                period: $month,
                studentsTotal: 0,
                studentsActive: 0,
                groupsTotal: $groupsTotal,
                attendance: $this->emptyAttendance(),
                memorization: ['pages' => '0.0000', 'sessions' => 0],
                review: ['pages' => '0.0000', 'sessions' => 0],
                recitationSessions: 0,
                weeklyEvaluations: ['count' => 0, 'approved' => 0, 'computed' => 0],
            );
        }

        $statuses = Student::query()
            ->whereIn('id', $studentIds->all())
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $studentsTotal = 0;

        foreach ($statuses as $count) {
            $studentsTotal += (int) $count;
        }

        $memorization = AmountTotals::total(
            Memorization::query()
                ->where('center_id', $center->id)
                ->whereIn('student_id', $studentIds->all())
                ->whereBetween('memorization_date', [$month->start, $month->end])
                ->get(['amount_pages']),
        );

        $review = AmountTotals::total(
            Review::query()
                ->where('center_id', $center->id)
                ->whereIn('student_id', $studentIds->all())
                ->whereBetween('review_date', [$month->start, $month->end])
                ->get(['amount_pages']),
        );

        $recitationSessions = Recitation::query()
            ->where('center_id', $center->id)
            ->whereIn('student_id', $studentIds->all())
            ->whereBetween('recitation_date', [$month->start, $month->end])
            ->count();

        return new DashboardSummary(
            period: $month,
            studentsTotal: $studentsTotal,
            studentsActive: (int) ($statuses[Student::STATUS_ACTIVE] ?? 0),
            groupsTotal: $groupsTotal,
            attendance: $this->attendance($center, $studentIds, $month),
            memorization: $memorization,
            review: $review,
            recitationSessions: $recitationSessions,
            weeklyEvaluations: $this->weeklyEvaluations($center, $studentIds, $month),
        );
    }

    /**
     * @param  Collection<int, int>  $studentIds
     * @return array{present: int, late: int, excused_absence: int, unexcused_absence: int}
     */
    private function attendance(Center $center, Collection $studentIds, MonthPeriod $month): array
    {
        $rows = Attendance::query()
            ->where('center_id', $center->id)
            ->whereIn('student_id', $studentIds->all())
            ->whereBetween('attendance_date', [$month->start, $month->end])
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $result = $this->emptyAttendance();

        foreach ($rows as $status => $count) {
            if (array_key_exists((string) $status, $result)) {
                $result[(string) $status] = (int) $count;
            }
        }

        return $result;
    }

    /**
     * Stored evaluations only: counted, never created or promoted.
     *
     * @param  Collection<int, int>  $studentIds
     * @return array{count: int, approved: int, computed: int}
     */
    private function weeklyEvaluations(Center $center, Collection $studentIds, MonthPeriod $month): array
    {
        $row = WeeklyEvaluation::query()
            ->where('center_id', $center->id)
            ->whereIn('student_id', $studentIds->all())
            ->whereBetween('week_start_date', [$month->start, $month->end])
            ->selectRaw(
                'COUNT(*) as total,'
                .' COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as approved,'
                .' COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as computed',
                [WeeklyEvaluation::STATUS_APPROVED, WeeklyEvaluation::STATUS_COMPUTED],
            )
            ->first();

        return [
            'count' => (int) ($row->total ?? 0),
            'approved' => (int) ($row->approved ?? 0),
            'computed' => (int) ($row->computed ?? 0),
        ];
    }

    /**
     * @return array{present: int, late: int, excused_absence: int, unexcused_absence: int}
     */
    private function emptyAttendance(): array
    {
        return [
            'present' => 0,
            'late' => 0,
            'excused_absence' => 0,
            'unexcused_absence' => 0,
        ];
    }
}
