<?php

namespace Tests\Unit;

use App\Domain\Groups\Models\Group;
use App\Domain\Memorization\Models\Memorization;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use App\Policies\MemorizationPolicy;
use PHPUnit\Framework\TestCase;

class MemorizationPolicyTest extends TestCase
{
    private function record(string $date, int $centerId = 1, int $teacherId = 5): Memorization
    {
        $record = new Memorization([
            'center_id' => $centerId,
            'group_id' => 1,
            'teacher_id' => $teacherId,
            'memorization_date' => $date,
            'amount_pages' => '1.0000',
            'amount_source' => Memorization::SOURCE_MANUAL,
        ]);

        $record->setRelation('group', new Group([
            'center_id' => $centerId,
            'teacher_id' => $teacherId,
        ]));

        $record->setRelation('center', new Center(['timezone' => 'Asia/Riyadh']));

        return $record;
    }

    public function test_manager_can_view_and_update_any_record_in_the_center(): void
    {
        $manager = new User(['role' => User::ROLE_MANAGER, 'center_id' => 1]);
        $record = $this->record('2020-01-01');
        $policy = new MemorizationPolicy;

        $this->assertTrue($policy->viewAny($manager));
        $this->assertTrue($policy->view($manager, $record));
        $this->assertTrue($policy->create($manager));
        $this->assertTrue($policy->update($manager, $record));
        $this->assertTrue($policy->delete($manager, $record));
    }

    public function test_teacher_can_view_their_own_record_only(): void
    {
        $teacher = new User(['role' => User::ROLE_TEACHER, 'center_id' => 1]);
        $teacher->id = 5;

        $today = now('Asia/Riyadh')->toDateString();
        $own = $this->record($today, 1, 5);
        $other = $this->record($today, 1, 6);
        $policy = new MemorizationPolicy;

        $this->assertTrue($policy->view($teacher, $own));
        $this->assertFalse($policy->view($teacher, $other));
    }

    public function test_teacher_can_update_today_only(): void
    {
        $teacher = new User(['role' => User::ROLE_TEACHER, 'center_id' => 1]);
        $teacher->id = 5;
        $policy = new MemorizationPolicy;

        $today = $this->record(now('Asia/Riyadh')->toDateString(), 1, 5);
        $yesterday = $this->record(now('Asia/Riyadh')->subDay()->toDateString(), 1, 5);

        $this->assertTrue($policy->update($teacher, $today));
        $this->assertFalse($policy->update($teacher, $yesterday));
    }

    public function test_teacher_cannot_delete_without_permission(): void
    {
        $teacher = new User(['role' => User::ROLE_TEACHER, 'center_id' => 1]);
        $teacher->id = 5;
        $record = $this->record(now('Asia/Riyadh')->toDateString(), 1, 5);
        $policy = new MemorizationPolicy;

        $this->assertFalse($policy->delete($teacher, $record));
    }

    public function test_cross_center_is_never_allowed(): void
    {
        $manager = new User(['role' => User::ROLE_MANAGER, 'center_id' => 1]);
        $record = $this->record(now('Asia/Riyadh')->toDateString(), 2, 99);
        $policy = new MemorizationPolicy;

        $this->assertFalse($policy->view($manager, $record));
        $this->assertFalse($policy->update($manager, $record));
        $this->assertFalse($policy->delete($manager, $record));
    }
}
