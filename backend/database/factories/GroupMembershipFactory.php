<?php

namespace Database\Factories;

use App\Domain\Groups\Models\Group;
use App\Domain\Groups\Models\GroupMembership;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupMembership>
 */
class GroupMembershipFactory extends Factory
{
    protected $model = GroupMembership::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'center_id' => Center::factory(),
            'group_id' => Group::factory(),
            'student_id' => Student::factory(),
            'joined_at' => now()->toDateString(),
            'left_at' => null,
            'assigned_by' => null,
            'reason' => null,
        ];
    }
}
