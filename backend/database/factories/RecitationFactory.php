<?php

namespace Database\Factories;

use App\Domain\Groups\Models\Group;
use App\Domain\Recitation\Models\Recitation;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recitation>
 */
class RecitationFactory extends Factory
{
    protected $model = Recitation::class;

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
            'recitation_date' => now()->toDateString(),
            'surah_id' => 1,
            'from_ayah_id' => 1,
            'to_ayah_id' => 7,
            'from_ayah_number' => 1,
            'to_ayah_number' => 7,
            'from_page' => 1,
            'to_page' => 1,
            'grade' => 'good',
            'hifz_errors' => 0,
            'tajweed_errors' => 0,
            'reading_errors' => 0,
            'notes' => null,
            'quran_dataset_version_id' => 1,
            'quran_dataset_version' => 'fixture-dev-v1',
            'client_uuid' => null,
            'version' => 1,
        ];
    }
}
