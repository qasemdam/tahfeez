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

    /**
     * Exporting is a separate grant (`reports.export`): reading a report on
     * screen and taking a file of it out of the center are different actions,
     * and a teacher only gets the latter when the manager enables it.
     */
    public function export(User $user): bool
    {
        return $user->hasPermission('reports.export');
    }

    public function view(User $user, WeeklyReport $report): bool
    {
        return $this->viewAny($user);
    }
}
