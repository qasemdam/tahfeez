<?php

namespace App\Domain\Reports\Support;

use App\Domain\Groups\Models\Group;
use App\Domain\Groups\Models\GroupMembership;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The student rows a report may contain: an active membership in a group the
 * actor can see, optionally narrowed by a group and/or student filter.
 *
 * Scope is structural (BR-AUT-02 / BR-TEN-02): a teacher only resolves their
 * own groups, so an out-of-scope filter yields no rows rather than a 403.
 */
final class StudentScope
{
    /**
     * @return Collection<int, GroupMembership>
     */
    public static function memberships(
        User $actor,
        Center $center,
        ?int $groupId = null,
        ?int $studentId = null,
    ): Collection {
        $groupIds = Group::query()
            ->visibleTo($actor)
            ->where('groups.center_id', $center->id)
            ->when($groupId !== null, static fn ($query) => $query->whereKey($groupId))
            ->pluck('id');

        if ($groupIds->isEmpty()) {
            return collect();
        }

        return GroupMembership::query()
            ->where('center_id', $center->id)
            ->whereNull('left_at')
            ->whereIn('group_id', $groupIds)
            ->when($studentId !== null, static fn ($query) => $query->where('student_id', $studentId))
            ->whereHas('student')
            ->with([
                'student:id,full_name',
                'group:id,name,teacher_id',
                'group.teacher:id,name',
            ])
            ->orderBy('student_id')
            ->orderBy('id')
            ->get()
            // A student with two active memberships appears once, on the first.
            ->unique('student_id')
            ->values();
    }
}
