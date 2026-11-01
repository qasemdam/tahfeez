<?php

namespace Tests\Feature\Api;

use App\Domain\Attendance\Models\Attendance;
use App\Domain\Evaluation\Models\EvaluationProfile;
use App\Domain\Evaluation\Models\EvaluationProfileVersion;
use App\Domain\Evaluation\Models\WeeklyEvaluation;
use App\Domain\Groups\Actions\AddStudentToGroup;
use App\Domain\Groups\Models\Group;
use App\Domain\Memorization\Models\Memorization;
use App\Domain\Recitation\Models\Recitation;
use App\Domain\Review\Models\Review;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyEvaluationApiTest extends TestCase
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
    private function scenario(string $startDay = 'saturday'): array
    {
        $center = Center::factory()->create([
            'weekly_evaluation_start_day' => $startDay,
            'timezone' => 'Asia/Riyadh',
        ]);
        $manager = User::factory()->manager()->for($center)->create();
        $teacher = User::factory()->teacher()->for($center)->create();
        $group = Group::factory()->for($center)->create(['teacher_id' => $teacher->id]);
        $student = Student::factory()->for($center)->create(['enrolled_at' => '2026-01-01']);

        app(AddStudentToGroup::class)->execute($group, $manager, $student->id);

        return compact('center', 'manager', 'teacher', 'group', 'student');
    }

    private function memorization(array $s, string $date, string $pages): Memorization
    {
        return Memorization::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'memorization_date' => $date,
            'amount_pages' => $pages,
        ]);
    }

    private function review(array $s, string $date, string $pages): Review
    {
        return Review::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'review_date' => $date,
            'amount_pages' => $pages,
        ]);
    }

    private function recitation(array $s, string $date, string $grade): Recitation
    {
        return Recitation::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'recitation_date' => $date,
            'grade' => $grade,
            'hifz_errors' => 0,
            'tajweed_errors' => 0,
            'reading_errors' => 0,
        ]);
    }

    private function attendance(array $s, string $date, string $status): Attendance
    {
        return Attendance::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'attendance_date' => $date,
            'status' => $status,
            'late_minutes' => null,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     * @param  array<string, string>  $gradeScores
     * @return array<string, mixed>
     */
    private function ruleSet(array $criteria, array $gradeScores = []): array
    {
        return [
            'version' => 'v1',
            'criteria' => $criteria,
            'recitation_grade_scores' => $gradeScores,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array<string, mixed>
     */
    private function criterion(string $key, string $weight, string $policy = 'block', array $params = []): array
    {
        return [
            'key' => $key,
            'weight' => $weight,
            'calculator' => $key,
            'missing_data_policy' => $policy,
            'params' => $params,
        ];
    }

    private function profileVersion(
        array $s,
        array $ruleSet,
        string $requiredPages = '1.00',
        string $effectiveFrom = '2026-01-01',
        int $version = 1,
    ): EvaluationProfileVersion {
        $profile = EvaluationProfile::firstOrCreate([
            'center_id' => $s['center']->id,
            'student_id' => $s['student']->id,
        ]);

        return EvaluationProfileVersion::factory()->for($s['center'])->create([
            'profile_id' => $profile->id,
            'version_number' => $version,
            'required_pages' => $requiredPages,
            'effective_from' => $effectiveFrom,
            'rule_set' => $ruleSet,
        ]);
    }

    public function test_a_guest_cannot_list_weekly_evaluations(): void
    {
        $this->getJson('/api/v1/weekly-evaluations')->assertUnauthorized();
    }

    public function test_a_manager_can_create_a_weekly_evaluation(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-26', '1.0000');

        $response = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.student_id', $s['student']->id)
            ->assertJsonPath('data.group_id', $s['group']->id)
            ->assertJsonPath('data.teacher_id', $s['teacher']->id)
            ->assertJsonPath('data.week_key', '2026-10-24')
            ->assertJsonPath('data.week_start_date', '2026-10-24')
            ->assertJsonPath('data.week_end_date', '2026-10-30')
            ->assertJsonPath('data.weekly_evaluation_start_day', 'saturday')
            ->assertJsonPath('data.required_pages', '1.00')
            ->assertJsonPath('data.actual_pages', '1.0000')
            ->assertJsonPath('data.achievement_percentage', '100.00')
            ->assertJsonPath('data.weighted_score', null)
            ->assertJsonPath('data.is_complete', true)
            ->assertJsonPath('data.status', 'computed');

        $this->assertDatabaseHas('weekly_evaluations', [
            'center_id' => $s['center']->id,
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'week_key' => '2026-10-24',
        ]);
    }

    public function test_achievement_is_uncapped(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-26', '1.5000');

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
        ])->assertCreated()->assertJsonPath('data.achievement_percentage', '150.00');
    }

    public function test_an_overachiever_is_not_normalized(): void
    {
        $s = $this->scenario();
        $second = Student::factory()->for($s['center'])->create(['enrolled_at' => '2026-01-01']);
        app(AddStudentToGroup::class)->execute($s['group'], $s['manager'], $second->id);

        $this->memorization($s, '2026-10-26', '1.0000');
        Memorization::factory()->for($s['center'])->create([
            'student_id' => $second->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'memorization_date' => '2026-10-26',
            'amount_pages' => '2.0000',
        ]);

        $first = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
        ])->assertCreated();

        $secondResponse = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $second->id,
            'week_date' => '2026-10-26',
        ])->assertCreated();

        $this->assertSame('100.00', $first->json('data.achievement_percentage'));
        $this->assertSame('200.00', $secondResponse->json('data.achievement_percentage'));
    }

    public function test_review_is_separate_from_memorization(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-26', '1.0000');
        $this->review($s, '2026-10-26', '0.5000');

        $response = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
        ])->assertCreated();

        $this->assertSame('100.00', $response->json('data.achievement_percentage'));
        $this->assertSame('1.0000', $response->json('data.actual_pages'));

        $record = WeeklyEvaluation::firstOrFail();
        $this->assertSame('0.5000', $record->rules_snapshot['inputs']['review_pages']);
    }

    public function test_a_rule_set_computes_the_weighted_score(): void
    {
        $s = $this->scenario();
        $version = $this->profileVersion($s, $this->ruleSet([
            $this->criterion('memorization_pages', '50', 'block', ['required_pages' => '1']),
            $this->criterion('review_completion', '50', 'block', ['target_pages' => '1']),
        ]));
        $this->memorization($s, '2026-10-26', '1.0000');
        $this->review($s, '2026-10-26', '0.5000');

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
            'profile_version_id' => $version->id,
        ])->assertCreated()
            ->assertJsonPath('data.evaluation_profile_version', 1)
            ->assertJsonPath('data.achievement_percentage', '100.00')
            ->assertJsonPath('data.weighted_score', '75.00');
    }

    public function test_recitation_enters_through_the_profile_grade_scores(): void
    {
        $s = $this->scenario();
        $version = $this->profileVersion($s, $this->ruleSet([
            $this->criterion('recitation_quality', '100', 'block', ['aggregate' => 'mean']),
        ], [
            'excellent' => '100',
            'good' => '80',
        ]));
        $this->recitation($s, '2026-10-26', 'excellent');
        $this->recitation($s, '2026-10-27', 'good');

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
            'profile_version_id' => $version->id,
        ])->assertCreated()->assertJsonPath('data.weighted_score', '90.00');
    }

    public function test_attendance_is_opt_in(): void
    {
        $s = $this->scenario();
        $version = $this->profileVersion($s, $this->ruleSet([
            $this->criterion('memorization_pages', '100', 'block', ['required_pages' => '1']),
        ]));
        $this->memorization($s, '2026-10-26', '1.0000');
        $this->attendance($s, '2026-10-26', 'present');
        $this->attendance($s, '2026-10-27', 'unexcused_absence');

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
            'profile_version_id' => $version->id,
        ])->assertCreated()->assertJsonPath('data.weighted_score', '100.00');
    }

    public function test_attendance_contributes_when_the_rule_set_includes_it(): void
    {
        $s = $this->scenario();
        $version = $this->profileVersion($s, $this->ruleSet([
            $this->criterion('attendance', '100', 'block', [
                'status_scores' => [
                    'present' => '100',
                    'late' => '80',
                    'excused_absence' => '100',
                    'unexcused_absence' => '0',
                ],
                'count_absences_in_denominator' => true,
            ]),
        ]));
        $this->attendance($s, '2026-10-26', 'present');
        $this->attendance($s, '2026-10-27', 'unexcused_absence');

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
            'profile_version_id' => $version->id,
        ])->assertCreated()->assertJsonPath('data.weighted_score', '50.00');
    }

    public function test_missing_data_policy_block_marks_the_result_incomplete(): void
    {
        $s = $this->scenario();
        $version = $this->profileVersion($s, $this->ruleSet([
            $this->criterion('recitation_quality', '100', 'block', ['aggregate' => 'mean']),
        ]));

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
            'profile_version_id' => $version->id,
        ])->assertCreated()
            ->assertJsonPath('data.weighted_score', null)
            ->assertJsonPath('data.is_complete', false)
            ->assertJsonPath('data.blocked_criteria.0', 'recitation_quality');
    }

    public function test_the_snapshot_is_stored(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-26', '1.0000');

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
        ])->assertCreated();

        $record = WeeklyEvaluation::firstOrFail();

        $this->assertSame('2026-10-24', $record->rules_snapshot['week_start_date']);
        $this->assertSame('2026-10-30', $record->rules_snapshot['week_end_date']);
        $this->assertSame('saturday', $record->rules_snapshot['weekly_evaluation_start_day']);
        $this->assertSame('1.0000', $record->rules_snapshot['inputs']['memorization_pages']);
        $this->assertSame('100.00', $record->result_snapshot['achievement_percentage']);
        $this->assertArrayHasKey('components', $record->result_snapshot);
    }

    public function test_changing_the_profile_does_not_change_an_existing_evaluation(): void
    {
        $s = $this->scenario();
        $v1 = $this->profileVersion($s, $this->ruleSet([
            $this->criterion('memorization_pages', '100', 'block', ['required_pages' => '1']),
        ]));
        $this->memorization($s, '2026-10-26', '1.0000');

        $created = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
            'profile_version_id' => $v1->id,
        ])->assertCreated();

        $id = $created->json('data.id');

        // A new version with a different rule set must not rewrite history.
        $this->profileVersion($s, $this->ruleSet([
            $this->criterion('memorization_pages', '100', 'block', ['required_pages' => '5']),
        ]), '5.00', '2026-11-01', 2);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/weekly-evaluations/'.$id)
            ->assertOk()
            ->assertJsonPath('data.weighted_score', '100.00')
            ->assertJsonPath('data.required_pages', '1.00')
            ->assertJsonPath('data.evaluation_profile_version', 1);
    }

    public function test_changing_the_week_start_day_does_not_change_old_boundaries(): void
    {
        $s = $this->scenario('saturday');
        $this->memorization($s, '2026-10-26', '1.0000');

        $created = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
        ])->assertCreated();

        $id = $created->json('data.id');
        $s['center']->update(['weekly_evaluation_start_day' => 'monday']);

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/weekly-evaluations/'.$id)
            ->assertOk()
            ->assertJsonPath('data.week_start_date', '2026-10-24')
            ->assertJsonPath('data.week_end_date', '2026-10-30')
            ->assertJsonPath('data.weekly_evaluation_start_day', 'saturday');
    }

    public function test_a_duplicate_student_week_returns_the_existing_evaluation(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-26', '1.0000');

        $first = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
        ])->assertCreated();

        $second = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-27',
        ])->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, WeeklyEvaluation::query()->count());
    }

    public function test_client_uuid_is_idempotent(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-26', '1.0000');

        $payload = [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
            'client_uuid' => '44444444-4444-4444-8444-444444444444',
        ];

        $first = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', $payload)
            ->assertCreated();

        $second = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-11-02',
            'client_uuid' => '44444444-4444-4444-8444-444444444444',
        ])->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, WeeklyEvaluation::query()->count());
    }

    public function test_cross_center_access_returns_404(): void
    {
        $centerA = Center::factory()->create();
        $centerB = Center::factory()->create();
        $managerA = User::factory()->manager()->for($centerA)->create();
        $teacherB = User::factory()->teacher()->for($centerB)->create();
        $groupB = Group::factory()->for($centerB)->create(['teacher_id' => $teacherB->id]);
        $studentB = Student::factory()->for($centerB)->create(['enrolled_at' => '2026-01-01']);

        $evaluation = WeeklyEvaluation::factory()->for($centerB)->create([
            'student_id' => $studentB->id,
            'group_id' => $groupB->id,
            'teacher_id' => $teacherB->id,
        ]);

        $headers = $this->auth($managerA);

        $this->withHeaders($headers)->getJson('/api/v1/weekly-evaluations/'.$evaluation->id)->assertNotFound();
        $this->withHeaders($headers)->patchJson('/api/v1/weekly-evaluations/'.$evaluation->id, ['status' => 'approved'])->assertNotFound();
    }

    public function test_a_teacher_can_view_their_groups_evaluation_only(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-26', '1.0000');

        $created = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
        ])->assertCreated();

        $this->withHeaders($this->auth($s['teacher']))
            ->getJson('/api/v1/weekly-evaluations/'.$created->json('data.id'))
            ->assertOk();

        $otherTeacher = User::factory()->teacher()->for($s['center'])->create();
        $otherGroup = Group::factory()->for($s['center'])->create(['teacher_id' => $otherTeacher->id]);
        $otherStudent = Student::factory()->for($s['center'])->create(['enrolled_at' => '2026-01-01']);

        $other = WeeklyEvaluation::factory()->for($s['center'])->create([
            'student_id' => $otherStudent->id,
            'group_id' => $otherGroup->id,
            'teacher_id' => $otherTeacher->id,
        ]);

        $this->withHeaders($this->auth($s['teacher']))
            ->getJson('/api/v1/weekly-evaluations/'.$other->id)
            ->assertNotFound();
    }

    public function test_a_teacher_cannot_create_or_approve(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-26', '1.0000');

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
        ])->assertForbidden();

        $evaluation = WeeklyEvaluation::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
        ]);

        $this->withHeaders($this->auth($s['teacher']))
            ->patchJson('/api/v1/weekly-evaluations/'.$evaluation->id, ['status' => 'approved'])
            ->assertForbidden();
    }

    public function test_a_manager_can_approve_a_computed_evaluation(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-26', '1.0000');

        $created = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
        ])->assertCreated();

        $this->withHeaders($this->auth($s['manager']))
            ->patchJson('/api/v1/weekly-evaluations/'.$created->json('data.id'), ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_the_week_start_day_is_center_configuration(): void
    {
        $s = $this->scenario('monday');
        $this->memorization($s, '2026-10-28', '1.0000');

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-31',
        ])->assertCreated()
            ->assertJsonPath('data.week_start_date', '2026-10-26')
            ->assertJsonPath('data.week_end_date', '2026-11-01')
            ->assertJsonPath('data.weekly_evaluation_start_day', 'monday');
    }

    public function test_required_pages_come_from_the_profile(): void
    {
        $s = $this->scenario();
        $version = $this->profileVersion($s, $this->ruleSet([
            $this->criterion('memorization_pages', '100', 'block', ['required_pages' => '2']),
        ]), '2.00');
        $this->memorization($s, '2026-10-26', '1.0000');

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
            'profile_version_id' => $version->id,
        ])->assertCreated()
            ->assertJsonPath('data.required_pages', '2.00')
            ->assertJsonPath('data.achievement_percentage', '50.00');
    }

    public function test_the_profile_version_effective_at_week_start_is_used(): void
    {
        $s = $this->scenario();
        $v1 = $this->profileVersion($s, $this->ruleSet([
            $this->criterion('memorization_pages', '100', 'block', ['required_pages' => '1']),
        ]), '1.00', '2026-01-01', 1);

        $this->profileVersion($s, $this->ruleSet([
            $this->criterion('memorization_pages', '100', 'block', ['required_pages' => '2']),
        ]), '2.00', '2026-11-01', 2);

        $this->memorization($s, '2026-10-26', '1.0000');

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
        ])->assertCreated()
            ->assertJsonPath('data.evaluation_profile_version', 1)
            ->assertJsonPath('data.required_pages', '1.00');
    }

    public function test_a_profile_version_not_effective_for_the_week_is_rejected(): void
    {
        $s = $this->scenario();
        $future = $this->profileVersion($s, $this->ruleSet([
            $this->criterion('memorization_pages', '100', 'block', ['required_pages' => '1']),
        ]), '1.00', '2026-11-01', 2);

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
            'profile_version_id' => $future->id,
        ])->assertStatus(422)->assertJsonValidationErrors('profile_version_id');
    }

    public function test_show_does_not_recompute_after_new_records(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-26', '1.0000');

        $created = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
        ])->assertCreated();

        $this->memorization($s, '2026-10-27', '1.0000');

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/weekly-evaluations/'.$created->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.actual_pages', '1.0000')
            ->assertJsonPath('data.achievement_percentage', '100.00');
    }

    public function test_evaluations_can_be_filtered(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-26', '1.0000');

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
        ])->assertCreated();

        $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/weekly-evaluations?student_id='.$s['student']->id.'&week_key=2026-10-24')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.week_key', '2026-10-24');
    }
}
