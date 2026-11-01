<?php

namespace App\Domain\Reports\Support;

use App\Domain\Groups\Models\Group;
use App\Domain\Groups\Models\GroupMembership;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
     * The shared predicate: reports hydrate it, the dashboard only needs ids.
     */
    public static function query(
        User $actor,
        Center $center,
        ?int $groupId = null,
        ?int $studentId = null,
    ): Builder {
        $groupIds = Group::query()
            ->visibleTo($actor)
            ->where('groups.center_id', $center->id)
            ->when($groupId !== null, static fn ($query) => $query->whereKey($groupId))
            ->pluck('id');

        return GroupMembership::query()
            ->where('center_id', $center->id)
            ->whereNull('left_at')
            // An empty scope must yield no rows, never every row.
            ->whereIn('group_id', $groupIds->all())
            ->when($studentId !== null, static fn ($query) => $query->where('student_id', $studentId))
            ->whereHas('student');
    }

    /**
     * @return Collection<int, GroupMembership>
     */
    public static function memberships(
        User $actor,
        Center $center,
        ?int $groupId = null,
        ?int $studentId = null,
    ): Collection {
        return self::query($actor, $center, $groupId, $studentId)
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

    /**
     * @return Collection<int, int>
     */
    public static function studentIds(
        User $actor,
        Center $center,
        ?int $groupId = null,
        ?int $studentId = null,
    ): Collection {
        return self::query($actor, $center, $groupId, $studentId)
            ->distinct()
            ->pluck('student_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values();
    }
}
