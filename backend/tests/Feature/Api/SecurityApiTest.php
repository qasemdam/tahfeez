<?php

namespace Tests\Feature\Api;

use App\Domain\Evaluation\Models\WeeklyEvaluation;
use App\Domain\Groups\Actions\AddStudentToGroup;
use App\Domain\Groups\Models\Group;
use App\Domain\Memorization\Models\Memorization;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The authorization and isolation matrix, in one place.
 *
 * Each test asserts the shape the rest of the codebase relies on:
 * out-of-scope ids are **404** (never 403, never a leak), in-scope but
 * forbidden actions are **403**, and nothing crosses a centre boundary.
 */
class SecurityApiTest extends TestCase
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
     * Two centres, each with a manager, a teacher, a group and a student.
     *
     * @return array<string, mixed>
     */
    private function twoCentres(): array
    {
        $build = function (string $slug): array {
            $center = Center::factory()->create(['timezone' => 'Asia/Riyadh', 'slug' => $slug]);
            $manager = User::factory()->manager()->for($center)->create();
            $teacher = User::factory()->teacher()->for($center)->create();
            $group = Group::factory()->for($center)->create(['teacher_id' => $teacher->id]);
            $student = Student::factory()->for($center)->create(['enrolled_at' => '2026-01-01']);

            app(AddStudentToGroup::class)->execute($group, $manager, $student->id);

            return compact('center', 'manager', 'teacher', 'group', 'student');
        };

        return ['a' => $build('centre-a'), 'b' => $build('centre-b')];
    }

    private function memorization(array $s, string $pages = '1.0000'): Memorization
    {
        return Memorization::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'memorization_date' => '2026-10-05',
            'amount_pages' => $pages,
        ]);
    }

    public function test_login_is_rate_limited_per_credential_pair(): void
    {
        $center = Center::factory()->create();
        $user = User::factory()->teacher()->for($center)->create([
            'email' => 'teacher@example.test',
            'password' => 'secret-password',
        ]);

        // Five wrong attempts are allowed and rejected as bad credentials.
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        // The sixth is throttled, before the password is even checked.
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_another_account_behind_the_same_address_is_not_blocked(): void
    {
        $center = Center::factory()->create();
        $first = User::factory()->teacher()->for($center)->create(['password' => 'secret-password']);
        $second = User::factory()->teacher()->for($center)->create(['password' => 'secret-password']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $first->email,
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $first->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);

        // A different account from the same IP still signs in.
        $this->postJson('/api/v1/auth/login', [
            'email' => $second->email,
            'password' => 'secret-password',
        ])->assertOk();
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $center = Center::factory()->create();
        $user = User::factory()->teacher()->for($center)->create();

        [, $plain] = $user->issueToken('expired-device', now()->subDay());

        $this->withHeaders(['Authorization' => 'Bearer '.$plain])
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_a_revoked_token_is_rejected(): void
    {
        $center = Center::factory()->create();
        $user = User::factory()->teacher()->for($center)->create();
        $headers = $this->auth($user);

        $this->withHeaders($headers)->postJson('/api/v1/auth/logout')->assertOk();

        $this->withHeaders($headers)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_a_teacher_cannot_read_or_delete_another_teachers_record(): void
    {
        $c = $this->twoCentres();
        $record = $this->memorization($c['a']);

        // Same centre, different teacher: the record is outside their scope.
        $otherTeacher = User::factory()->teacher()->for($c['a']['center'])->create();

        $this->withHeaders($this->auth($otherTeacher))
            ->getJson('/api/v1/memorization/'.$record->id)
            ->assertNotFound();

        $this->withHeaders($this->auth($otherTeacher))
            ->deleteJson('/api/v1/memorization/'.$record->id)
            ->assertNotFound();

        $this->assertNull($record->fresh()->deleted_at);
    }

    public function test_a_teacher_cannot_update_or_delete_another_teachers_group(): void
    {
        $c = $this->twoCentres();
        $otherTeacher = User::factory()->teacher()->for($c['a']['center'])->create();

        $this->withHeaders($this->auth($otherTeacher))
            ->patchJson('/api/v1/groups/'.$c['a']['group']->id, ['name' => 'hijacked'])
            ->assertNotFound();

        $this->withHeaders($this->auth($otherTeacher))
            ->deleteJson('/api/v1/groups/'.$c['a']['group']->id)
            ->assertNotFound();

        $this->assertSame($c['a']['group']->name, $c['a']['group']->fresh()->name);
    }

    public function test_a_manager_cannot_reach_another_centre(): void
    {
        $c = $this->twoCentres();
        $record = $this->memorization($c['b']);

        $this->withHeaders($this->auth($c['a']['manager']))
            ->getJson('/api/v1/students/'.$c['b']['student']->id)
            ->assertNotFound();

        $this->withHeaders($this->auth($c['a']['manager']))
            ->getJson('/api/v1/groups/'.$c['b']['group']->id)
            ->assertNotFound();

        $this->withHeaders($this->auth($c['a']['manager']))
            ->getJson('/api/v1/memorization/'.$record->id)
            ->assertNotFound();

        $this->withHeaders($this->auth($c['a']['manager']))
            ->getJson('/api/v1/groups/'.$c['b']['group']->id.'/attendance')
            ->assertNotFound();
    }

    public function test_a_teacher_cannot_reach_another_centre(): void
    {
        $c = $this->twoCentres();

        $this->withHeaders($this->auth($c['a']['teacher']))
            ->getJson('/api/v1/students/'.$c['b']['student']->id)
            ->assertNotFound();

        $this->withHeaders($this->auth($c['a']['teacher']))
            ->getJson('/api/v1/groups/'.$c['b']['group']->id)
            ->assertNotFound();
    }

    public function test_a_cross_centre_student_cannot_be_pulled_into_a_group(): void
    {
        $c = $this->twoCentres();

        $this->withHeaders($this->auth($c['a']['manager']))
            ->postJson('/api/v1/groups/'.$c['a']['group']->id.'/students', [
                'student_id' => $c['b']['student']->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('group_memberships', [
            'group_id' => $c['a']['group']->id,
            'student_id' => $c['b']['student']->id,
        ]);
    }

    public function test_a_soft_deleted_record_is_not_readable_or_updatable(): void
    {
        $c = $this->twoCentres();
        $record = $this->memorization($c['a']);
        $record->delete();

        $this->withHeaders($this->auth($c['a']['manager']))
            ->getJson('/api/v1/memorization/'.$record->id)
            ->assertNotFound();

        $this->withHeaders($this->auth($c['a']['manager']))
            ->deleteJson('/api/v1/memorization/'.$record->id)
            ->assertNotFound();
    }

    public function test_a_soft_deleted_student_is_not_readable(): void
    {
        $c = $this->twoCentres();
        $c['a']['student']->delete();

        $this->withHeaders($this->auth($c['a']['manager']))
            ->getJson('/api/v1/students/'.$c['a']['student']->id)
            ->assertNotFound();
    }

    public function test_a_client_cannot_choose_the_centre_on_create(): void
    {
        $c = $this->twoCentres();

        $created = $this->withHeaders($this->auth($c['a']['manager']))
            ->postJson('/api/v1/students', [
                'full_name' => 'طالب جديد',
                'guardian_name' => 'ولي الأمر',
                'enrolled_at' => '2026-10-01',
                'center_id' => $c['b']['center']->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('students', [
            'id' => $created->json('data.id'),
            'center_id' => $c['a']['center']->id,
        ]);
    }

    public function test_a_historical_evaluation_stays_with_the_teacher_who_graded_it(): void
    {
        $c = $this->twoCentres();
        $centre = $c['a'];

        $evaluation = WeeklyEvaluation::factory()->for($centre['center'])->create([
            'student_id' => $centre['student']->id,
            'group_id' => $centre['group']->id,
            'teacher_id' => $centre['teacher']->id,
            'week_key' => '2026-10-24',
            'week_start_date' => '2026-10-24',
            'week_end_date' => '2026-10-30',
        ]);

        // The student moves to another group with another teacher afterwards.
        $newTeacher = User::factory()->teacher()->for($centre['center'])->create();
        $newGroup = Group::factory()->for($centre['center'])->create(['teacher_id' => $newTeacher->id]);
        app(AddStudentToGroup::class)->execute($newGroup, $centre['manager'], $centre['student']->id);

        // The teacher who graded the week keeps access to it…
        $this->withHeaders($this->auth($centre['teacher']))
            ->getJson('/api/v1/weekly-evaluations/'.$evaluation->id)
            ->assertOk();

        // …and the new teacher does not inherit someone else's history.
        $this->withHeaders($this->auth($newTeacher))
            ->getJson('/api/v1/weekly-evaluations/'.$evaluation->id)
            ->assertNotFound();
    }
}
