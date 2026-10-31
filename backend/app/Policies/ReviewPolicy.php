<?php

namespace App\Policies;

use App\Domain\Review\Models\Review;
use App\Models\User;

/**
 * Review permissions (docs/permissions.md §3, OD-035).
 *
 * Scope is structural: the controller resolves through `Review::visibleTo()`,
 * so an out-of-scope id is 404 before this policy runs.
 */
class ReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('review.record');
    }

    public function view(User $user, Review $record): bool
    {
        return $record->center_id === $user->center_id
            && $user->hasPermission('review.record')
            && ($user->isManager()
                || $record->teacher_id === $user->id
                || $record->group?->teacher_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('review.record');
    }

    /**
     * Manager: any date. Teacher: only records they authored and only for the
     * center's current business day (the same rule as memorization).
     */
    public function update(User $user, Review $record): bool
    {
        if ($record->center_id !== $user->center_id) {
            return false;
        }

        if (! $user->hasPermission('review.update')) {
            return false;
        }

        if ($user->isManager()) {
            return true;
        }

        if ($record->teacher_id !== $user->id) {
            return false;
        }

        $timezone = $record->center?->timezone ?? config('app.timezone');

        return $record->review_date->toDateString()
            === now($timezone)->toDateString();
    }

    public function delete(User $user, Review $record): bool
    {
        if ($record->center_id !== $user->center_id) {
            return false;
        }

        if (! $user->hasPermission('review.delete')) {
            return false;
        }

        return $user->isManager() || $record->teacher_id === $user->id;
    }
}
