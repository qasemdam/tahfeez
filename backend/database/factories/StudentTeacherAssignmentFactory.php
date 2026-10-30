<?php

namespace Database\Factories;

use App\Domain\Students\Models\Student;
use App\Domain\Students\Models\StudentTeacherAssignment;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentTeacherAssignment>
 */
class StudentTeacherAssignmentFactory extends Factory
{
    protected $model = StudentTeacherAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'center_id' => Center::factory(),
            'student_id' => Student::factory(),
            'teacher_id' => User::factory()->teacher(),
            'assigned_at' => now()->toDateString(),
            'unassigned_at' => null,
            'assigned_by' => null,
            'reason' => null,
        ];
    }
}
