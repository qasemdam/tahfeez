<?php

namespace App\Domain\Review\Models;

use App\Domain\Assessment\RecitationGrade;
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
 * A review (Murajaʿah) record. Independent from new memorization: it has its
 * own table, endpoints and reports, and never contributes to memorization
 * totals (BR-REV-01 / BR-REV-04).
 */
class Review extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const SOURCE_CALCULATED = 'CALCULATED';

    public const SOURCE_MANUAL = 'MANUAL';

    /** @var array<int, string> */
    public const SOURCES = [self::SOURCE_CALCULATED, self::SOURCE_MANUAL];

    /** @var array<int, string> */
    public const GRADES = RecitationGrade::ALL;

    protected $fillable = [
        'center_id',
        'student_id',
        'group_id',
        'teacher_id',
        'review_date',
        'surah_id',
        'from_ayah_id',
        'to_ayah_id',
        'from_ayah_number',
        'to_ayah_number',
        'from_page',
        'to_page',
        'amount_pages',
        'amount_lines',
        'amount_source',
        'quran_dataset_version_id',
        'quran_dataset_version',
        'grade',
        'hifz_errors',
        'tajweed_errors',
        'reading_errors',
        'notes',
        'client_uuid',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'review_date' => 'date',
            'amount_pages' => 'decimal:4',
            'amount_lines' => 'integer',
            'hifz_errors' => 'integer',
            'tajweed_errors' => 'integer',
            'reading_errors' => 'integer',
            'quran_dataset_version_id' => 'integer',
            'version' => 'integer',
        ];
    }

    protected function reviewDate(): Attribute
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
     * readable after the group is removed.
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
     * Manager: the whole center. Teacher: records they authored or that belong
     * to a group they own (including soft-deleted groups).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->where('reviews.center_id', $user->center_id);

        if ($user->isTeacher()) {
            $query->where(function (Builder $q) use ($user): void {
                $q->where('reviews.teacher_id', $user->id)
                    ->orWhereHas('group', function (Builder $g) use ($user): void {
                        $g->withTrashed()->where('teacher_id', $user->id);
                    });
            });
        }

        return $query;
    }

    public function isCalculated(): bool
    {
        return $this->amount_source === self::SOURCE_CALCULATED;
    }
}
