<?php

namespace Database\Factories;

use App\Domain\Tenancy\Models\Center;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Center>
 */
class CenterFactory extends Factory
{
    protected $model = Center::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'timezone' => 'Asia/Riyadh',
            'locale' => 'ar',
            'weekly_evaluation_start_day' => 'saturday',
            'weekly_evaluation_start_day_effective_from' => null,
            'settings' => null,
            'is_active' => true,
        ];
    }
}
