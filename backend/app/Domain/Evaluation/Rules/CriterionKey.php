<?php

namespace App\Domain\Evaluation\Rules;

/**
 * The closed criterion catalogue (BR-EVL-07, docs/evaluation.md §3.2).
 * Adding a key requires an ADR; the engine does not invent criteria.
 */
final class CriterionKey
{
    public const MEMORIZATION_PAGES = 'memorization_pages';

    public const REVIEW_COMPLETION = 'review_completion';

    public const RECITATION_QUALITY = 'recitation_quality';

    public const ERROR_RATE = 'error_rate';

    public const ATTENDANCE = 'attendance';

    /** @var array<int, string> */
    public const ALL = [
        self::MEMORIZATION_PAGES,
        self::REVIEW_COMPLETION,
        self::RECITATION_QUALITY,
        self::ERROR_RATE,
        self::ATTENDANCE,
    ];
}
