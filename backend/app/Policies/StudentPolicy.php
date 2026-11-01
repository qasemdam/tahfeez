<?php

namespace App\Policies;

use App\Domain\Students\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('students.view');
    }

    public function view(User $user, Student $student): bool
    {
        if ($student->center_id !== $user->center_id) {
            return false;
        }

        if ($user->isManager()) {
            return true;
        }

        return $student->assignments()
            ->whereNull('unassigned_at')
            ->where('teacher_id', $user->id)
            ->exists();
    }

    /**
     * Enrolling a student is a manager action (docs/permissions.md §3).
     */
    public function create(User $user): bool
    {
        return $user->isManager() && $user->hasPermission('students.create');
    }

    public function update(User $user, Student $student): bool
    {
        return $student->center_id === $user->center_id
            && $user->hasPermission('students.update');
    }

    public function delete(User $user, Student $student): bool
    {
        return $student->center_id === $user->center_id
            && $user->hasPermission('students.delete');
    }
}
