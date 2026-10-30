<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * BR-AUT-04: the manager holds everything; a teacher holds the role defaults
 * plus any explicit grants the manager enabled.
 */
class UserPermissionTest extends TestCase
{
    public function test_manager_holds_every_permission(): void
    {
        $manager = new User(['role' => User::ROLE_MANAGER]);

        $this->assertTrue($manager->hasPermission('students.create'));
        $this->assertTrue($manager->hasPermission('students.delete'));
        $this->assertTrue($manager->hasPermission('anything.at.all'));
    }

    public function test_teacher_holds_role_defaults(): void
    {
        $teacher = new User(['role' => User::ROLE_TEACHER]);

        $this->assertTrue($teacher->hasPermission('students.view'));
        $this->assertTrue($teacher->hasPermission('memorization.record'));
        $this->assertTrue($teacher->hasPermission('attendance.record'));
        $this->assertTrue($teacher->hasPermission('evaluation.view'));
    }

    public function test_teacher_does_not_hold_manager_only_permissions(): void
    {
        $teacher = new User(['role' => User::ROLE_TEACHER]);

        $this->assertFalse($teacher->hasPermission('students.create'));
        $this->assertFalse($teacher->hasPermission('students.delete'));
        $this->assertFalse($teacher->hasPermission('users.manage'));
    }

    public function test_teacher_grants_extend_role_defaults(): void
    {
        $teacher = new User([
            'role' => User::ROLE_TEACHER,
            'permissions' => ['students.update'],
        ]);

        $this->assertTrue($teacher->hasPermission('students.update'));
        $this->assertTrue($teacher->hasPermission('students.view'));
    }

    public function test_role_helpers(): void
    {
        $manager = new User(['role' => User::ROLE_MANAGER]);
        $teacher = new User(['role' => User::ROLE_TEACHER]);

        $this->assertTrue($manager->isManager());
        $this->assertFalse($manager->isTeacher());
        $this->assertTrue($teacher->isTeacher());
        $this->assertFalse($teacher->isManager());
    }
}
