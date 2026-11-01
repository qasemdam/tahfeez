<?php

namespace Tests\Feature\Api;

use App\Domain\Attendance\Models\Attendance;
use App\Domain\Groups\Actions\AddStudentToGroup;
use App\Domain\Groups\Models\Group;
use App\Domain\Memorization\Models\Memorization;
use App\Domain\Quran\Contracts\QuranDataProvider;
use App\Domain\Recitation\Models\Recitation;
use App\Domain\Review\Models\Review;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FailingQuranDataProvider;
use Tests\TestCase;

class RecitationApiTest extends TestCase
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

    public function test_a_guest_cannot_list_recitations(): void
    {
        $this->getJson('/api/v1/recitations')->assertUnauthorized();
    }

    public function test_a_manager_can_create_a_recitation(): void
    {
        $s = $this->scenario();

        $response = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/recitations', [
            'student_id' => $s['student']->id,
            'recitation_date' => $this->today($s['center']),
            'surah_id' => 1,
            'from_ayah' => 1,
            'to_ayah' => 7,
            'grade' => 'excellent',
            'hifz_errors' => 0,
            'tajweed_errors' => 1,
            'reading_errors' => 0,
            'notes' => 'تسميع متقن',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.group_id', $s['group']->id)
            ->assertJsonPath('data.teacher_id', $s['manager']->id)
            ->assertJsonPath('data.surah_name', 'الفاتحة')
            ->assertJsonPath('data.from_ayah', 1)
            ->assertJsonPath('data.to_ayah', 7)
            ->assertJsonPath('data.from_page', 1)
            ->assertJsonPath('data.to_page', 1)
            ->assertJsonPath('data.grade', 'excellent')
            ->assertJsonPath('data.hifz_errors', 0)
            ->assertJsonPath('data.tajweed_errors', 1)
            ->assertJsonPath('data.notes', 'تسميع متقن')
            ->assertJsonMissingPath('data.amount_pages')
            ->assertJsonMissingPath('data.amount_lines')
            ->assertJsonMissingPath('data.achievement_pct')
            ->assertJsonMissingPath('data.weighted_score');

        $this->assertDatabaseHas('recitations', [
            'center_id' => $s['center']->id,
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['manager']->id,
        ]);
    }

    public function test_a_teacher_can_create_a_recitation_for_their_group(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/recitations', [
            'student_id' => $s['student']->id,
            'recitation_date' => $this->today($s['center']),
            'surah_id' => 1,
            'from_ayah' => 1,
            'to_ayah' => 7,
        ])->assertCreated()->assertJsonPath('data.teacher_id', $s['teacher']->id);
    }

    public function test_a_teacher_cannot_create_outside_their_group(): void
    {
        $center = Center::factory()->create();
        $manager = User::factory()->manager()->for($center)->create();
        $teacherA = User::factory()->teacher()->for($center)->create();
        $teacherB = User::factory()->teacher()->for($center)->create();
        $groupB = Group::factory()->for($center)->create(['teacher_id' => $teacherB->id]);
        $student = Student::factory()->for($center)->create(['enrolled_at' => '2026-01-01']);

        app(AddStudentToGroup::class)->execute($groupB, $manager, $student->id);

        $this->withHeaders($this->auth($teacherA))->postJson('/api/v1/recitations', [
            'student_id' => $student->id,
            'recitation_date' => $this->today($center),
            'surah_id' => 1,
            'from_ayah' => 1,
            'to_ayah' => 7,
        ])->assertNotFound();
    }

    public function test_cross_center_recitation_access_returns_404(): void
    {
        $centerA = Center::factory()->create();
        $centerB = Center::factory()->create();
        $managerA = User::factory()->manager()->for($centerA)->create();
        $teacherB = User::factory()->teacher()->for($centerB)->create();
        $groupB = Group::factory()->for($centerB)->create(['teacher_id' => $teacherB->id]);
        $studentB = Student::factory()->for($centerB)->create(['enrolled_at' => '2026-01-01']);

        $record = Recitation::factory()->for($centerB)->create([
            'student_id' => $studentB->id,
            'group_id' => $groupB->id,
            'teacher_id' => $teacherB->id,
            'recitation_date' => $this->today($centerB),
        ]);

        $headers = $this->auth($managerA);

        $this->withHeaders($headers)->getJson('/api/v1/recitations/'.$record->id)->assertNotFound();
        $this->withHeaders($headers)->patchJson('/api/v1/recitations/'.$record->id, ['notes' => 'x'])->assertNotFound();
        $this->withHeaders($headers)->deleteJson('/api/v1/recitations/'.$record->id)->assertNotFound();
    }

    public function test_a_teacher_can_read_a_students_recitations(): void
    {
        $s = $this->scenario();

        Recitation::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'recitation_date' => $this->today($s['center']),
        ]);

        $this->withHeaders($this->auth($s['teacher']))
            ->getJson('/api/v1/recitations?student_id='.$s['student']->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student_id', $s['student']->id);
    }

    public function test_a_teacher_cannot_view_another_teachers_recitation(): void
    {
        $s = $this->scenario();
        $otherTeacher = User::factory()->teacher()->for($s['center'])->create();
        $otherGroup = Group::factory()->for($s['center'])->create(['teacher_id' => $otherTeacher->id]);
        $otherStudent = Student::factory()->for($s['center'])->create(['enrolled_at' => '2026-01-01']);

        $record = Recitation::factory()->for($s['center'])->create([
            'student_id' => $otherStudent->id,
            'group_id' => $otherGroup->id,
            'teacher_id' => $otherTeacher->id,
            'recitation_date' => $this->today($s['center']),
        ]);

        $this->withHeaders($this->auth($s['teacher']))
            ->getJson('/api/v1/recitations/'.$record->id)
            ->assertNotFound();
    }

    public function test_recitations_can_be_filtered_by_student_group_and_date(): void
    {
        $s = $this->scenario();
        $date = $this->today($s['center']);

        Recitation::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'recitation_date' => $date,
        ]);

        Recitation::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'recitation_date' => now($s['center']->timezone)->subDay()->toDateString(),
        ]);

        $this->withHeaders($this->auth($s['teacher']))
            ->getJson('/api/v1/recitations?student_id='.$s['student']->id.'&group_id='.$s['group']->id.'&date='.$date)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.recitation_date', $date);
    }

    public function test_the_group_and_author_are_historical(): void
    {
        $s = $this->scenario();

        $response = $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/recitations', [
            'student_id' => $s['student']->id,
            'recitation_date' => $this->today($s['center']),
            'surah_id' => 1,
            'from_ayah' => 1,
            'to_ayah' => 7,
        ]);

        $id = $response->json('data.id');

        $teacherB = User::factory()->teacher()->for($s['center'])->create();
        $groupB = Group::factory()->for($s['center'])->create(['teacher_id' => $teacherB->id]);
        app(AddStudentToGroup::class)->execute($groupB, $s['manager'], $s['student']->id);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/recitations/'.$id)
            ->assertOk()
            ->assertJsonPath('data.group_id', $s['group']->id)
            ->assertJsonPath('data.teacher_id', $s['teacher']->id);
    }

    public function test_multiple_recitations_per_day_are_allowed(): void
    {
        $s = $this->scenario();
        $payload = [
            'student_id' => $s['student']->id,
            'recitation_date' => $this->today($s['center']),
            'surah_id' => 1,
            'from_ayah' => 1,
            'to_ayah' => 7,
        ];

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/recitations', $payload)->assertCreated();
        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/recitations', $payload)->assertCreated();

        $this->assertSame(2, Recitation::query()->count());
    }

    public function test_client_uuid_is_idempotent(): void
    {
        $s = $this->scenario();
        $payload = [
            'student_id' => $s['student']->id,
            'recitation_date' => $this->today($s['center']),
            'surah_id' => 1,
            'from_ayah' => 1,
            'to_ayah' => 7,
            'client_uuid' => '33333333-3333-4333-8333-333333333333',
        ];

        $first = $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/recitations', $payload)
            ->assertCreated();

        $second = $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/recitations', $payload)
            ->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Recitation::query()->count());
    }

    public function test_an_unknown_surah_is_rejected(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/recitations', [
            'student_id' => $s['student']->id,
            'recitation_date' => $this->today($s['center']),
            'surah_id' => 999,
            'from_ayah' => 1,
            'to_ayah' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('surah_id');
    }

    public function test_an_unmapped_ayah_is_rejected(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/recitations', [
            'student_id' => $s['student']->id,
            'recitation_date' => $this->today($s['center']),
            'surah_id' => 2,
            'from_ayah' => 1,
            'to_ayah' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors('surah_id');
    }

    public function test_start_ayah_after_end_ayah_is_rejected(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/recitations', [
            'student_id' => $s['student']->id,
            'recitation_date' => $this->today($s['center']),
            'surah_id' => 2,
            'from_ayah' => 5,
            'to_ayah' => 3,
        ])->assertStatus(422)->assertJsonValidationErrors('from_ayah');
    }

    public function test_a_page_that_contradicts_the_ayah_range_is_rejected(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/recitations', [
            'student_id' => $s['student']->id,
            'recitation_date' => $this->today($s['center']),
            'surah_id' => 1,
            'from_ayah' => 1,
            'to_ayah' => 7,
            'from_page' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors('from_page');
    }

    public function test_a_page_range_out_of_order_is_rejected(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/recitations', [
            'student_id' => $s['student']->id,
            'recitation_date' => $this->today($s['center']),
            'surah_id' => 1,
            'from_ayah' => 1,
            'to_ayah' => 7,
            'from_page' => 2,
            'to_page' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('from_page');
    }

    public function test_an_invalid_grade_is_rejected(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/recitations', [
            'student_id' => $s['student']->id,
            'recitation_date' => $this->today($s['center']),
            'surah_id' => 1,
            'from_ayah' => 1,
            'to_ayah' => 7,
            'grade' => 'amazing',
        ])->assertStatus(422)->assertJsonValidationErrors('grade');
    }

    public function test_negative_error_counts_are_rejected(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/recitations', [
            'student_id' => $s['student']->id,
            'recitation_date' => $this->today($s['center']),
            'surah_id' => 1,
            'from_ayah' => 1,
            'to_ayah' => 7,
            'hifz_errors' => -1,
        ])->assertStatus(422)->assertJsonValidationErrors('hifz_errors');
    }

    public function test_calculated_provider_failure_is_clear(): void
    {
        $this->app->instance(QuranDataProvider::class, new FailingQuranDataProvider);

        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/recitations', [
            'student_id' => $s['student']->id,
            'recitation_date' => $this->today($s['center']),
            'surah_id' => 1,
            'from_ayah' => 1,
            'to_ayah' => 7,
        ])->assertStatus(422)->assertJsonValidationErrors('surah_id');
    }

    public function test_a_manager_can_update_a_recitation(): void
    {
        $s = $this->scenario();

        $record = Recitation::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'recitation_date' => $this->today($s['center']),
        ]);

        $this->withHeaders($this->auth($s['manager']))
            ->patchJson('/api/v1/recitations/'.$record->id, [
                'grade' => 'good',
                'notes' => 'تصحيح',
            ])
            ->assertOk()
            ->assertJsonPath('data.grade', 'good')
            ->assertJsonPath('data.notes', 'تصحيح');
    }

    public function test_a_teacher_can_update_todays_recitation_only(): void
    {
        $s = $this->scenario();

        $today = Recitation::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'recitation_date' => $this->today($s['center']),
        ]);

        $old = Recitation::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'recitation_date' => now($s['center']->timezone)->subDay()->toDateString(),
        ]);

        $headers = $this->auth($s['teacher']);

        $this->withHeaders($headers)
            ->patchJson('/api/v1/recitations/'.$today->id, ['grade' => 'very_good'])
            ->assertOk();

        $this->withHeaders($headers)
            ->patchJson('/api/v1/recitations/'.$old->id, ['grade' => 'good'])
            ->assertForbidden();
    }

    public function test_a_manager_can_soft_delete_a_recitation(): void
    {
        $s = $this->scenario();

        $record = Recitation::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'recitation_date' => $this->today($s['center']),
        ]);

        $this->withHeaders($this->auth($s['manager']))
            ->deleteJson('/api/v1/recitations/'.$record->id)
            ->assertNoContent();

        $this->assertSoftDeleted('recitations', ['id' => $record->id]);
    }

    public function test_a_teacher_cannot_delete_a_recitation(): void
    {
        $s = $this->scenario();

        $record = Recitation::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'recitation_date' => $this->today($s['center']),
        ]);

        $this->withHeaders($this->auth($s['teacher']))
            ->deleteJson('/api/v1/recitations/'.$record->id)
            ->assertForbidden();
    }

    public function test_a_recitation_does_not_create_memorization_review_or_attendance(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/recitations', [
            'student_id' => $s['student']->id,
            'recitation_date' => $this->today($s['center']),
            'surah_id' => 1,
            'from_ayah' => 1,
            'to_ayah' => 7,
        ])->assertCreated();

        $this->assertSame(0, Memorization::query()->count());
        $this->assertSame(0, Review::query()->count());
        $this->assertSame(0, Attendance::query()->count());
    }
}
