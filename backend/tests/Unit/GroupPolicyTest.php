<?php

namespace Tests\Unit;

use App\Domain\Groups\Models\Group;
use App\Models\User;
use App\Policies\GroupPolicy;
use PHPUnit\Framework\TestCase;

class GroupPolicyTest extends TestCase
{
    public function test_manager_holds_every_group_permission_in_the_center(): void
    {
        $manager = new User(['role' => User::ROLE_MANAGER, 'center_id' => 1]);
        $group = new Group(['center_id' => 1, 'teacher_id' => 99]);
        $policy = new GroupPolicy;

        $this->assertTrue($policy->viewAny($manager));
        $this->assertTrue($policy->view($manager, $group));
        $this->assertTrue($policy->create($manager));
        $this->assertTrue($policy->update($manager, $group));
        $this->assertTrue($policy->delete($manager, $group));
        $this->assertTrue($policy->manageStudents($manager, $group));
    }

    public function test_teacher_can_view_their_own_group_only(): void
    {
        $teacher = new User(['role' => User::ROLE_TEACHER, 'center_id' => 1]);
        $teacher->id = 5;

        $own = new Group(['center_id' => 1, 'teacher_id' => 5]);
        $other = new Group(['center_id' => 1, 'teacher_id' => 6]);
        $policy = new GroupPolicy;

        $this->assertTrue($policy->viewAny($teacher));
        $this->assertTrue($policy->view($teacher, $own));
        $this->assertFalse($policy->view($teacher, $other));
    }

    public function test_teacher_has_no_group_write_permissions_by_default(): void
    {
        $teacher = new User(['role' => User::ROLE_TEACHER, 'center_id' => 1]);
        $teacher->id = 5;
        $group = new Group(['center_id' => 1, 'teacher_id' => 5]);
        $policy = new GroupPolicy;

        $this->assertFalse($policy->create($teacher));
        $this->assertFalse($policy->update($teacher, $group));
        $this->assertFalse($policy->delete($teacher, $group));
        $this->assertFalse($policy->manageStudents($teacher, $group));
    }

    public function test_cross_center_is_never_allowed(): void
    {
        $manager = new User(['role' => User::ROLE_MANAGER, 'center_id' => 1]);
        $group = new Group(['center_id' => 2, 'teacher_id' => 99]);
        $policy = new GroupPolicy;

        $this->assertFalse($policy->view($manager, $group));
        $this->assertFalse($policy->update($manager, $group));
        $this->assertFalse($policy->delete($manager, $group));
        $this->assertFalse($policy->manageStudents($manager, $group));
    }
}
