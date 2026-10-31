<?php

namespace Database\Factories;

use App\Domain\Groups\Models\Group;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'center_id' => Center::factory(),
            'teacher_id' => User::factory()->teacher(),
            'name' => 'حلقة '.fake()->unique()->numerify('###'),
            'status' => Group::STATUS_ACTIVE,
            'notes' => null,
            'version' => 1,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Group::STATUS_INACTIVE,
        ]);
    }
}
