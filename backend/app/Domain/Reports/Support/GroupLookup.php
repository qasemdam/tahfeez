<?php

namespace App\Domain\Reports\Support;

use App\Domain\Groups\Models\Group;
use App\Domain\Groups\Models\GroupMembership;
use Illuminate\Support\Collection;

/**
 * The groups a report row can point at: the current memberships plus any group
 * an evaluation froze. A group the student has since left is still readable
 * (soft-deleted groups and teachers included), because it is part of history.
 */
final class GroupLookup
{
    /**
     * @param  Collection<int, GroupMembership>  $memberships
     * @param  array<int, int|string>  $groupIds  ids referenced by stored evaluations
     * @return Collection<int, Group>
     */
    public static function resolve(Collection $memberships, array $groupIds): Collection
    {
        $groups = $memberships
            ->map(static fn (GroupMembership $membership): ?Group => $membership->group)
            ->filter()
            ->keyBy(static fn (Group $group): int => (int) $group->id);

        $missing = collect($groupIds)
            ->map(static fn (int|string $id): int => (int) $id)
            ->unique()
            ->reject(static fn (int $id): bool => $groups->has($id));

        if ($missing->isEmpty()) {
            return $groups;
        }

        $extra = Group::withTrashed()
            ->with(['teacher' => static fn ($query) => $query->withTrashed()])
            ->whereIn('id', $missing->all())
            ->get();

        foreach ($extra as $group) {
            $groups->put((int) $group->id, $group);
        }

        return $groups;
    }
}
