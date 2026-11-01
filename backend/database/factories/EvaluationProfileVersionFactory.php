<?php

namespace Database\Factories;

use App\Domain\Evaluation\Models\EvaluationProfile;
use App\Domain\Evaluation\Models\EvaluationProfileVersion;
use App\Domain\Tenancy\Models\Center;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationProfileVersion>
 */
class EvaluationProfileVersionFactory extends Factory
{
    protected $model = EvaluationProfileVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'center_id' => Center::factory(),
            'profile_id' => EvaluationProfile::factory(),
            'version_number' => 1,
            'required_pages' => '1.00',
            'effective_from' => now()->toDateString(),
            'rule_set' => null,
            'created_by' => null,
            'reason' => null,
        ];
    }
}
