<?php

namespace App\Domain\Groups\Actions;

use App\Domain\Groups\Models\Group;
use App\Domain\Groups\Models\GroupMembership;
use App\Domain\Students\Models\StudentTeacherAssignment;
use Illuminate\Support\Facades\DB;

/**
 * Removes a student from a group: closes the membership and only the teacher
 * assignment this group created. History is kept, never deleted.
 */
class RemoveStudentFromGroup
{
    public function execute(Group $group, int $studentId): void
    {
        DB::transaction(function () use ($group, $studentId): void {
            $membership = GroupMembership::query()
                ->where('group_id', $group->id)
                ->where('student_id', $studentId)
                ->whereNull('left_at')
                ->firstOrFail();

            $today = now()->toDateString();
            $membership->forceFill(['left_at' => $today])->save();

            StudentTeacherAssignment::query()
                ->where('student_id', $studentId)
                ->where('group_id', $group->id)
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => $today]);
        });
    }
}
