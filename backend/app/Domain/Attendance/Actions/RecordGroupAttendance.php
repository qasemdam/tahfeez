<?php

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Models\Attendance;
use App\Domain\Groups\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a whole group's attendance for one day in a single transaction.
 * Re-submitting the same day is idempotent (one row per student per day).
 */
class RecordGroupAttendance
{
    public function __construct(private readonly RecordAttendance $record) {}

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, Attendance>
     */
    public function execute(Group $group, User $actor, string $date, array $records): array
    {
        $today = now($actor->center->timezone)->toDateString();

        if ($date > $today) {
            throw ValidationException::withMessages([
                'attendance_date' => ['لا يمكن تسجيل حضور بتاريخ مستقبلي.'],
            ]);
        }

        $memberIds = $group->activeStudents()->pluck('students.id')->all();

        return DB::transaction(function () use ($actor, $date, $records, $memberIds): array {
            $saved = [];

            foreach ($records as $row) {
                $studentId = (int) $row['student_id'];

                if (! in_array($studentId, $memberIds, true)) {
                    throw ValidationException::withMessages([
                        'records' => ['أحد الطلاب ليس عضوًا في هذه الحلقة.'],
                    ]);
                }

                [$attendance] = $this->record->execute($actor, [
                    'student_id' => $studentId,
                    'attendance_date' => $date,
                    'status' => $row['status'],
                    'late_minutes' => $row['late_minutes'] ?? null,
                    'notes' => $row['notes'] ?? null,
                ]);

                $saved[] = $attendance;
            }

            return $saved;
        });
    }
}
