<?php

namespace Database\Factories;

use App\Domain\Groups\Models\Group;
use App\Domain\Review\Models\Review;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

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
            'review_date' => now()->toDateString(),
            'surah_id' => 1,
            'from_ayah_id' => 1,
            'to_ayah_id' => 7,
            'from_ayah_number' => 1,
            'to_ayah_number' => 7,
            'from_page' => 1,
            'to_page' => 1,
            'amount_pages' => '1.0000',
            'amount_lines' => 8,
            'amount_source' => Review::SOURCE_MANUAL,
            'quran_dataset_version_id' => null,
            'quran_dataset_version' => null,
            'grade' => 'good',
            'hifz_errors' => 0,
            'tajweed_errors' => 0,
            'reading_errors' => 0,
            'notes' => null,
            'client_uuid' => null,
            'version' => 1,
        ];
    }
}
