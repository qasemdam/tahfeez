<?php

namespace Database\Factories;

use App\Domain\Evaluation\Models\WeeklyEvaluation;
use App\Domain\Groups\Models\Group;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeeklyEvaluation>
 */
class WeeklyEvaluationFactory extends Factory
{
    protected $model = WeeklyEvaluation::class;

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
            'week_key' => '2026-10-24',
            'week_start_date' => '2026-10-24',
            'week_end_date' => '2026-10-30',
            'weekly_evaluation_start_day' => 'saturday',
            'evaluation_profile_version_id' => null,
            'evaluation_profile_version' => null,
            'template_version_id' => null,
            'required_pages' => '1.00',
            'actual_pages' => '1.0000',
            'achievement_percentage' => '100.00',
            'weighted_score' => null,
            'is_complete' => true,
            'status' => WeeklyEvaluation::STATUS_COMPUTED,
            'rules_snapshot' => [
                'week_key' => '2026-10-24',
                'week_start_date' => '2026-10-24',
                'week_end_date' => '2026-10-30',
            ],
            'result_snapshot' => [
                'achievement_percentage' => '100.00',
                'weighted_score' => null,
                'is_complete' => true,
                'components' => [],
            ],
            'client_uuid' => null,
            'version' => 1,
        ];
    }
}
