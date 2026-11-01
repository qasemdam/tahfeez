<?php

namespace App\Domain\Evaluation\Rules;

/**
 * An immutable template-version rule set. Weights must total exactly 100.00
 * (BR-EVL-06); the engine validates that before calculating.
 */
final readonly class EvaluationRuleSet
{
    /**
     * @param  array<int, EvaluationCriterion>  $criteria
     */
    public function __construct(
        public string $version,
        public array $criteria,
    ) {}
}
