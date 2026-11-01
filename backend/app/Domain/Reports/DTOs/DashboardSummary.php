<?php

namespace App\Domain\Reports\DTOs;

use App\Domain\Reports\ValueObjects\MonthPeriod;

/**
 * Center KPIs for one month. A read-only projection: nothing is written, no
 * evaluation is created and the engine is never run.
 *
 * Every figure is scoped to the groups the actor can see, so a teacher never
 * sees a centre-wide total that would leak another teacher's groups.
 */
final readonly class DashboardSummary
{
    /**
     * @param  array{present: int, late: int, excused_absence: int, unexcused_absence: int}  $attendance
     * @param  array{pages: string, sessions: int}  $memorization
     * @param  array{pages: string, sessions: int}  $review
     * @param  array{count: int, approved: int, computed: int}  $weeklyEvaluations
     */
    public function __construct(
        public MonthPeriod $period,
        public int $studentsTotal,
        public int $studentsActive,
        public int $groupsTotal,
        public array $attendance,
        public array $memorization,
        public array $review,
        public int $recitationSessions,
        public array $weeklyEvaluations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'period' => [
                'month' => $this->period->key,
                'start_date' => $this->period->start,
                'end_date' => $this->period->end,
            ],
            'students' => [
                'total' => $this->studentsTotal,
                'active' => $this->studentsActive,
            ],
            'groups' => [
                'total' => $this->groupsTotal,
            ],
            'attendance' => $this->attendance,
            'memorization' => $this->memorization,
            'review' => $this->review,
            'recitation' => [
                'sessions' => $this->recitationSessions,
            ],
            'weekly_evaluations' => $this->weeklyEvaluations,
        ];
    }
}
