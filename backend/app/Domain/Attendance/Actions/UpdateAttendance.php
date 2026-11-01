<?php

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Models\Attendance;
use Illuminate\Validation\ValidationException;

/**
 * Corrects an existing attendance record. The student, date and historical
 * group are immutable; only status, late minutes and notes change.
 */
class UpdateAttendance
{
    public function execute(Attendance $attendance, array $data): Attendance
    {
        $status = (string) ($data['status'] ?? $attendance->status);

        if ($status !== Attendance::STATUS_LATE
            && array_key_exists('late_minutes', $data)
            && $data['late_minutes'] !== null) {
            throw ValidationException::withMessages([
                'late_minutes' => ['دقائق التأخير تُسجّل فقط مع حالة "متأخر".'],
            ]);
        }

        $lateMinutes = $status === Attendance::STATUS_LATE
            ? ($data['late_minutes'] ?? $attendance->late_minutes)
            : null;

        $attendance->fill([
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $attendance->notes,
        ]);

        $attendance->save();

        return $attendance;
    }
}
