<?php

namespace App\Domain\Evaluation\Rules;

/**
 * One criterion in a template version: a catalogue key, a weight, a
 * calculator id and the missing-data policy. `params` is calculator-specific
 * configuration; no weight or threshold is hard-coded in the engine.
 */
final readonly class EvaluationCriterion
{
    public function __construct(
        public string $key,
        public string $weight,
        public string $calculator,
        public MissingDataPolicy $missingDataPolicy,
        public array $params = [],
    ) {}
}
