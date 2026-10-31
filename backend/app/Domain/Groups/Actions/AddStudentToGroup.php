<?php

namespace App\Domain\Groups\Actions;

use App\Domain\Groups\Models\Group;
use App\Domain\Groups\Models\GroupMembership;
use App\Domain\Students\Actions\AssignStudentToTeacher;
use App\Domain\Students\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds a student to a group, or moves them from another group.
 *
 * Moving closes the previous membership (history kept) and reassigns the
 * student to the group's teacher. Adding a student who is already in the same
 * group is idempotent.
 */
class AddStudentToGroup
{
    public function __construct(private readonly AssignStudentToTeacher $assign) {}

    public function execute(Group $group, User $actor, int $studentId): GroupMembership
    {
        if (! $group->isActive()) {
            throw ValidationException::withMessages([
                'group' => ['لا يمكن إضافة طلاب إلى حلقة غير مفعّلة.'],
            ]);
        }

        $teacher = $group->teacher;

        if ($teacher === null || ! $teacher->is_active) {
            throw ValidationException::withMessages([
                'group' => ['معلم الحلقة غير متاح.'],
            ]);
        }

        // Cross-center ids resolve to 404 (BR-TEN-02).
        $student = Student::query()
            ->where('center_id', $actor->center_id)
            ->findOrFail($studentId);

        return DB::transaction(function () use ($group, $actor, $student, $teacher): GroupMembership {
            $active = $student->groupMemberships()->whereNull('left_at')->first();

            if ($active !== null && $active->group_id === $group->id) {
                return $active;
            }

            if ($active !== null) {
                $active->forceFill(['left_at' => now()->toDateString()])->save();
            }

            $membership = GroupMembership::create([
                'center_id' => $student->center_id,
                'group_id' => $group->id,
                'student_id' => $student->id,
                'joined_at' => now()->toDateString(),
                'assigned_by' => $actor->id,
            ]);

            $this->assign->execute(
                $student,
                $teacher,
                $actor,
                'group_membership',
                $group->id,
            );

            return $membership;
        });
    }
}
