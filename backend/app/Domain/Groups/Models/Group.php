<?php

namespace App\Domain\Groups\Models;

use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A halaqah: one teacher, one center, a set of students.
 *
 * @property int $id
 * @property int $center_id
 * @property int $teacher_id
 * @property string $name
 * @property string $status
 */
class Group extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'center_id',
        'teacher_id',
        'name',
        'status',
        'notes',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMembership::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->hasMany(GroupMembership::class)->whereNull('left_at');
    }

    /**
     * Students currently in the group, with `joined_at` on the pivot.
     */
    public function activeStudents(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'group_memberships')
            ->withPivot(['joined_at', 'left_at'])
            ->wherePivotNull('left_at');
    }

    /**
     * A manager sees every group in the center; a teacher sees only their own
     * (BR-AUT-02 / BR-TEN-02). Applied in the query, not the controller.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->where('groups.center_id', $user->center_id);

        if ($user->isTeacher()) {
            $query->where('groups.teacher_id', $user->id);
        }

        return $query;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
