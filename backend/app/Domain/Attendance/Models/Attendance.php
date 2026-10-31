<?php

namespace App\Domain\Attendance\Models;

use App\Domain\Groups\Models\Group;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A single day's attendance for one student (BR-ATT-01 … BR-ATT-06).
 *
 * `group_id` is the historical group at recording time. `student`, `group`
 * and `teacher` include soft-deleted rows so a deleted group or student does
 * not erase the meaning of an old record.
 */
class Attendance extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_PRESENT = 'present';

    public const STATUS_EXCUSED = 'excused_absence';

    public const STATUS_UNEXCUSED = 'unexcused_absence';

    public const STATUS_LATE = 'late';

    /** @var array<int, string> */
    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_EXCUSED,
        self::STATUS_UNEXCUSED,
        self::STATUS_LATE,
    ];

    protected $fillable = [
        'center_id',
        'student_id',
        'group_id',
        'teacher_id',
        'attendance_date',
        'status',
        'late_minutes',
        'notes',
        'client_uuid',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'late_minutes' => 'integer',
            'version' => 'integer',
        ];
    }

    /**
     * Store the business date as `Y-m-d`, never `Y-m-d H:i:s`. A date column
     * holding a time component makes SQLite comparisons and exports confusing.
     */
    protected function attendanceDate(): Attribute
    {
        return Attribute::make(
            set: static fn (mixed $value): ?string => $value === null
                ? null
                : Carbon::parse($value)->toDateString(),
        );
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * Historical group; includes a soft-deleted group so old records stay
     * readable after the group is deactivated or removed.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class)->withTrashed();
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id')->withTrashed();
    }

    /**
     * Manager: the whole center. Teacher: records whose **historical** group
     * they own (including soft-deleted groups).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->where('attendances.center_id', $user->center_id);

        if ($user->isTeacher()) {
            $query->whereHas('group', function (Builder $q) use ($user): void {
                $q->withTrashed()->where('teacher_id', $user->id);
            });
        }

        return $query;
    }

    public function isLate(): bool
    {
        return $this->status === self::STATUS_LATE;
    }
}
