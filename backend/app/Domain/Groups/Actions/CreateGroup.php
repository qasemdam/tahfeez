<?php

namespace App\Domain\Groups\Actions;

use App\Domain\Groups\Models\Group;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Creates a group. The teacher must be an active teacher in the actor's center;
 * a cross-center or non-teacher id resolves to 404, never leaking existence.
 */
class CreateGroup
{
    public function execute(User $actor, array $data): Group
    {
        $teacher = $this->resolveTeacher($actor, (int) $data['teacher_id']);

        if ($actor->isTeacher() && $teacher->id !== $actor->id) {
            throw new AuthorizationException('A teacher may only create a group assigned to themselves.');
        }

        return Group::create([
            'center_id' => $actor->center_id,
            'teacher_id' => $teacher->id,
            'name' => $data['name'],
            'status' => $data['status'] ?? Group::STATUS_ACTIVE,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    private function resolveTeacher(User $actor, int $teacherId): User
    {
        return User::query()
            ->where('center_id', $actor->center_id)
            ->where('role', User::ROLE_TEACHER)
            ->where('is_active', true)
            ->findOrFail($teacherId);
    }
}
