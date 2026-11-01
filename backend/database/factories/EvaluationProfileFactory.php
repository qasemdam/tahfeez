<?php

namespace Database\Factories;

use App\Domain\Evaluation\Models\EvaluationProfile;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationProfile>
 */
class EvaluationProfileFactory extends Factory
{
    protected $model = EvaluationProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'center_id' => Center::factory(),
            'student_id' => Student::factory(),
        ];
    }
}
