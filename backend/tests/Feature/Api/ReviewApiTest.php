<?php

namespace Tests\Feature\Api;

use App\Domain\Attendance\Models\Attendance;
use App\Domain\Groups\Actions\AddStudentToGroup;
use App\Domain\Groups\Models\Group;
use App\Domain\Memorization\Models\Memorization;
use App\Domain\Quran\Contracts\QuranDataProvider;
use App\Domain\Review\Models\Review;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FailingQuranDataProvider;
use Tests\TestCase;

class ReviewApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function auth(User $user): array
    {
        [, $plain] = $user->issueToken('phpunit');

        return ['Authorization' => 'Bearer '.$plain];
    }

    /**
     * @return array{center: Center, manager: User, teacher: User, group: Group, student: Student}
     */
    private function scenario(string $enrolledAt = '2026-01-01'): array
    {
        $center = Center::factory()->create();
        $manager = User::factory()->manager()->for($center)->create();
        $teacher = User::factory()->teacher()->for($center)->create();
        $group = Group::factory()->for($center)->create(['teacher_id' => $teacher->id]);
        $student = Student::factory()->for($center)->create(['enrolled_at' => $enrolledAt]);

        app(AddStudentToGroup::class)->execute($group, $manager, $student->id);

        return compact('center', 'manager', 'teacher', 'group', 'student');
    }

    private function today(Center $center): string
    {
        return now($center->timezone)->toDateString();
    }

    public function test_a_guest_cannot_list_reviews(): void
    {
        $this->getJson('/api/v1/reviews')->assertUnauthorized();
    }

    public function test_a_manager_can_create_a_manual_review(): void
    {
        $s = $this->scenario();

        $response = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/reviews', [
            'student_id' => $s['student']->id,
            'review_date' => $this->today($s['center']),
            'amount_source' => Review::SOURCE_MANUAL,
            'amount_pages' => '1.5',
            'grade' => 'excellent',
            'notes' => 'مراجعة متقنة',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.amount_pages', '1.5000')
            ->assertJsonPath('data.amount_source', 'MANUAL')
            ->assertJsonPath('data.group_id', $s['group']->id)
            ->assertJsonPath('data.teacher_id', $s['manager']->id)
            ->assertJsonPath('data.grade', 'excellent')
            ->assertJsonPath('data.notes', 'مراجعة متقنة')
            ->assertJsonMissingPath('data.achievement_pct')
            ->assertJsonMissingPath('data.weighted_score');

        $this->assertDatabaseHas('reviews', [
            'center_id' => $s['center']->id,
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['manager']->id,
            'amount_source' => 'MANUAL',
        ]);
    }

    public function test_a_teacher_can_create_a_review_for_their_group(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/reviews', [
            'student_id' => $s['student']->id,
            'review_date' => $this->today($s['center']),
            'amount_source' => Review::SOURCE_MANUAL,
            'amount_pages' => '0.5',
        ])->assertCreated()->assertJsonPath('data.teacher_id', $s['teacher']->id);
    }

    public function test_a_teacher_cannot_create_a_review_outside_their_group(): void
    {
        $center = Center::factory()->create();
        $manager = User::factory()->manager()->for($center)->create();
        $teacherA = User::factory()->teacher()->for($center)->create();
        $teacherB = User::factory()->teacher()->for($center)->create();
        $groupB = Group::factory()->for($center)->create(['teacher_id' => $teacherB->id]);
        $student = Student::factory()->for($center)->create(['enrolled_at' => '2026-01-01']);

        app(AddStudentToGroup::class)->execute($groupB, $manager, $student->id);

        $this->withHeaders($this->auth($teacherA))->postJson('/api/v1/reviews', [
            'student_id' => $student->id,
            'review_date' => $this->today($center),
            'amount_source' => Review::SOURCE_MANUAL,
            'amount_pages' => '1',
        ])->assertNotFound();
    }

    public function test_a_teacher_can_read_a_students_reviews(): void
    {
        $s = $this->scenario();

        Review::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'review_date' => $this->today($s['center']),
        ]);

        $this->withHeaders($this->auth($s['teacher']))
            ->getJson('/api/v1/reviews?student_id='.$s['student']->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student_id', $s['student']->id);
    }

    public function test_a_teacher_sees_only_their_groups_reviews(): void
    {
        $s = $this->scenario();
        $otherTeacher = User::factory()->teacher()->for($s['center'])->create();
        $otherGroup = Group::factory()->for($s['center'])->create(['teacher_id' => $otherTeacher->id]);
        $otherStudent = Student::factory()->for($s['center'])->create(['enrolled_at' => '2026-01-01']);

        Review::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'review_date' => $this->today($s['center']),
        ]);

        Review::factory()->for($s['center'])->create([
            'student_id' => $otherStudent->id,
            'group_id' => $otherGroup->id,
            'teacher_id' => $otherTeacher->id,
            'review_date' => $this->today($s['center']),
        ]);

        $this->withHeaders($this->auth($s['teacher']))
            ->getJson('/api/v1/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.group_id', $s['group']->id);
    }

    public function test_a_teacher_cannot_view_another_teachers_review(): void
    {
        $s = $this->scenario();
        $otherTeacher = User::factory()->teacher()->for($s['center'])->create();
        $otherGroup = Group::factory()->for($s['center'])->create(['teacher_id' => $otherTeacher->id]);
        $otherStudent = Student::factory()->for($s['center'])->create(['enrolled_at' => '2026-01-01']);

        $record = Review::factory()->for($s['center'])->create([
            'student_id' => $otherStudent->id,
            'group_id' => $otherGroup->id,
            'teacher_id' => $otherTeacher->id,
            'review_date' => $this->today($s['center']),
        ]);

        $this->withHeaders($this->auth($s['teacher']))
            ->getJson('/api/v1/reviews/'.$record->id)
            ->assertNotFound();
    }

    public function test_cross_center_review_access_returns_404(): void
    {
        $centerA = Center::factory()->create();
        $centerB = Center::factory()->create();
        $managerA = User::factory()->manager()->for($centerA)->create();
        $teacherB = User::factory()->teacher()->for($centerB)->create();
        $groupB = Group::factory()->for($centerB)->create(['teacher_id' => $teacherB->id]);
        $studentB = Student::factory()->for($centerB)->create(['enrolled_at' => '2026-01-01']);

        $record = Review::factory()->for($centerB)->create([
            'student_id' => $studentB->id,
            'group_id' => $groupB->id,
            'teacher_id' => $teacherB->id,
            'review_date' => $this->today($centerB),
        ]);

        $headers = $this->auth($managerA);

        $this->withHeaders($headers)->getJson('/api/v1/reviews/'.$record->id)->assertNotFound();
        $this->withHeaders($headers)->patchJson('/api/v1/reviews/'.$record->id, ['notes' => 'x'])->assertNotFound();
        $this->withHeaders($headers)->deleteJson('/api/v1/reviews/'.$record->id)->assertNotFound();
    }

    public function test_date_group_and_teacher_are_stored(): void
    {
        $s = $this->scenario();
        $date = $this->today($s['center']);

        $response = $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/reviews', [
            'student_id' => $s['student']->id,
            'review_date' => $date,
            'amount_source' => Review::SOURCE_MANUAL,
            'amount_pages' => '1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.review_date', $date)
            ->assertJsonPath('data.group_id', $s['group']->id)
            ->assertJsonPath('data.teacher_id', $s['teacher']->id);
    }

    public function test_client_uuid_is_idempotent(): void
    {
        $s = $this->scenario();
        $payload = [
            'student_id' => $s['student']->id,
            'review_date' => $this->today($s['center']),
            'amount_source' => Review::SOURCE_MANUAL,
            'amount_pages' => '1',
            'client_uuid' => '22222222-2222-4222-8222-222222222222',
        ];

        $first = $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/reviews', $payload)
            ->assertCreated();

        $second = $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/reviews', $payload)
            ->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Review::query()->count());
    }

    public function test_multiple_reviews_per_day_are_allowed(): void
    {
        $s = $this->scenario();
        $payload = [
            'student_id' => $s['student']->id,
            'review_date' => $this->today($s['center']),
            'amount_source' => Review::SOURCE_MANUAL,
            'amount_pages' => '0.5',
        ];

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/reviews', $payload)->assertCreated();
        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/reviews', $payload)->assertCreated();

        $this->assertSame(2, Review::query()->count());
    }

    public function test_a_calculated_review_uses_the_provider(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/reviews', [
            'student_id' => $s['student']->id,
            'review_date' => $this->today($s['center']),
            'amount_source' => Review::SOURCE_CALCULATED,
            'surah_id' => 1,
            'from_ayah' => 1,
            'to_ayah' => 7,
        ])->assertCreated()
            ->assertJsonPath('data.amount_source', 'CALCULATED')
            ->assertJsonPath('data.amount_pages', '1.0000')
            ->assertJsonPath('data.amount_lines', 8)
            ->assertJsonPath('data.from_page', 1)
            ->assertJsonPath('data.to_page', 1)
            ->assertJsonPath('data.surah_name', 'الفاتحة');

        $this->assertDatabaseHas('reviews', [
            'quran_dataset_version_id' => 1,
            'quran_dataset_version' => 'fixture-dev-v1',
        ]);
    }

    public function test_page_height_comes_from_data_not_a_constant(): void
    {
        $s = $this->scenario();

        $shortPage = $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/reviews', [
            'student_id' => $s['student']->id,
            'review_date' => $this->today($s['center']),
            'amount_source' => Review::SOURCE_CALCULATED,
            'surah_id' => 1,
            'from_ayah' => 1,
            'to_ayah' => 7,
        ])->assertCreated();

        $longPage = $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/reviews', [
            'student_id' => $s['student']->id,
            'review_date' => $this->today($s['center']),
            'amount_source' => Review::SOURCE_CALCULATED,
            'surah_id' => 2,
            'from_ayah' => 1,
            'to_ayah' => 4,
        ])->assertCreated();

        // Both cover 8 lines; page 1 has 8 lines, page 2 has 15.
        $this->assertSame(8, $shortPage->json('data.amount_lines'));
        $this->assertSame(8, $longPage->json('data.amount_lines'));
        $this->assertSame('1.0000', $shortPage->json('data.amount_pages'));
        $this->assertSame('0.5333', $longPage->json('data.amount_pages'));
    }

    public function test_the_surah_name_degrades_to_null_when_the_dataset_disappears(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/reviews', [
            'student_id' => $s['student']->id,
            'review_date' => $this->today($s['center']),
            'amount_source' => Review::SOURCE_MANUAL,
            'amount_pages' => '0.5',
            'surah_id' => 2,
            'from_ayah' => 1,
            'to_ayah' => 5,
        ])->assertCreated()->assertJsonPath('data.surah_name', 'البقرة');

        $this->app->instance(QuranDataProvider::class, new FailingQuranDataProvider);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.surah_name', null)
            ->assertJsonPath('data.0.surah_id', 2);
    }

    public function test_a_manual_review_is_stored_when_the_dataset_is_unavailable(): void
    {
        $this->app->instance(QuranDataProvider::class, new FailingQuranDataProvider);

        $s = $this->scenario();

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/reviews', [
            'student_id' => $s['student']->id,
            'review_date' => $this->today($s['center']),
            'amount_source' => Review::SOURCE_MANUAL,
            'amount_pages' => '1.5',
        ])->assertCreated()
            ->assertJsonPath('data.surah_name', null)
            ->assertJsonPath('data.amount_pages', '1.5000');

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_calculated_fails_clearly_when_the_provider_cannot_measure(): void
    {
        $this->app->instance(QuranDataProvider::class, new FailingQuranDataProvider);

        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/reviews', [
            'student_id' => $s['student']->id,
            'review_date' => $this->today($s['center']),
            'amount_source' => Review::SOURCE_CALCULATED,
            'surah_id' => 1,
            'from_ayah' => 1,
            'to_ayah' => 7,
        ])->assertStatus(422)->assertJsonValidationErrors('surah_id');
    }

    public function test_start_ayah_after_end_ayah_is_rejected(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/reviews', [
            'student_id' => $s['student']->id,
            'review_date' => $this->today($s['center']),
            'amount_source' => Review::SOURCE_CALCULATED,
            'surah_id' => 2,
            'from_ayah' => 5,
            'to_ayah' => 3,
        ])->assertStatus(422)->assertJsonValidationErrors('from_ayah');
    }

    public function test_a_manager_can_update_a_review(): void
    {
        $s = $this->scenario();

        $record = Review::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'review_date' => $this->today($s['center']),
        ]);

        $this->withHeaders($this->auth($s['manager']))
            ->patchJson('/api/v1/reviews/'.$record->id, [
                'grade' => 'good',
                'notes' => 'تصحيح',
            ])
            ->assertOk()
            ->assertJsonPath('data.grade', 'good')
            ->assertJsonPath('data.notes', 'تصحيح');
    }

    public function test_a_teacher_can_update_todays_review_only(): void
    {
        $s = $this->scenario();

        $today = Review::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'review_date' => $this->today($s['center']),
        ]);

        $old = Review::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'review_date' => now($s['center']->timezone)->subDay()->toDateString(),
        ]);

        $headers = $this->auth($s['teacher']);

        $this->withHeaders($headers)
            ->patchJson('/api/v1/reviews/'.$today->id, ['grade' => 'very_good'])
            ->assertOk();

        $this->withHeaders($headers)
            ->patchJson('/api/v1/reviews/'.$old->id, ['grade' => 'good'])
            ->assertForbidden();
    }

    public function test_a_manager_can_soft_delete_a_review(): void
    {
        $s = $this->scenario();

        $record = Review::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'review_date' => $this->today($s['center']),
        ]);

        $this->withHeaders($this->auth($s['manager']))
            ->deleteJson('/api/v1/reviews/'.$record->id)
            ->assertNoContent();

        $this->assertSoftDeleted('reviews', ['id' => $record->id]);
    }

    public function test_a_teacher_cannot_delete_a_review(): void
    {
        $s = $this->scenario();

        $record = Review::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'review_date' => $this->today($s['center']),
        ]);

        $this->withHeaders($this->auth($s['teacher']))
            ->deleteJson('/api/v1/reviews/'.$record->id)
            ->assertForbidden();
    }

    public function test_a_calculated_review_cannot_be_edited_manually(): void
    {
        $s = $this->scenario();

        $record = Review::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'review_date' => $this->today($s['center']),
            'amount_source' => Review::SOURCE_CALCULATED,
            'amount_pages' => '1.0000',
            'amount_lines' => 8,
        ]);

        $this->withHeaders($this->auth($s['manager']))
            ->patchJson('/api/v1/reviews/'.$record->id, ['amount_pages' => '9.9'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount_pages');
    }

    public function test_a_manual_review_cannot_change_its_amount_source(): void
    {
        $s = $this->scenario();

        $record = Review::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'review_date' => $this->today($s['center']),
            'amount_source' => Review::SOURCE_MANUAL,
            'amount_pages' => '1.0000',
        ]);

        $this->withHeaders($this->auth($s['manager']))
            ->patchJson('/api/v1/reviews/'.$record->id, [
                'amount_source' => Review::SOURCE_CALCULATED,
                'amount_pages' => '2',
            ])
            ->assertOk()
            ->assertJsonPath('data.amount_source', 'MANUAL')
            ->assertJsonPath('data.amount_pages', '2.0000');
    }

    public function test_a_review_does_not_create_memorization_or_attendance(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/reviews', [
            'student_id' => $s['student']->id,
            'review_date' => $this->today($s['center']),
            'amount_source' => Review::SOURCE_MANUAL,
            'amount_pages' => '1',
        ])->assertCreated();

        $this->assertSame(0, Memorization::query()->count());
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_reviews_can_be_filtered_by_student_and_date(): void
    {
        $s = $this->scenario();
        $date = $this->today($s['center']);

        Review::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'review_date' => $date,
        ]);

        Review::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'review_date' => now($s['center']->timezone)->subDay()->toDateString(),
        ]);

        $this->withHeaders($this->auth($s['teacher']))
            ->getJson('/api/v1/reviews?student_id='.$s['student']->id.'&date='.$date)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.review_date', $date);
    }
}
