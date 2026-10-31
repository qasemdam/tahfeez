<?php

namespace App\Domain\Evaluation\DTOs;

/**
 * Attendance counts by status (BR-ATT-01). The engine only uses them when the
 * rule set explicitly includes the attendance criterion (BR-ATT-04).
 */
final readonly class AttendanceInput
{
    public function __construct(
        public int $present = 0,
        public int $late = 0,
        public int $excusedAbsence = 0,
        public int $unexcusedAbsence = 0,
    ) {}

    public function total(): int
    {
        return $this->present + $this->late + $this->excusedAbsence + $this->unexcusedAbsence;
    }
}
