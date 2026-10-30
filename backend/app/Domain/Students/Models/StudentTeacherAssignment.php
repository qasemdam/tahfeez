<?php

namespace App\Domain\Students\Models;

use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Assignment history row. `unassigned_at = null` means "currently assigned".
 */
class StudentTeacherAssignment extends Model
{
    protected $fillable = [
        'center_id',
        'student_id',
        'teacher_id',
        'assigned_at',
        'unassigned_at',
        'assigned_by',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'date',
            'unassigned_at' => 'date',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
