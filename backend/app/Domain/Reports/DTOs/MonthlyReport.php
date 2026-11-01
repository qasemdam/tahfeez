<?php

namespace App\Domain\Reports\DTOs;

use App\Domain\Reports\ValueObjects\MonthPeriod;

/**
 * A read-only monthly report: the calendar month plus one row per student.
 *
 * It is a projection, not a computation: nothing is written and the evaluation
 * engine is never run.
 */
final readonly class MonthlyReport
{
    /**
     * @param  array<int, StudentMonthlyRow>  $students
     */
    public function __construct(
        public MonthPeriod $month,
        public array $students,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'month' => [
                'month_key' => $this->month->key,
                'start_date' => $this->month->start,
                'end_date' => $this->month->end,
            ],
            'students' => array_map(
                static fn (StudentMonthlyRow $row): array => $row->toArray(),
                $this->students,
            ),
            'totals' => [
                'students' => count($this->students),
            ],
        ];
    }
}
