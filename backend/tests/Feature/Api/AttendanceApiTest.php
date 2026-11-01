<?php

namespace Tests\Feature\Api;

use App\Domain\Attendance\Models\Attendance;
use App\Domain\Groups\Actions\AddStudentToGroup;
use App\Domain\Groups\Models\Group;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
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

    public function test_a_guest_cannot_list_attendance(): void
    {
        $this->getJson('/api/v1/attendance')->assertUnauthorized();
    }

    public function test_a_manager_can_record_attendance(): void
    {
        $s = $this->scenario();
        $date = $this->today($s['center']);

        $response = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/attendance', [
            'student_id' => $s['student']->id,
            'attendance_date' => $date,
            'status' => Attendance::STATUS_PRESENT,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.student_id', $s['student']->id)
            ->assertJsonPath('data.group_id', $s['group']->id)
            ->assertJsonPath('data.status', 'present')
            ->assertJsonPath('data.attendance_date', $date);

        $this->assertDatabaseHas('attendances', [
            'center_id' => $s['center']->id,
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['manager']->id,
            'attendance_date' => $date,
            'status' => 'present',
        ]);
    }

    public function test_a_teacher_can_record_attendance_for_their_group(): void
    {
        $s = $this->scenario();
        $date = $this->today($s['center']);

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/attendance', [
            'student_id' => $s['student']->id,
            'attendance_date' => $date,
            'status' => Attendance::STATUS_PRESENT,
        ])->assertCreated()->assertJsonPath('data.group_id', $s['group']->id);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $s['student']->id,
            'teacher_id' => $s['teacher']->id,
        ]);
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

        $this->withHeaders($this->auth($teacherA))->postJson('/api/v1/attendance', [
            'student_id' => $student->id,
            'attendance_date' => $this->today($center),
            'status' => 'present',
        ])->assertNotFound();
    }

    public function test_a_teacher_cannot_view_another_teachers_attendance(): void
    {
        $center = Center::factory()->create();
        $manager = User::factory()->manager()->for($center)->create();
        $teacherA = User::factory()->teacher()->for($center)->create();
        $teacherB = User::factory()->teacher()->for($center)->create();
        $groupB = Group::factory()->for($center)->create(['teacher_id' => $teacherB->id]);
        $student = Student::factory()->for($center)->create(['enrolled_at' => '2026-01-01']);

        app(AddStudentToGroup::class)->execute($groupB, $manager, $student->id);

        $attendance = Attendance::factory()->for($center)->create([
            'student_id' => $student->id,
            'group_id' => $groupB->id,
            'teacher_id' => $manager->id,
            'attendance_date' => $this->today($center),
            'status' => 'present',
        ]);

        $this->withHeaders($this->auth($teacherA))
            ->getJson('/api/v1/attendance/'.$attendance->id)
            ->assertNotFound();
    }

    public function test_cross_center_attendance_access_returns_404(): void
    {
        $centerA = Center::factory()->create();
        $centerB = Center::factory()->create();
        $managerA = User::factory()->manager()->for($centerA)->create();
        $teacherB = User::factory()->teacher()->for($centerB)->create();
        $groupB = Group::factory()->for($centerB)->create(['teacher_id' => $teacherB->id]);
        $studentB = Student::factory()->for($centerB)->create(['enrolled_at' => '2026-01-01']);

        $attendance = Attendance::factory()->for($centerB)->create([
            'student_id' => $studentB->id,
            'group_id' => $groupB->id,
            'teacher_id' => $teacherB->id,
            'attendance_date' => $this->today($centerB),
            'status' => 'present',
        ]);

        $headers = $this->auth($managerA);

        $this->withHeaders($headers)
            ->getJson('/api/v1/attendance/'.$attendance->id)
            ->assertNotFound();

        $this->withHeaders($headers)
            ->patchJson('/api/v1/attendance/'.$attendance->id, [
                'status' => 'late',
                'late_minutes' => 5,
            ])
            ->assertNotFound();
    }

    public function test_all_four_statuses_are_accepted(): void
    {
        $s = $this->scenario();
        $date = $this->today($s['center']);
        $headers = $this->auth($s['manager']);

        foreach (Attendance::STATUSES as $status) {
            $student = Student::factory()->for($s['center'])->create(['enrolled_at' => '2026-01-01']);
            app(AddStudentToGroup::class)->execute($s['group'], $s['manager'], $student->id);

            $this->withHeaders($headers)->postJson('/api/v1/attendance', [
                'student_id' => $student->id,
                'attendance_date' => $date,
                'status' => $status,
            ])->assertCreated()->assertJsonPath('data.status', $status);
        }
    }

    public function test_late_minutes_are_stored_for_late(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/attendance', [
            'student_id' => $s['student']->id,
            'attendance_date' => $this->today($s['center']),
            'status' => Attendance::STATUS_LATE,
            'late_minutes' => 15,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'late')
            ->assertJsonPath('data.late_minutes', 15);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $s['student']->id,
            'late_minutes' => 15,
        ]);
    }

    public function test_late_minutes_are_rejected_for_non_late_status(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/attendance', [
            'student_id' => $s['student']->id,
            'attendance_date' => $this->today($s['center']),
            'status' => Attendance::STATUS_PRESENT,
            'late_minutes' => 10,
        ])->assertStatus(422)->assertJsonValidationErrors('late_minutes');
    }

    public function test_negative_late_minutes_are_rejected(): void
    {
        $s = $this->scenario();

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/attendance', [
            'student_id' => $s['student']->id,
            'attendance_date' => $this->today($s['center']),
            'status' => Attendance::STATUS_LATE,
            'late_minutes' => -5,
        ])->assertStatus(422)->assertJsonValidationErrors('late_minutes');
    }

    public function test_duplicate_attendance_updates_the_existing_record(): void
    {
        $s = $this->scenario();
        $date = $this->today($s['center']);
        $headers = $this->auth($s['manager']);

        $this->withHeaders($headers)->postJson('/api/v1/attendance', [
            'student_id' => $s['student']->id,
            'attendance_date' => $date,
            'status' => Attendance::STATUS_PRESENT,
        ])->assertCreated();

        $this->withHeaders($headers)->postJson('/api/v1/attendance', [
            'student_id' => $s['student']->id,
            'attendance_date' => $date,
            'status' => Attendance::STATUS_EXCUSED,
        ])->assertOk()->assertJsonPath('data.status', 'excused_absence');

        $this->assertSame(1, Attendance::query()
            ->where('student_id', $s['student']->id)
            ->count());
    }

    public function test_the_group_at_recording_time_is_preserved(): void
    {
        $s = $this->scenario();
        $date = $this->today($s['center']);
        $headers = $this->auth($s['manager']);

        $this->withHeaders($headers)->postJson('/api/v1/attendance', [
            'student_id' => $s['student']->id,
            'attendance_date' => $date,
            'status' => Attendance::STATUS_PRESENT,
        ])->assertCreated();

        $teacherB = User::factory()->teacher()->for($s['center'])->create();
        $groupB = Group::factory()->for($s['center'])->create(['teacher_id' => $teacherB->id]);
        app(AddStudentToGroup::class)->execute($groupB, $s['manager'], $s['student']->id);

        $this->withHeaders($headers)->postJson('/api/v1/attendance', [
            'student_id' => $s['student']->id,
            'attendance_date' => $date,
            'status' => Attendance::STATUS_LATE,
            'late_minutes' => 5,
        ])->assertOk();

        $record = Attendance::query()
            ->where('student_id', $s['student']->id)
            ->firstOrFail();

        $this->assertSame($s['group']->id, $record->group_id);
        $this->assertSame('late', $record->status);
    }

    public function test_deleting_a_group_does_not_break_historical_attendance(): void
    {
        $s = $this->scenario();
        $date = $this->today($s['center']);
        $headers = $this->auth($s['manager']);

        $this->withHeaders($headers)->postJson('/api/v1/attendance', [
            'student_id' => $s['student']->id,
            'attendance_date' => $date,
            'status' => Attendance::STATUS_PRESENT,
        ])->assertCreated();

        $record = Attendance::query()
            ->where('student_id', $s['student']->id)
            ->firstOrFail();

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/groups/'.$s['group']->id)
            ->assertNoContent();

        $this->withHeaders($headers)
            ->getJson('/api/v1/attendance/'.$record->id)
            ->assertOk()
            ->assertJsonPath('data.group_id', $s['group']->id)
            ->assertJsonPath('data.group_name', $s['group']->name);
    }

    public function test_a_teacher_can_update_todays_record(): void
    {
        $s = $this->scenario();

        $attendance = Attendance::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'attendance_date' => $this->today($s['center']),
            'status' => 'present',
        ]);

        $this->withHeaders($this->auth($s['teacher']))
            ->patchJson('/api/v1/attendance/'.$attendance->id, [
                'status' => 'late',
                'late_minutes' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'late')
            ->assertJsonPath('data.late_minutes', 10);
    }

    public function test_a_teacher_cannot_update_an_old_record(): void
    {
        $s = $this->scenario();
        $yesterday = now($s['center']->timezone)->subDay()->toDateString();

        $attendance = Attendance::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'attendance_date' => $yesterday,
            'status' => 'present',
        ]);

        $this->withHeaders($this->auth($s['teacher']))
            ->patchJson('/api/v1/attendance/'.$attendance->id, [
                'status' => 'late',
                'late_minutes' => 10,
            ])
            ->assertForbidden();
    }

    public function test_a_manager_can_update_an_old_record(): void
    {
        $s = $this->scenario();
        $yesterday = now($s['center']->timezone)->subDay()->toDateString();

        $attendance = Attendance::factory()->for($s['center'])->create([
            'student_id' => $s['student']->id,
            'group_id' => $s['group']->id,
            'teacher_id' => $s['teacher']->id,
            'attendance_date' => $yesterday,
            'status' => 'present',
        ]);

        $this->withHeaders($this->auth($s['manager']))
            ->patchJson('/api/v1/attendance/'.$attendance->id, ['status' => 'excused_absence'])
            ->assertOk()
            ->assertJsonPath('data.status', 'excused_absence');
    }

    public function test_group_sheet_returns_students_without_records(): void
    {
        $s = $this->scenario();
        $date = $this->today($s['center']);

        $this->withHeaders($this->auth($s['teacher']))
            ->getJson('/api/v1/groups/'.$s['group']->id.'/attendance?date='.$date)
            ->assertOk()
            ->assertJsonPath('data.group_id', $s['group']->id)
            ->assertJsonPath('data.date', $date)
            ->assertJsonPath('data.students.0.student_id', $s['student']->id)
            ->assertJsonPath('data.students.0.status', null);
    }

    public function test_bulk_recording_is_idempotent(): void
    {
        $s = $this->scenario();
        $second = Student::factory()->for($s['center'])->create(['enrolled_at' => '2026-01-01']);
        app(AddStudentToGroup::class)->execute($s['group'], $s['manager'], $second->id);

        $date = $this->today($s['center']);
        $headers = $this->auth($s['teacher']);

        $this->withHeaders($headers)->postJson('/api/v1/groups/'.$s['group']->id.'/attendance', [
            'attendance_date' => $date,
            'records' => [
                ['student_id' => $s['student']->id, 'status' => 'present'],
                ['student_id' => $second->id, 'status' => 'unexcused_absence'],
            ],
        ])->assertOk();

        $this->withHeaders($headers)->postJson('/api/v1/groups/'.$s['group']->id.'/attendance', [
            'attendance_date' => $date,
            'records' => [
                ['student_id' => $s['student']->id, 'status' => 'late', 'late_minutes' => 5],
                ['student_id' => $second->id, 'status' => 'excused_absence'],
            ],
        ])->assertOk();

        $this->assertSame(2, Attendance::query()
            ->whereDate('attendance_date', $date)
            ->count());

        $this->assertDatabaseHas('attendances', [
            'student_id' => $s['student']->id,
            'status' => 'late',
            'late_minutes' => 5,
        ]);
    }

    public function test_attendance_before_enrollment_is_rejected(): void
    {
        $s = $this->scenario('2026-06-01');

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/attendance', [
            'student_id' => $s['student']->id,
            'attendance_date' => '2026-01-01',
            'status' => 'present',
        ])->assertStatus(422)->assertJsonValidationErrors('attendance_date');
    }

    public function test_future_attendance_is_rejected(): void
    {
        $s = $this->scenario();
        $tomorrow = now($s['center']->timezone)->addDay()->toDateString();

        $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/attendance', [
            'student_id' => $s['student']->id,
            'attendance_date' => $tomorrow,
            'status' => 'present',
        ])->assertStatus(422)->assertJsonValidationErrors('attendance_date');
    }

    public function test_a_student_without_a_group_cannot_be_recorded(): void
    {
        $center = Center::factory()->create();
        $manager = User::factory()->manager()->for($center)->create();
        $student = Student::factory()->for($center)->create(['enrolled_at' => '2026-01-01']);

        $this->withHeaders($this->auth($manager))->postJson('/api/v1/attendance', [
            'student_id' => $student->id,
            'attendance_date' => $this->today($center),
            'status' => 'present',
        ])->assertStatus(422)->assertJsonValidationErrors('student_id');
    }

    public function test_bulk_recording_rejects_a_cross_center_group(): void
    {
        $centerA = Center::factory()->create();
        $centerB = Center::factory()->create();
        $managerA = User::factory()->manager()->for($centerA)->create();
        $teacherB = User::factory()->teacher()->for($centerB)->create();
        $groupB = Group::factory()->for($centerB)->create(['teacher_id' => $teacherB->id]);
        $studentB = Student::factory()->for($centerB)->create(['enrolled_at' => '2026-01-01']);

        app(AddStudentToGroup::class)->execute($groupB, $teacherB, $studentB->id);

        $this->withHeaders($this->auth($managerA))->postJson('/api/v1/groups/'.$groupB->id.'/attendance', [
            'attendance_date' => $this->today($centerB),
            'records' => [
                ['student_id' => $studentB->id, 'status' => 'present'],
            ],
        ])->assertNotFound();
    }

    public function test_bulk_recording_rejects_a_student_not_in_the_group(): void
    {
        $s = $this->scenario();
        $outsider = Student::factory()->for($s['center'])->create(['enrolled_at' => '2026-01-01']);

        $this->withHeaders($this->auth($s['teacher']))->postJson('/api/v1/groups/'.$s['group']->id.'/attendance', [
            'attendance_date' => $this->today($s['center']),
            'records' => [
                ['student_id' => $outsider->id, 'status' => 'present'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('records');
    }
}
