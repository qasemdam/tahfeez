<?php

namespace App\Policies;

use App\Domain\Evaluation\Models\WeeklyEvaluation;
use App\Models\User;

/**
 * Weekly evaluation permissions (docs/permissions.md §3).
 *
 * Scope is structural: the controller resolves through
 * `WeeklyEvaluation::visibleTo()`, so an out-of-scope id is 404 before this
 * policy runs.
 */
class WeeklyEvaluationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('evaluation.view');
    }

    public function view(User $user, WeeklyEvaluation $evaluation): bool
    {
        return $evaluation->center_id === $user->center_id
            && $user->hasPermission('evaluation.view')
            && ($user->isManager()
                || $evaluation->teacher_id === $user->id
                || $evaluation->group?->teacher_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('evaluation.compute');
    }

    public function update(User $user, WeeklyEvaluation $evaluation): bool
    {
        return $evaluation->center_id === $user->center_id
            && $user->hasPermission('evaluation.approve')
            && ($user->isManager() || $evaluation->group?->teacher_id === $user->id);
    }
}
