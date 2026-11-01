<?php

namespace App\Domain\Reports\DTOs;

/**
 * One student's row inside a weekly report.
 *
 * Every section is read-only aggregate data gathered from the domain tables:
 * nothing here is recomputed by the evaluation engine and nothing is written
 * back. `evaluation` is the **stored** snapshot when a weekly evaluation
 * exists for the week, and `null` when it does not — a report never creates one.
 */
final readonly class StudentWeeklyRow
{
    /**
     * @param  array{present: int, late: int, excused_absence: int, unexcused_absence: int}  $attendance
     * @param  array{required_pages: string, actual_pages: string, achievement_percentage: string, sessions: int}  $memorization
     * @param  array{pages: string, sessions: int}  $review
     * @param  array{sessions: int, grades: array<string, int>, errors: array{hifz: int, tajweed: int, reading: int}}  $recitation
     * @param  array{id: int, weighted_score: string|null, achievement_percentage: string, is_complete: bool, status: string}|null  $evaluation
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
        public ?array $evaluation,
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
            'evaluation' => $this->evaluation,
        ];
    }
}
