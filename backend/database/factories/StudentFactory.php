<?php

namespace Database\Factories;

use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'center_id' => Center::factory(),
            'code' => strtoupper(fake()->unique()->bothify('S-####')),
            'full_name' => fake()->name(),
            'birth_date' => null,
            'age_years' => fake()->numberBetween(6, 18),
            'grade_level' => (string) fake()->numberBetween(1, 12),
            'phone' => null,
            'guardian_name' => fake()->name(),
            'guardian_phone' => fake()->numerify('05#########'),
            'enrolled_at' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'status' => Student::STATUS_ACTIVE,
            'notes' => null,
            'client_uuid' => null,
            'version' => 1,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Student::STATUS_INACTIVE,
        ]);
    }
}
