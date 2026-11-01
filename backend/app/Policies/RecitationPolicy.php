<?php

namespace App\Policies;

use App\Domain\Recitation\Models\Recitation;
use App\Models\User;

/**
 * Recitation permissions (docs/permissions.md §3, OD-036).
 *
 * Scope is structural: the controller resolves through
 * `Recitation::visibleTo()`, so an out-of-scope id is 404 before this policy
 * runs.
 */
class RecitationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('recitation.record');
    }

    public function view(User $user, Recitation $record): bool
    {
        return $record->center_id === $user->center_id
            && $user->hasPermission('recitation.record')
            && ($user->isManager()
                || $record->teacher_id === $user->id
                || $record->group?->teacher_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('recitation.record');
    }

    /**
     * Manager: any date. Teacher: only records they authored and only for the
     * center's current business day.
     */
    public function update(User $user, Recitation $record): bool
    {
        if ($record->center_id !== $user->center_id) {
            return false;
        }

        if (! $user->hasPermission('recitation.update')) {
            return false;
        }

        if ($user->isManager()) {
            return true;
        }

        if ($record->teacher_id !== $user->id) {
            return false;
        }

        $timezone = $record->center?->timezone ?? config('app.timezone');

        return $record->recitation_date->toDateString()
            === now($timezone)->toDateString();
    }

    public function delete(User $user, Recitation $record): bool
    {
        if ($record->center_id !== $user->center_id) {
            return false;
        }

        if (! $user->hasPermission('recitation.delete')) {
            return false;
        }

        return $user->isManager() || $record->teacher_id === $user->id;
    }
}
