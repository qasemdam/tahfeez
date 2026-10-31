<?php

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Models\Attendance;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records one student's attendance for one day.
 *
 * The record is an upsert on `(center, student, date)`, so re-submitting the
 * same day updates the existing row instead of creating a duplicate
 * (BR-ATT-03 / BR-ATT-06). The group is captured at creation and never
 * overwritten, so a later transfer does not rewrite history.
 */
class RecordAttendance
{
    /**
     * @return array{0: Attendance, 1: bool} [record, created]
     */
    public function execute(User $actor, array $data): array
    {
        $student = Student::query()
            ->where('center_id', $actor->center_id)
            ->findOrFail((int) $data['student_id']);

        if (! $student->isActive()) {
            throw ValidationException::withMessages([
                'student_id' => ['لا يمكن تسجيل حضور طالب غير مفعّل.'],
            ]);
        }

        $membership = $student->activeGroupMembership()->with('group')->first();

        if ($membership === null || $membership->group === null) {
            throw ValidationException::withMessages([
                'student_id' => ['الطالب غير مرتبط بحلقة نشطة.'],
            ]);
        }

        $group = $membership->group;

        // A teacher may only record for a student in one of their groups.
        if ($actor->isTeacher() && $group->teacher_id !== $actor->id) {
            abort(404);
        }

        $date = (string) $data['attendance_date'];
        $this->assertDateAllowed($student, $date, $actor->center);

        $status = (string) $data['status'];
        $this->assertLateMinutes($status, $data['late_minutes'] ?? null);

        $lateMinutes = $status === Attendance::STATUS_LATE
            ? ($data['late_minutes'] ?? null)
            : null;

        // Offline idempotency: retrying the same operation returns the same row.
        if (! empty($data['client_uuid'])) {
            $existing = Attendance::query()
                ->where('center_id', $actor->center_id)
                ->where('client_uuid', $data['client_uuid'])
                ->first();

            if ($existing !== null) {
                return [$existing, false];
            }
        }

        return DB::transaction(function () use ($actor, $student, $group, $date, $status, $lateMinutes, $data): array {
            $attendance = Attendance::query()
                ->where('center_id', $actor->center_id)
                ->where('student_id', $student->id)
                ->whereDate('attendance_date', $date)
                ->first();

            $created = $attendance === null;

            if ($created) {
                $attendance = new Attendance([
                    'center_id' => $actor->center_id,
                    'student_id' => $student->id,
                    'group_id' => $group->id,
                    'teacher_id' => $actor->id,
                    'attendance_date' => $date,
                    'client_uuid' => $data['client_uuid'] ?? null,
                ]);
            }

            $attendance->fill([
                'status' => $status,
                'late_minutes' => $lateMinutes,
                'notes' => $data['notes'] ?? null,
            ]);

            $attendance->save();

            return [$attendance, $created];
        });
    }

    private function assertDateAllowed(Student $student, string $date, Center $center): void
    {
        $today = now($center->timezone)->toDateString();

        if ($date > $today) {
            throw ValidationException::withMessages([
                'attendance_date' => ['لا يمكن تسجيل حضور بتاريخ مستقبلي.'],
            ]);
        }

        if ($student->enrolled_at !== null && $date < $student->enrolled_at->toDateString()) {
            throw ValidationException::withMessages([
                'attendance_date' => ['لا يمكن تسجيل حضور قبل تاريخ تسجيل الطالب.'],
            ]);
        }
    }

    private function assertLateMinutes(string $status, mixed $lateMinutes): void
    {
        if ($status !== Attendance::STATUS_LATE && $lateMinutes !== null) {
            throw ValidationException::withMessages([
                'late_minutes' => ['دقائق التأخير تُسجّل فقط مع حالة "متأخر".'],
            ]);
        }

        if ($lateMinutes !== null && (int) $lateMinutes < 0) {
            throw ValidationException::withMessages([
                'late_minutes' => ['دقائق التأخير لا تكون سالبة.'],
            ]);
        }
    }
}
