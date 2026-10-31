<?php

namespace Tests\Feature\Api;

use App\Domain\Groups\Actions\AddStudentToGroup;
use App\Domain\Groups\Models\Group;
use App\Domain\Groups\Models\GroupMembership;
use App\Domain\Students\Models\Student;
use App\Domain\Students\Models\StudentTeacherAssignment;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupApiTest extends TestCase
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

    private function manager(Center $center): User
    {
        return User::factory()->manager()->for($center)->create();
    }

    private function teacher(Center $center): User
    {
        return User::factory()->teacher()->for($center)->create();
    }

    public function test_a_guest_cannot_list_groups(): void
    {
        $this->getJson('/api/v1/groups')->assertUnauthorized();
    }

    public function test_a_manager_can_create_a_group(): void
    {
        $center = Center::factory()->create();
        $manager = $this->manager($center);
        $teacher = $this->teacher($center);

        $response = $this->withHeaders($this->auth($manager))->postJson('/api/v1/groups', [
            'name' => 'حلقة النور',
            'teacher_id' => $teacher->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'حلقة النور')
            ->assertJsonPath('data.teacher_id', $teacher->id)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.students_count', 0);

        $this->assertDatabaseHas('groups', [
            'center_id' => $center->id,
            'teacher_id' => $teacher->id,
            'name' => 'حلقة النور',
        ]);
    }

    public function test_a_manager_sees_every_group_in_the_center(): void
    {
        $center = Center::factory()->create();
        $manager = $this->manager($center);
        $first = $this->teacher($center);
        $second = $this->teacher($center);

        Group::factory()->for($center)->create(['teacher_id' => $first->id]);
        Group::factory()->for($center)->create(['teacher_id' => $second->id]);
        Group::factory()->create(); // another center

        $this->withHeaders($this->auth($manager))
            ->getJson('/api/v1/groups')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_a_teacher_sees_only_their_own_groups(): void
    {
        $center = Center::factory()->create();
        $teacher = $this->teacher($center);
        $other = $this->teacher($center);

        Group::factory()->for($center)->create(['teacher_id' => $teacher->id]);
        Group::factory()->for($center)->create(['teacher_id' => $other->id]);

        $this->withHeaders($this->auth($teacher))
            ->getJson('/api/v1/groups')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_teacher_cannot_view_another_teachers_group(): void
    {
        $center = Center::factory()->create();
        $teacher = $this->teacher($center);
        $other = $this->teacher($center);
        $group = Group::factory()->for($center)->create(['teacher_id' => $other->id]);

        $this->withHeaders($this->auth($teacher))
            ->getJson('/api/v1/groups/'.$group->id)
            ->assertNotFound();
    }

    public function test_a_manager_can_rename_a_group(): void
    {
        $center = Center::factory()->create();
        $manager = $this->manager($center);
        $teacher = $this->teacher($center);
        $group = Group::factory()->for($center)->create(['teacher_id' => $teacher->id]);

        $this->withHeaders($this->auth($manager))
            ->patchJson('/api/v1/groups/'.$group->id, ['name' => 'حلقة الفجر'])
            ->assertOk()
            ->assertJsonPath('data.name', 'حلقة الفجر');
    }

    public function test_changing_the_teacher_reassigns_active_students(): void
    {
        $center = Center::factory()->create();
        $manager = $this->manager($center);
        $old = $this->teacher($center);
        $new = $this->teacher($center);
        $group = Group::factory()->for($center)->create(['teacher_id' => $old->id]);
        $student = Student::factory()->for($center)->create();

        app(AddStudentToGroup::class)->execute($group, $manager, $student->id);

        $this->withHeaders($this->auth($manager))
            ->patchJson('/api/v1/groups/'.$group->id, ['teacher_id' => $new->id])
            ->assertOk()
            ->assertJsonPath('data.teacher_id', $new->id);

        $this->assertDatabaseHas('student_teacher_assignments', [
            'student_id' => $student->id,
            'teacher_id' => $new->id,
            'group_id' => $group->id,
            'unassigned_at' => null,
        ]);

        // The previous assignment stays as history (BR-AUT-05).
        $this->assertDatabaseHas('student_teacher_assignments', [
            'student_id' => $student->id,
            'teacher_id' => $old->id,
            'group_id' => $group->id,
        ]);
    }

    public function test_a_manager_can_add_a_student_to_a_group(): void
    {
        $center = Center::factory()->create();
        $manager = $this->manager($center);
        $teacher = $this->teacher($center);
        $group = Group::factory()->for($center)->create(['teacher_id' => $teacher->id]);
        $student = Student::factory()->for($center)->create(['full_name' => 'أحمد']);

        $response = $this->withHeaders($this->auth($manager))
            ->postJson('/api/v1/groups/'.$group->id.'/students', ['student_id' => $student->id]);

        $response->assertOk()
            ->assertJsonPath('data.students_count', 1)
            ->assertJsonPath('data.students.0.id', $student->id)
            ->assertJsonPath('data.students.0.full_name', 'أحمد');

        $this->assertDatabaseHas('group_memberships', [
            'group_id' => $group->id,
            'student_id' => $student->id,
            'left_at' => null,
        ]);

        $this->assertDatabaseHas('student_teacher_assignments', [
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'group_id' => $group->id,
            'unassigned_at' => null,
        ]);
    }

    public function test_adding_the_same_student_twice_is_idempotent(): void
    {
        $center = Center::factory()->create();
        $manager = $this->manager($center);
        $teacher = $this->teacher($center);
        $group = Group::factory()->for($center)->create(['teacher_id' => $teacher->id]);
        $student = Student::factory()->for($center)->create();

        $headers = $this->auth($manager);

        $this->withHeaders($headers)
            ->postJson('/api/v1/groups/'.$group->id.'/students', ['student_id' => $student->id])
            ->assertOk();

        $this->withHeaders($headers)
            ->postJson('/api/v1/groups/'.$group->id.'/students', ['student_id' => $student->id])
            ->assertOk();

        $this->assertSame(1, GroupMembership::query()
            ->where('student_id', $student->id)
            ->whereNull('left_at')
            ->count());

        $this->assertSame(1, StudentTeacherAssignment::query()
            ->where('student_id', $student->id)
            ->whereNull('unassigned_at')
            ->count());
    }

    public function test_moving_a_student_between_groups_preserves_history(): void
    {
        $center = Center::factory()->create();
        $manager = $this->manager($center);
        $firstTeacher = $this->teacher($center);
        $secondTeacher = $this->teacher($center);
        $first = Group::factory()->for($center)->create(['teacher_id' => $firstTeacher->id]);
        $second = Group::factory()->for($center)->create(['teacher_id' => $secondTeacher->id]);
        $student = Student::factory()->for($center)->create();

        app(AddStudentToGroup::class)->execute($first, $manager, $student->id);

        $this->withHeaders($this->auth($manager))
            ->postJson('/api/v1/groups/'.$second->id.'/students', ['student_id' => $student->id])
            ->assertOk();

        $oldMembership = GroupMembership::query()
            ->where('group_id', $first->id)
            ->where('student_id', $student->id)
            ->first();

        $this->assertNotNull($oldMembership);
        $this->assertNotNull($oldMembership->left_at);

        $this->assertSame(1, GroupMembership::query()
            ->where('student_id', $student->id)
            ->whereNull('left_at')
            ->count());

        $this->assertDatabaseHas('group_memberships', [
            'group_id' => $second->id,
            'student_id' => $student->id,
            'left_at' => null,
        ]);

        $this->assertDatabaseHas('student_teacher_assignments', [
            'student_id' => $student->id,
            'teacher_id' => $secondTeacher->id,
            'group_id' => $second->id,
            'unassigned_at' => null,
        ]);
    }

    public function test_a_manager_can_remove_a_student_from_a_group(): void
    {
        $center = Center::factory()->create();
        $manager = $this->manager($center);
        $teacher = $this->teacher($center);
        $group = Group::factory()->for($center)->create(['teacher_id' => $teacher->id]);
        $student = Student::factory()->for($center)->create();

        app(AddStudentToGroup::class)->execute($group, $manager, $student->id);

        $this->withHeaders($this->auth($manager))
            ->deleteJson('/api/v1/groups/'.$group->id.'/students/'.$student->id)
            ->assertNoContent();

        $this->assertSame(0, GroupMembership::query()
            ->where('group_id', $group->id)
            ->where('student_id', $student->id)
            ->whereNull('left_at')
            ->count());

        $this->assertSame(0, StudentTeacherAssignment::query()
            ->where('student_id', $student->id)
            ->where('group_id', $group->id)
            ->whereNull('unassigned_at')
            ->count());
    }

    public function test_deleting_a_group_closes_memberships_and_assignments(): void
    {
        $center = Center::factory()->create();
        $manager = $this->manager($center);
        $teacher = $this->teacher($center);
        $group = Group::factory()->for($center)->create(['teacher_id' => $teacher->id]);
        $student = Student::factory()->for($center)->create();

        app(AddStudentToGroup::class)->execute($group, $manager, $student->id);

        $this->withHeaders($this->auth($manager))
            ->deleteJson('/api/v1/groups/'.$group->id)
            ->assertNoContent();

        $this->assertSoftDeleted('groups', ['id' => $group->id]);

        $this->assertSame(0, GroupMembership::query()
            ->where('group_id', $group->id)
            ->whereNull('left_at')
            ->count());

        $this->assertSame(0, StudentTeacherAssignment::query()
            ->where('group_id', $group->id)
            ->whereNull('unassigned_at')
            ->count());
    }

    public function test_a_teacher_cannot_create_update_delete_or_manage_students(): void
    {
        $center = Center::factory()->create();
        $teacher = $this->teacher($center);
        $group = Group::factory()->for($center)->create(['teacher_id' => $teacher->id]);
        $student = Student::factory()->for($center)->create();

        $headers = $this->auth($teacher);

        $this->withHeaders($headers)
            ->postJson('/api/v1/groups', ['name' => 'حلقة', 'teacher_id' => $teacher->id])
            ->assertForbidden();

        $this->withHeaders($headers)
            ->patchJson('/api/v1/groups/'.$group->id, ['name' => 'حلقة أخرى'])
            ->assertForbidden();

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/groups/'.$group->id)
            ->assertForbidden();

        $this->withHeaders($headers)
            ->postJson('/api/v1/groups/'.$group->id.'/students', ['student_id' => $student->id])
            ->assertForbidden();

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/groups/'.$group->id.'/students/'.$student->id)
            ->assertForbidden();
    }

    public function test_cross_center_group_access_returns_404(): void
    {
        $centerA = Center::factory()->create();
        $centerB = Center::factory()->create();
        $managerA = $this->manager($centerA);
        $teacherB = $this->teacher($centerB);
        $groupB = Group::factory()->for($centerB)->create(['teacher_id' => $teacherB->id]);

        $headers = $this->auth($managerA);

        $this->withHeaders($headers)->getJson('/api/v1/groups/'.$groupB->id)->assertNotFound();
        $this->withHeaders($headers)->patchJson('/api/v1/groups/'.$groupB->id, ['name' => 'اسم صالح'])->assertNotFound();
        $this->withHeaders($headers)->deleteJson('/api/v1/groups/'.$groupB->id)->assertNotFound();
    }

    public function test_a_cross_center_student_cannot_be_added(): void
    {
        $centerA = Center::factory()->create();
        $centerB = Center::factory()->create();
        $managerA = $this->manager($centerA);
        $teacherA = $this->teacher($centerA);
        $groupA = Group::factory()->for($centerA)->create(['teacher_id' => $teacherA->id]);
        $studentB = Student::factory()->for($centerB)->create();

        $this->withHeaders($this->auth($managerA))
            ->postJson('/api/v1/groups/'.$groupA->id.'/students', ['student_id' => $studentB->id])
            ->assertNotFound();
    }

    public function test_a_cross_center_teacher_cannot_be_assigned(): void
    {
        $centerA = Center::factory()->create();
        $centerB = Center::factory()->create();
        $managerA = $this->manager($centerA);
        $teacherB = $this->teacher($centerB);

        $this->withHeaders($this->auth($managerA))
            ->postJson('/api/v1/groups', ['name' => 'حلقة', 'teacher_id' => $teacherB->id])
            ->assertNotFound();
    }

    public function test_an_inactive_group_rejects_new_students(): void
    {
        $center = Center::factory()->create();
        $manager = $this->manager($center);
        $teacher = $this->teacher($center);
        $group = Group::factory()->for($center)->inactive()->create(['teacher_id' => $teacher->id]);
        $student = Student::factory()->for($center)->create();

        $this->withHeaders($this->auth($manager))
            ->postJson('/api/v1/groups/'.$group->id.'/students', ['student_id' => $student->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('group');
    }
}
