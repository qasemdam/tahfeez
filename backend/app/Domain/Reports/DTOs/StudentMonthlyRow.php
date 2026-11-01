<?php

namespace App\Domain\Reports\DTOs;

/**
 * One student's row inside a monthly report.
 *
 * `memorization.required_pages` is the sum of the requirements frozen in the
 * stored weekly evaluations of the month — never a guess from the current
 * profile and never `weeks × default`. When no evaluation is stored the sum is
 * zero and `achievement_percentage` is null: an undefined ratio is reported as
 * null rather than invented.
 */
final readonly class StudentMonthlyRow
{
    /**
     * @param  array{present: int, late: int, excused_absence: int, unexcused_absence: int}  $attendance
     * @param  array{required_pages: string, actual_pages: string, achievement_percentage: string|null, sessions: int}  $memorization
     * @param  array{pages: string, sessions: int}  $review
     * @param  array{sessions: int, grades: array<string, int>, errors: array{hifz: int, tajweed: int, reading: int}}  $recitation
     * @param  array{count: int, completed: int, approved: int, computed: int}  $weeklyEvaluations
     */
    public function __construct(
        public int $studentId,
        public string $studentName,
        public ?int $groupId,
        public ?string $groupName,
        public ?int $teacherId,
        public ?string $teacherName,
        public array $attendance,
        public array $memorization,
        public array $review,
        public array $recitation,
        public array $weeklyEvaluations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'student' => [
                'id' => $this->studentId,
                'name' => $this->studentName,
            ],
            'group' => $this->groupId === null ? null : [
                'id' => $this->groupId,
                'name' => $this->groupName,
            ],
            'teacher' => $this->teacherId === null ? null : [
                'id' => $this->teacherId,
                'name' => $this->teacherName,
            ],
            'attendance' => $this->attendance,
            'memorization' => $this->memorization,
            'review' => $this->review,
            'recitation' => $this->recitation,
            'weekly_evaluations' => $this->weeklyEvaluations,
        ];
    }
}
