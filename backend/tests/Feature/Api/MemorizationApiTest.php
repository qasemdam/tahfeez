<?php

namespace Tests\Feature\Api;

use App\Domain\Attendance\Models\Attendance;
use App\Domain\Groups\Actions\AddStudentToGroup;
use App\Domain\Groups\Models\Group;
use App\Domain\Memorization\Models\Memorization;
use App\Domain\Quran\Contracts\QuranDataProvider;
use App\Domain\Quran\Support\AyahLocation;
use App\Domain\Quran\Support\PageMeasurement;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FailingQuranDataProvider;
use Tests\TestCase;

class MemorizationApiTest extends TestCase
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

    public function test_a_guest_cannot_list_memorization(): void
    {
        $this->getJson('/api/v1/memorization')->assertUnauthorized();
    }

    public function test_a_guest_cannot_list_surahs(): void
    {
        $this->getJson('/api/v1/quran/surahs')->assertUnauthorized();
    }

    public function test_a_manager_can_record_manual_memorization(): void
    {
        $s = $this->scenario();

        $response = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/memorization', [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_MANUAL,
            'amount_pages' => '1.5',
            'grade' => 'excellent',
            'notes' => 'حفظ متقن',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.amount_pages', '1.5000')
            ->assertJsonPath('data.amount_source', 'MANUAL')
            ->assertJsonPath('data.group_id', $s['group']->id)
            ->assertJsonPath('data.teacher_id', $s['manager']->id)
            ->assertJsonPath('data.grade', 'excellent')
            ->assertJsonPath('data.notes', 'حفظ متقن')
            ->assertJsonMissingPath('data.achievement_pct')
            ->assertJsonMissingPath('data.weighted_score');

        $this->assertDatabaseHas('memorizations', [
            'center_id' => $s['center']->id,
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'amount_source' => 'MANUAL',
        ]);
    }

    public function test_a_teacher_can_record_for_their_group(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_MANUAL,
            'amount_pages' => '0.5',
        ])->assertCreated()->assertJsonPath('data.teacher_id', $s['teacher']->id);
    }

    public function test_a_teacher_cannot_record_outside_their_group(): void
    {
        $center = Center::factory()->create();
        $manager = User::factory()->manager()->for($center)->create();
        $teacherA = User::factory()->teacher()->for($center)->create();
        $teacherB = User::factory()->teacher()->for($center)->create();
        $groupB = Group::factory()->for($center)->create(['teacher_id' => $teacherB->id]);
        $student = Student::factory()->for($center)->create(['enrolled_at' => '2026-01-01']);

        app(AddStudentToGroup::class)->execute($groupB, $manager, $student->id);

        $this->withHeaders($this->auth($teacherA))->postJson('/api/v1/memorization', [
            'student_id' => $student->id,
            'memorization_date' => $this->today($center),
            'amount_source' => Memorization::SOURCE_MANUAL,
            'amount_pages' => '1',
        ])->assertNotFound();
    }

    public function test_cross_center_memorization_access_returns_404(): void
    {
        $centerA = Center::factory()->create();
        $centerB = Center::factory()->create();
        $managerA = User::factory()->manager()->for($centerA)->create();
        $teacherB = User::factory()->teacher()->for($centerB)->create();
        $groupB = Group::factory()->for($centerB)->create(['teacher_id' => $teacherB->id]);
        $studentB = Student::factory()->for($centerB)->create(['enrolled_at' => '2026-01-01']);

        $record = Memorization::factory()->for($centerB)->create([
            'student_id' => $studentB->id,
            'group_id' => $groupB->id,
            'teacher_id' => $teacherB->id,
            'memorization_date' => $this->today($centerB),
        ]);

        $headers = $this->auth($managerA);

        $this->withHeaders($headers)->getJson('/api/v1/memorization/'.$record->id)->assertNotFound();
        $this->withHeaders($headers)->patchJson('/api/v1/memorization/'.$record->id, ['notes' => 'x'])->assertNotFound();
        $this->withHeaders($headers)->deleteJson('/api/v1/memorization/'.$record->id)->assertNotFound();
    }

    public function test_simple_mode_requires_only_the_amount(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_MANUAL,
            'amount_pages' => '0.5',
        ])->assertCreated()
            ->assertJsonPath('data.surah_id', null)
            ->assertJsonPath('data.from_ayah', null)
            ->assertJsonPath('data.amount_pages', '0.5000');
    }

    public function test_professional_mode_calculates_from_the_provider(): void
    {
        $s = $this->scenario();

        $response = $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_CALCULATED,
            'surah_id' => 2,
            'from_ayah' => 1,
            'to_ayah' => 5,
            'grade' => 'very_good',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.amount_source', 'CALCULATED')
            ->assertJsonPath('data.amount_pages', '0.7333')
            ->assertJsonPath('data.amount_lines', 11)
            ->assertJsonPath('data.from_page', 2)
            ->assertJsonPath('data.to_page', 2)
            ->assertJsonPath('data.from_ayah', 1)
            ->assertJsonPath('data.to_ayah', 5)
            ->assertJsonPath('data.surah_name', 'البقرة');

        $this->assertDatabaseHas('memorizations', [
            'quran_dataset_version_id' => 1,
            'quran_dataset_version' => 'fixture-dev-v1',
        ]);
    }

    public function test_manual_amount_is_stored_as_given(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_MANUAL,
            'amount_pages' => '2.25',
        ])->assertCreated()->assertJsonPath('data.amount_pages', '2.2500');
    }

    public function test_calculated_amount_ignores_a_client_supplied_amount(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_CALCULATED,
            'amount_pages' => '9.9',
            'surah_id' => 2,
            'from_ayah' => 1,
            'to_ayah' => 5,
        ])->assertCreated()->assertJsonPath('data.amount_pages', '0.7333');
    }

    public function test_calculated_fails_clearly_when_the_provider_cannot_measure(): void
    {
        $this->app->instance(QuranDataProvider::class, new FailingQuranDataProvider);

        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_CALCULATED,
            'surah_id' => 2,
            'from_ayah' => 1,
            'to_ayah' => 5,
        ])->assertStatus(422)->assertJsonValidationErrors('surah_id');
    }

    public function test_the_surah_name_degrades_to_null_when_the_dataset_disappears(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/memorization', [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_MANUAL,
            'amount_pages' => '0.5',
            'surah_id' => 2,
            'from_ayah' => 1,
            'to_ayah' => 5,
        ])->assertCreated()->assertJsonPath('data.surah_name', 'البقرة');

        // The dataset goes away: the same record must still be listed, with the
        // name dropped rather than the whole response turned into a 503.
        $this->app->instance(QuranDataProvider::class, new FailingQuranDataProvider);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/memorization')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.surah_name', null)
            ->assertJsonPath('data.0.surah_id', 2);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/memorization/'.Memorization::query()->value('id'))
            ->assertOk()
            ->assertJsonPath('data.surah_name', null);
    }

    public function test_a_manual_record_is_stored_when_the_dataset_is_unavailable(): void
    {
        $this->app->instance(QuranDataProvider::class, new FailingQuranDataProvider);

        $s = $this->scenario();

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/memorization', [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_MANUAL,
            'amount_pages' => '1.5',
        ])->assertCreated()
            ->assertJsonPath('data.surah_name', null)
            ->assertJsonPath('data.amount_pages', '1.5000');

        $this->assertDatabaseCount('memorizations', 1);
    }

    public function test_an_unexpected_enrichment_failure_is_not_swallowed(): void
    {
        $s = $this->scenario();

        // Only QuranDataUnavailable is tolerated; a bug in the provider must
        // still surface instead of being hidden behind a null name.
        $this->app->instance(QuranDataProvider::class, new class implements QuranDataProvider
        {
            public function surahs(): array
            {
                throw new \RuntimeException('boom');
            }

            public function linesOnPage(int $page): int
            {
                throw new \RuntimeException('boom');
            }

            public function ayahLocation(int $surahId, int $ayahNumber): AyahLocation
            {
                throw new \RuntimeException('boom');
            }

            public function measure(int $fromAyahId, int $toAyahId): PageMeasurement
            {
                throw new \RuntimeException('boom');
            }

            public function datasetVersion(): string
            {
                return 'none';
            }

            public function datasetVersionId(): int
            {
                return 0;
            }
        });

        $this->withoutExceptionHandling();

        $this->expectException(\RuntimeException::class);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/memorization');
    }

    public function test_an_unknown_surah_is_rejected(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_CALCULATED,
            'surah_id' => 999,
            'from_ayah' => 1,
            'to_ayah' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('surah_id');
    }

    public function test_an_unmapped_ayah_is_rejected(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_CALCULATED,
            'surah_id' => 2,
            'from_ayah' => 1,
            'to_ayah' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors('surah_id');
    }

    public function test_start_ayah_after_end_ayah_is_rejected(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_CALCULATED,
            'surah_id' => 2,
            'from_ayah' => 5,
            'to_ayah' => 3,
        ])->assertStatus(422)->assertJsonValidationErrors('from_ayah');
    }

    public function test_page_start_and_page_end_are_stored_across_pages(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_CALCULATED,
            'surah_id' => 2,
            'from_ayah' => 7,
            'to_ayah' => 7,
        ])->assertCreated()
            ->assertJsonPath('data.from_page', 2)
            ->assertJsonPath('data.to_page', 3)
            ->assertJsonPath('data.amount_lines', 3)
            ->assertJsonPath('data.amount_pages', '0.2000');
    }

    public function test_the_group_at_recording_time_is_preserved(): void
    {
        $s = $this->scenario();

        $response = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/memorization', [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_MANUAL,
            'amount_pages' => '1',
        ]);

        $id = $response->json('data.id');

        $teacherB = User::factory()->teacher()->for($s['center'])->create();
        $groupB = Group::factory()->for($s['center'])->create(['teacher_id' => $teacherB->id]);
        app(AddStudentToGroup::class)->execute($groupB, $s['manager'], $s['student']->id);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/memorization/'.$id)
            ->assertOk()
            ->assertJsonPath('data.group_id', $s['group']->id);
    }

    public function test_the_author_is_stored(): void
    {
        $s = $this->scenario();

        $managerRecord = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/memorization', [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_MANUAL,
            'amount_pages' => '1',
        ])->assertCreated();

        $teacherRecord = $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_MANUAL,
            'amount_pages' => '1',
        ])->assertCreated();

        $this->assertSame($s['manager']->id, $managerRecord->json('data.teacher_id'));
        $this->assertSame($s['teacher']->id, $teacherRecord->json('data.teacher_id'));
    }

    public function test_grade_errors_and_notes_are_stored(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_MANUAL,
            'amount_pages' => '1',
            'grade' => 'needs_review',
            'hifz_errors' => 2,
            'tajweed_errors' => 1,
            'reading_errors' => 0,
            'notes' => 'يحتاج إعادة',
        ])->assertCreated()
            ->assertJsonPath('data.grade', 'needs_review')
            ->assertJsonPath('data.hifz_errors', 2)
            ->assertJsonPath('data.tajweed_errors', 1)
            ->assertJsonPath('data.reading_errors', 0)
            ->assertJsonPath('data.notes', 'يحتاج إعادة');
    }

    public function test_client_uuid_is_idempotent(): void
    {
        $s = $this->scenario();
        $payload = [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_MANUAL,
            'amount_pages' => '1',
            'client_uuid' => '11111111-1111-4111-8111-111111111111',
        ];

        $first = $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', $payload)
            ->assertCreated();

        $second = $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', $payload)
            ->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Memorization::query()->count());
    }

    public function test_a_replay_after_a_soft_delete_returns_the_deleted_record(): void
    {
        $s = $this->scenario();
        $payload = [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_MANUAL,
            'amount_pages' => '1',
            'client_uuid' => 'a1a1a1a1-1111-4111-8111-111111111111',
        ];

        $first = $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', $payload)
            ->assertCreated();

        $id = (int) $first->json('data.id');
        Memorization::query()->findOrFail($id)->delete();
        $this->assertSoftDeleted('memorizations', ['id' => $id]);

        // The unique index still holds the tombstone, so a lookup that ignored
        // trashed rows would collide instead of answering.
        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->assertSoftDeleted('memorizations', ['id' => $id]);
        $this->assertSame(1, Memorization::withTrashed()->count());
    }

    public function test_a_uuid_reused_for_another_student_is_rejected(): void
    {
        $s = $this->scenario();

        $other = Student::factory()->for($s['center'])->create(['enrolled_at' => '2026-01-01']);
        app(AddStudentToGroup::class)->execute($s['group'], $s['manager'], $other->id);

        $payload = [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_MANUAL,
            'amount_pages' => '1',
            'client_uuid' => 'a2a2a2a2-2222-4222-8222-222222222222',
        ];

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', $payload)
            ->assertCreated();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', [
            ...$payload,
            'student_id' => $other->id,
        ])->assertStatus(422)->assertJsonValidationErrors('client_uuid');

        // The second write is refused, not silently answered with the first.
        $this->assertSame(1, Memorization::withTrashed()->count());
    }

    public function test_a_concurrent_duplicate_returns_the_winning_record(): void
    {
        $s = $this->scenario();
        $uuid = 'a3a3a3a3-3333-4333-8333-333333333333';
        $date = $this->today($s['center']);

        // Simulate the other request winning between our lookup and our insert:
        // the winner lands while the model is being created, so the insert below
        // hits the unique index exactly as it would under real concurrency.
        $injected = false;
        Memorization::creating(function () use (&$injected, $s, $uuid, $date): void {
            if ($injected) {
                return;
            }

            $injected = true;

            DB::table('memorizations')->insert([
                'center_id' => $s['center']->id,
                'student_id' => $s['student']->id,
                'group_id' => $s['group']->id,
                'teacher_id' => $s['teacher']->id,
                'memorization_date' => $date,
                'amount_pages' => 1,
                'amount_source' => Memorization::SOURCE_MANUAL,
                'client_uuid' => $uuid,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $response = $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', [
            'student_id' => $s['student']->id,
            'memorization_date' => $date,
            'amount_source' => Memorization::SOURCE_MANUAL,
            'amount_pages' => '2',
            'client_uuid' => $uuid,
        ]);

        $response->assertOk();

        $this->assertTrue($injected);
        $this->assertSame(1, Memorization::withTrashed()->count());
        $this->assertSame((int) DB::table('memorizations')->value('id'), (int) $response->json('data.id'));
    }

    public function test_a_replay_of_a_record_the_actor_cannot_view_is_forbidden(): void
    {
        $s = $this->scenario();
        $payload = [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_MANUAL,
            'amount_pages' => '1',
            'client_uuid' => 'a4a4a4a4-4444-4444-8444-444444444444',
        ];

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', $payload)
            ->assertCreated();

        // The student moves to another group. Its teacher may record for them,
        // but may not read the record the previous teacher wrote.
        $otherTeacher = User::factory()->teacher()->for($s['center'])->create();
        $otherGroup = Group::factory()->for($s['center'])->create(['teacher_id' => $otherTeacher->id]);
        app(AddStudentToGroup::class)->execute($otherGroup, $s['manager'], $s['student']->id);

        $this->withHeaders($this->auth($otherTeacher))
            ->postJson('/api/v1/memorization', $payload)
            ->assertForbidden();

        $this->assertSame(1, Memorization::withTrashed()->count());
    }

    public function test_multiple_records_per_day_are_allowed(): void
    {
        $s = $this->scenario();
        $payload = [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_MANUAL,
            'amount_pages' => '0.5',
        ];

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', $payload)->assertCreated();
        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', $payload)->assertCreated();

        $this->assertSame(2, Memorization::query()->count());
    }

    public function test_a_manager_can_update_grade_and_notes(): void
    {
        $s = $this->scenario();

        $record = Memorization::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'memorization_date' => $this->today($s['center']),
        ]);

        $this->withHeaders($this->auth($s['manager']))
            ->patchJson('/api/v1/memorization/'.$record->id, [
                'grade' => 'good',
                'notes' => 'تصحيح الملاحظة',
            ])
            ->assertOk()
            ->assertJsonPath('data.grade', 'good')
            ->assertJsonPath('data.notes', 'تصحيح الملاحظة');
    }

    public function test_a_teacher_can_update_todays_record(): void
    {
        $s = $this->scenario();

        $record = Memorization::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'memorization_date' => $this->today($s['center']),
        ]);

        $this->withHeaders($this->auth($s['teacher']))
            ->patchJson('/api/v1/memorization/'.$record->id, ['grade' => 'very_good'])
            ->assertOk()
            ->assertJsonPath('data.grade', 'very_good');
    }

    public function test_a_teacher_cannot_update_an_old_record(): void
    {
        $s = $this->scenario();

        $record = Memorization::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'memorization_date' => now($s['center']->timezone)->subDay()->toDateString(),
        ]);

        $this->withHeaders($this->auth($s['teacher']))
            ->patchJson('/api/v1/memorization/'.$record->id, ['grade' => 'good'])
            ->assertForbidden();
    }

    public function test_a_teacher_cannot_update_another_teachers_record(): void
    {
        $s = $this->scenario();
        $otherTeacher = User::factory()->teacher()->for($s['center'])->create();
        $otherGroup = Group::factory()->for($s['center'])->create(['teacher_id' => $otherTeacher->id]);

        $record = Memorization::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $otherGroup->id,
            'teacher_id' => $otherTeacher->id,
            'memorization_date' => $this->today($s['center']),
        ]);

        $this->withHeaders($this->auth($s['teacher']))
            ->patchJson('/api/v1/memorization/'.$record->id, ['grade' => 'good'])
            ->assertNotFound();
    }

    public function test_a_manager_can_soft_delete_a_record(): void
    {
        $s = $this->scenario();

        $record = Memorization::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'memorization_date' => $this->today($s['center']),
        ]);

        $this->withHeaders($this->auth($s['manager']))
            ->deleteJson('/api/v1/memorization/'.$record->id)
            ->assertNoContent();

        $this->assertSoftDeleted('memorizations', ['id' => $record->id]);
    }

    public function test_a_teacher_cannot_delete_a_record(): void
    {
        $s = $this->scenario();

        $record = Memorization::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'memorization_date' => $this->today($s['center']),
        ]);

        $this->withHeaders($this->auth($s['teacher']))
            ->deleteJson('/api/v1/memorization/'.$record->id)
            ->assertForbidden();
    }

    public function test_memorization_does_not_create_attendance(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/memorization', [
            'student_id' => $s['student']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_MANUAL,
            'amount_pages' => '1',
        ])->assertCreated();

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_a_calculated_amount_cannot_be_edited_manually(): void
    {
        $s = $this->scenario();

        $record = Memorization::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_CALCULATED,
            'amount_pages' => '0.7333',
            'amount_lines' => 11,
        ]);

        $this->withHeaders($this->auth($s['manager']))
            ->patchJson('/api/v1/memorization/'.$record->id, ['amount_pages' => '9.9'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount_pages');
    }

    public function test_a_manual_record_cannot_change_its_amount_source(): void
    {
        $s = $this->scenario();

        $record = Memorization::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'memorization_date' => $this->today($s['center']),
            'amount_source' => Memorization::SOURCE_MANUAL,
            'amount_pages' => '1.0000',
        ]);

        $this->withHeaders($this->auth($s['manager']))
            ->patchJson('/api/v1/memorization/'.$record->id, [
                'amount_source' => Memorization::SOURCE_CALCULATED,
                'amount_pages' => '2',
            ])
            ->assertOk()
            ->assertJsonPath('data.amount_source', 'MANUAL')
            ->assertJsonPath('data.amount_pages', '2.0000');
    }

    public function test_quran_surahs_are_served_to_authenticated_users(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))
            ->getJson('/api/v1/quran/surahs')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.name_ar', 'الفاتحة');
    }
}
