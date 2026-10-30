<?php

namespace App\Domain\Students\Actions;

use App\Domain\Students\Models\Student;
use App\Domain\Students\Models\StudentTeacherAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Assigns a student to a teacher, closing any previous active assignment.
 *
 * The invariant "at most one active assignment" (BR-STU-04) is enforced here
 * inside a transaction; PostgreSQL additionally enforces it with a partial
 * unique index.
 */
class AssignStudentToTeacher
{
    public function execute(Student $student, User $teacher, User $actor, ?string $reason = null): StudentTeacherAssignment
    {
        if ($teacher->center_id !== $student->center_id) {
            throw new RuntimeException('Teacher and student belong to different centers.');
        }

        if (! $teacher->isTeacher()) {
            throw new RuntimeException('The assignee is not a teacher.');
        }

        return DB::transaction(function () use ($student, $teacher, $actor, $reason): StudentTeacherAssignment {
            $student->assignments()
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => now()->toDateString()]);

            return $student->assignments()->create([
                'center_id' => $student->center_id,
                'teacher_id' => $teacher->id,
                'assigned_at' => now()->toDateString(),
                'assigned_by' => $actor->id,
                'reason' => $reason,
            ]);
        });
    }
}
