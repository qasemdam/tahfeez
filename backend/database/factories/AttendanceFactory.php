<?php

namespace Database\Factories;

use App\Domain\Attendance\Models\Attendance;
use App\Domain\Groups\Models\Group;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'center_id' => Center::factory(),
            'student_id' => Student::factory(),
            'group_id' => Group::factory(),
            'teacher_id' => User::factory()->teacher(),
            'attendance_date' => now()->toDateString(),
            'status' => Attendance::STATUS_PRESENT,
            'late_minutes' => null,
            'notes' => null,
            'client_uuid' => null,
            'version' => 1,
        ];
    }

    public function late(int $minutes = 10): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Attendance::STATUS_LATE,
            'late_minutes' => $minutes,
        ]);
    }
}
