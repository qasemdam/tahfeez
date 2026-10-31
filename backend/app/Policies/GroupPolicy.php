<?php

namespace App\Policies;

use App\Domain\Groups\Models\Group;
use App\Models\User;

/**
 * Group permissions (docs/permissions.md §3, extended for OD-030).
 *
 * Scope is structural: the controller resolves the group through
 * `Group::visibleTo()`, so an out-of-scope id is a 404 before this policy runs.
 * The policy then decides 403 for an in-scope group the actor may not modify.
 */
class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('groups.view');
    }

    public function view(User $user, Group $group): bool
    {
        return $group->center_id === $user->center_id
            && $user->hasPermission('groups.view')
            && ($user->isManager() || $group->teacher_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('groups.create');
    }

    public function update(User $user, Group $group): bool
    {
        return $group->center_id === $user->center_id
            && $user->hasPermission('groups.update')
            && ($user->isManager() || $group->teacher_id === $user->id);
    }

    public function delete(User $user, Group $group): bool
    {
        return $group->center_id === $user->center_id
            && $user->hasPermission('groups.delete')
            && ($user->isManager() || $group->teacher_id === $user->id);
    }

    public function manageStudents(User $user, Group $group): bool
    {
        return $group->center_id === $user->center_id
            && $user->hasPermission('groups.manage_students')
            && ($user->isManager() || $group->teacher_id === $user->id);
    }

    /**
     * Recording attendance for a group: a manager anywhere in the center, or
     * the owning teacher (attendance.record is a teacher default).
     */
    public function recordAttendance(User $user, Group $group): bool
    {
        return $group->center_id === $user->center_id
            && $user->hasPermission('attendance.record')
            && ($user->isManager() || $group->teacher_id === $user->id);
    }
}
