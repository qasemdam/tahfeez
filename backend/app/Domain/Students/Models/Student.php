<?php

namespace App\Domain\Students\Models;

use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $center_id
 * @property string $full_name
 * @property string $status
 */
class Student extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'center_id',
        'code',
        'full_name',
        'birth_date',
        'age_years',
        'grade_level',
        'phone',
        'guardian_name',
        'guardian_phone',
        'enrolled_at',
        'status',
        'notes',
        'client_uuid',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'enrolled_at' => 'date',
            'version' => 'integer',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StudentTeacherAssignment::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(StudentTeacherAssignment::class)
            ->whereNull('unassigned_at');
    }

    /**
     * Students a user may see: the whole center for a manager, only actively
     * assigned students for a teacher (BR-AUT-02).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->where('students.center_id', $user->center_id);

        if ($user->isTeacher()) {
            $query->whereHas('activeAssignment', function (Builder $q) use ($user): void {
                $q->where('teacher_id', $user->id);
            });
        }

        return $query;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
