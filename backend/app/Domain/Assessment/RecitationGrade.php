<?php

namespace App\Domain\Assessment;

/**
 * The recitation grade catalogue (BR-REC-01). One definition shared by
 * memorization, review and recitation so the stored values cannot drift.
 */
final class RecitationGrade
{
    public const EXCELLENT = 'excellent';

    public const VERY_GOOD = 'very_good';

    public const GOOD = 'good';

    public const NEEDS_REVIEW = 'needs_review';

    /** @var array<int, string> */
    public const ALL = [
        self::EXCELLENT,
        self::VERY_GOOD,
        self::GOOD,
        self::NEEDS_REVIEW,
    ];
}
