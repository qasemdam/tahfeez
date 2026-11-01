<?php

namespace App\Domain\Groups\Actions;

use App\Domain\Groups\Models\Group;
use App\Domain\Groups\Models\GroupMembership;
use App\Domain\Students\Actions\AssignStudentToTeacher;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Updates a group. Changing the teacher reassigns every active member to the
 * new teacher in the same transaction; the previous assignments stay as
 * history (BR-STU-04 / BR-AUT-05).
 */
class UpdateGroup
{
    public function __construct(private readonly AssignStudentToTeacher $assign) {}

    public function execute(Group $group, User $actor, array $data): Group
    {
        $newTeacher = null;

        if (array_key_exists('teacher_id', $data)) {
            $newTeacher = User::query()
                ->where('center_id', $actor->center_id)
                ->where('role', User::ROLE_TEACHER)
                ->where('is_active', true)
                ->findOrFail((int) $data['teacher_id']);

            if ($actor->isTeacher() && $newTeacher->id !== $actor->id) {
                throw new AuthorizationException('A teacher may not reassign a group.');
            }
        }

        return DB::transaction(function () use ($group, $actor, $data, $newTeacher): Group {
            $teacherChanged = $newTeacher !== null && $newTeacher->id !== $group->teacher_id;

            $group->fill(array_intersect_key($data, array_flip(['name', 'status', 'notes'])));

            if ($newTeacher !== null) {
                $group->teacher_id = $newTeacher->id;
            }

            $group->save();

            if ($teacherChanged) {
                $group->activeMemberships()
                    ->with('student')
                    ->get()
                    ->each(function (GroupMembership $membership) use ($newTeacher, $actor, $group): void {
                        if ($membership->student !== null) {
                            $this->assign->execute(
                                $membership->student,
                                $newTeacher,
                                $actor,
                                'group_teacher_changed',
                                $group->id,
                            );
                        }
                    });
            }

            return $group->refresh();
        });
    }
}
