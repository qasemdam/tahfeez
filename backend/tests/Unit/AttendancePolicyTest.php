<?php

namespace Tests\Unit;

use App\Domain\Attendance\Models\Attendance;
use App\Domain\Groups\Models\Group;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use App\Policies\AttendancePolicy;
use PHPUnit\Framework\TestCase;

class AttendancePolicyTest extends TestCase
{
    private function attendance(string $date, int $centerId = 1, int $teacherId = 5): Attendance
    {
        $attendance = new Attendance([
            'center_id' => $centerId,
            'group_id' => 1,
            'teacher_id' => $teacherId,
            'attendance_date' => $date,
            'status' => Attendance::STATUS_PRESENT,
        ]);

        $attendance->setRelation('group', new Group([
            'center_id' => $centerId,
            'teacher_id' => $teacherId,
        ]));

        $attendance->setRelation('center', new Center([
            'timezone' => 'Asia/Riyadh',
        ]));

        return $attendance;
    }

    public function test_manager_can_view_and_update_any_record_in_the_center(): void
    {
        $manager = new User(['role' => User::ROLE_MANAGER, 'center_id' => 1]);
        $attendance = $this->attendance('2020-01-01');
        $policy = new AttendancePolicy;

        $this->assertTrue($policy->viewAny($manager));
        $this->assertTrue($policy->view($manager, $attendance));
        $this->assertTrue($policy->create($manager));
        $this->assertTrue($policy->update($manager, $attendance));
    }

    public function test_teacher_can_view_their_own_group_only(): void
    {
        $teacher = new User(['role' => User::ROLE_TEACHER, 'center_id' => 1]);
        $teacher->id = 5;

        $today = now('Asia/Riyadh')->toDateString();
        $own = $this->attendance($today, 1, 5);
        $other = $this->attendance($today, 1, 6);
        $policy = new AttendancePolicy;

        $this->assertTrue($policy->view($teacher, $own));
        $this->assertFalse($policy->view($teacher, $other));
    }

    public function test_teacher_can_update_today_only(): void
    {
        $teacher = new User(['role' => User::ROLE_TEACHER, 'center_id' => 1]);
        $teacher->id = 5;
        $policy = new AttendancePolicy;

        $today = $this->attendance(now('Asia/Riyadh')->toDateString(), 1, 5);
        $yesterday = $this->attendance(now('Asia/Riyadh')->subDay()->toDateString(), 1, 5);

        $this->assertTrue($policy->update($teacher, $today));
        $this->assertFalse($policy->update($teacher, $yesterday));
    }

    public function test_cross_center_is_never_allowed(): void
    {
        $manager = new User(['role' => User::ROLE_MANAGER, 'center_id' => 1]);
        $attendance = $this->attendance(now('Asia/Riyadh')->toDateString(), 2, 99);
        $policy = new AttendancePolicy;

        $this->assertFalse($policy->view($manager, $attendance));
        $this->assertFalse($policy->update($manager, $attendance));
    }
}
