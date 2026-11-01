<?php

namespace App\Domain\Reports\DTOs;

use App\Domain\Evaluation\ValueObjects\WeekPeriod;

/**
 * A read-only weekly report: the resolved period plus one row per student.
 *
 * The period is always resolved by the server (center timezone + configured
 * start day); the client never derives boundaries.
 */
final readonly class WeeklyReport
{
    /**
     * @param  array<int, StudentWeeklyRow>  $students
     */
    public function __construct(
        public WeekPeriod $period,
        public array $students,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'week' => [
                'week_key' => $this->period->key,
                'start_date' => $this->period->start,
                'end_date' => $this->period->end,
                'start_day' => $this->period->startDay->value,
            ],
            'students' => array_map(
                static fn (StudentWeeklyRow $row): array => $row->toArray(),
                $this->students,
            ),
            'totals' => [
                'students' => count($this->students),
            ],
        ];
    }
}
