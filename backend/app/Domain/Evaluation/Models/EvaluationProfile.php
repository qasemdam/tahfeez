<?php

namespace App\Domain\Evaluation\Models;

use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student's stable evaluation profile; its versions carry the requirement
 * and rule-set snapshot.
 */
class EvaluationProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'center_id',
        'student_id',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(EvaluationProfileVersion::class, 'profile_id');
    }
}
