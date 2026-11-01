<?php

namespace App\Domain\Evaluation\Models;

use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Immutable profile version. `rule_set` is the snapshot the weekly evaluation
 * embeds; changing it means inserting a new version, never editing this row.
 */
class EvaluationProfileVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'center_id',
        'profile_id',
        'version_number',
        'required_pages',
        'effective_from',
        'rule_set',
        'created_by',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'required_pages' => 'decimal:2',
            'effective_from' => 'date',
            'rule_set' => 'array',
        ];
    }

    protected function effectiveFrom(): Attribute
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

    public function profile(): BelongsTo
    {
        return $this->belongsTo(EvaluationProfile::class, 'profile_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
