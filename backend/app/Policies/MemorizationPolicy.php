<?php

namespace App\Policies;

use App\Domain\Memorization\Models\Memorization;
use App\Models\User;

/**
 * Memorization permissions (docs/permissions.md §3, OD-034).
 *
 * Scope is structural: the controller resolves through
 * `Memorization::visibleTo()`, so an out-of-scope id is 404 before this policy
 * runs. This policy decides 403 for an in-scope record.
 */
class MemorizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('memorization.record');
    }

    public function view(User $user, Memorization $record): bool
    {
        return $record->center_id === $user->center_id
            && $user->hasPermission('memorization.record')
            && ($user->isManager()
                || $record->teacher_id === $user->id
                || $record->group?->teacher_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('memorization.record');
    }

    /**
     * Manager: any date. Teacher: only records they authored and only for the
     * center's current business day (the documented "own records, same day").
     */
    public function update(User $user, Memorization $record): bool
    {
        if ($record->center_id !== $user->center_id) {
            return false;
        }

        if (! $user->hasPermission('memorization.update')) {
            return false;
        }

        if ($user->isManager()) {
            return true;
        }

        if ($record->teacher_id !== $user->id) {
            return false;
        }

        $timezone = $record->center?->timezone ?? config('app.timezone');

        return $record->memorization_date->toDateString()
            === now($timezone)->toDateString();
    }

    public function delete(User $user, Memorization $record): bool
    {
        if ($record->center_id !== $user->center_id) {
            return false;
        }

        if (! $user->hasPermission('memorization.delete')) {
            return false;
        }

        return $user->isManager() || $record->teacher_id === $user->id;
    }
}
