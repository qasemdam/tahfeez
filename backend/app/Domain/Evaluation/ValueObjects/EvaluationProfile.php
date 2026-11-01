<?php

namespace App\Domain\Evaluation\ValueObjects;

/**
 * The versioned per-student profile effective for the evaluated period
 * (BR-EVL-09). The engine only consumes it; resolving the effective version
 * for a week is the weekly-evaluation slice's job.
 */
final readonly class EvaluationProfile
{
    public function __construct(
        public int $version,
        public string $effectiveFrom,
        public string $requiredPages,
        public ?int $templateVersionId = null,
    ) {}
}
