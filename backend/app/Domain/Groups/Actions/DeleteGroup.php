<?php

namespace App\Domain\Groups\Actions;

use App\Domain\Groups\Models\Group;
use App\Domain\Students\Models\StudentTeacherAssignment;
use Illuminate\Support\Facades\DB;

/**
 * Soft-deletes a group, closes its active memberships and closes only the
 * teacher assignments this group created. Manual assignments are untouched.
 */
class DeleteGroup
{
    public function execute(Group $group): void
    {
        DB::transaction(function () use ($group): void {
            $today = now()->toDateString();

            $group->activeMemberships()->update(['left_at' => $today]);

            StudentTeacherAssignment::query()
                ->where('group_id', $group->id)
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => $today]);

            $group->delete();
        });
    }
}
