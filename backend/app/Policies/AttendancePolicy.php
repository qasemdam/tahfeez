<?php

namespace App\Policies;

use App\Domain\Attendance\Models\Attendance;
use App\Models\User;

/**
 * Attendance permissions (docs/permissions.md §3, OD-033).
 *
 * Scope is structural: the controller resolves through `Attendance::visibleTo()`
 * or `Group::visibleTo()`, so an out-of-scope id is 404 before this policy runs.
 * This policy decides 403 for an in-scope record the actor may not change.
 */
class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance.view');
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $attendance->center_id === $user->center_id
            && $user->hasPermission('attendance.view')
            && ($user->isManager() || $attendance->group?->teacher_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('attendance.record');
    }

    /**
     * Manager: any date. Teacher: only their own group's record and only for
     * the center's current business day (OD-033).
     */
    public function update(User $user, Attendance $attendance): bool
    {
        if ($attendance->center_id !== $user->center_id) {
            return false;
        }

        if (! $user->hasPermission('attendance.update')) {
            return false;
        }

        if ($user->isManager()) {
            return true;
        }

        if ($attendance->group?->teacher_id !== $user->id) {
            return false;
        }

        $timezone = $attendance->center?->timezone ?? config('app.timezone');

        return $attendance->attendance_date->toDateString()
            === now($timezone)->toDateString();
    }
}
