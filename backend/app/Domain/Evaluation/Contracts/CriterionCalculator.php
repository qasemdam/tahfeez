<?php

namespace App\Domain\Evaluation\Contracts;

use App\Domain\Evaluation\DTOs\CriterionResult;
use App\Domain\Evaluation\DTOs\EvaluationInput;
use App\Domain\Evaluation\Rules\EvaluationCriterion;

/**
 * A pluggable criterion calculator. Implementations must be pure: same
 * inputs and params produce the same result, with no clock, randomness, I/O
 * or AI (docs/evaluation.md §8).
 */
interface CriterionCalculator
{
    public function key(): string;

    public function calculate(EvaluationInput $input, EvaluationCriterion $criterion): CriterionResult;
}
