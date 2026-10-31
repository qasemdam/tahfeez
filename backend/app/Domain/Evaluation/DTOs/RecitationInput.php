<?php

namespace App\Domain\Evaluation\DTOs;

/**
 * Recitation data for the engine. `scores` are already normalised to 0..100
 * by the configured recitation rule set (docs/evaluation.md §4); the engine
 * aggregates them and never invents a grade-to-score formula.
 */
final readonly class RecitationInput
{
    /**
     * @param  array<int, string>  $scores  ordered by recitation date
     */
    public function __construct(
        public array $scores = [],
        public int $hifzErrors = 0,
        public int $tajweedErrors = 0,
        public int $readingErrors = 0,
    ) {}
}
