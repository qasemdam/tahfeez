<?php

namespace App\Policies;

use App\Domain\Reports\DTOs\WeeklyReport;
use App\Models\User;

/**
 * Report permissions (docs/permissions.md §3).
 *
 * Reports are read-only and carry no per-row policy: the row scope is applied
 * inside the query (`Group::visibleTo`), so a teacher never resolves another
 * teacher's group and a cross-center request yields no rows.
 */
class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('reports.view');
    }

    public function view(User $user, WeeklyReport $report): bool
    {
        return $this->viewAny($user);
    }
}
