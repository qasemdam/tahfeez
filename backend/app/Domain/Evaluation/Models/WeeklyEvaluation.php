<?php

namespace App\Domain\Evaluation\Models;

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
 * A historical weekly evaluation. The scalar columns are for querying; the
 * snapshots are the source of truth for displaying it later. Reading this
 * model never consults the current profile, rule set or center setting.
 */
class WeeklyEvaluation extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_COMPUTED = 'computed';

    public const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'center_id',
        'student_id',
        'group_id',
        'teacher_id',
        'week_key',
        'week_start_date',
        'week_end_date',
        'weekly_evaluation_start_day',
        'evaluation_profile_version_id',
        'evaluation_profile_version',
        'template_version_id',
        'required_pages',
        'actual_pages',
        'achievement_percentage',
        'weighted_score',
        'is_complete',
        'status',
        'rules_snapshot',
        'result_snapshot',
        'client_uuid',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'week_key' => 'date',
            'week_start_date' => 'date',
            'week_end_date' => 'date',
            'required_pages' => 'decimal:2',
            'actual_pages' => 'decimal:4',
            'achievement_percentage' => 'decimal:2',
            'weighted_score' => 'decimal:2',
            'is_complete' => 'boolean',
            'rules_snapshot' => 'array',
            'result_snapshot' => 'array',
            'evaluation_profile_version' => 'integer',
            'template_version_id' => 'integer',
            'version' => 'integer',
        ];
    }

    protected function weekKey(): Attribute
    {
        return Attribute::make(
            set: static fn (mixed $value): ?string => $value === null
                ? null
                : Carbon::parse($value)->toDateString(),
        );
    }

    protected function weekStartDate(): Attribute
    {
        return Attribute::make(
            set: static fn (mixed $value): ?string => $value === null
                ? null
                : Carbon::parse($value)->toDateString(),
        );
    }

    protected function weekEndDate(): Attribute
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

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class)->withTrashed();
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id')->withTrashed();
    }

    public function profileVersion(): BelongsTo
    {
        return $this->belongsTo(EvaluationProfileVersion::class, 'evaluation_profile_version_id');
    }

    /**
     * Manager: the whole center. Teacher: evaluations whose historical group
     * they own or where they are the recorded teacher.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->where('weekly_evaluations.center_id', $user->center_id);

        if ($user->isTeacher()) {
            $query->where(function (Builder $q) use ($user): void {
                $q->where('weekly_evaluations.teacher_id', $user->id)
                    ->orWhereHas('group', function (Builder $g) use ($user): void {
                        $g->withTrashed()->where('teacher_id', $user->id);
                    });
            });
        }

        return $query;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
