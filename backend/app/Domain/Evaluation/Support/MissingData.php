<?php

namespace App\Domain\Evaluation\Support;

use App\Domain\Evaluation\DTOs\CriterionResult;
use App\Domain\Evaluation\Rules\EvaluationCriterion;
use App\Domain\Evaluation\Rules\MissingDataPolicy;

/**
 * Turns "no data" into the configured policy's result (BR-EVL-12).
 */
final class MissingData
{
    public static function result(EvaluationCriterion $criterion, string $reason): CriterionResult
    {
        return match ($criterion->missingDataPolicy) {
            MissingDataPolicy::TreatAsZero => new CriterionResult(
                key: $criterion->key,
                weight: $criterion->weight,
                rawValue: null,
                normalized: '0.000000',
                weighted: '0.000000',
                included: true,
                blocked: false,
                explanation: $reason.' Treated as zero.',
            ),
            MissingDataPolicy::ExcludeAndRenormalize => new CriterionResult(
                key: $criterion->key,
                weight: $criterion->weight,
                rawValue: null,
                normalized: null,
                weighted: null,
                included: false,
                blocked: false,
                explanation: $reason.' Excluded; remaining weights are renormalised.',
            ),
            MissingDataPolicy::Block => new CriterionResult(
                key: $criterion->key,
                weight: $criterion->weight,
                rawValue: null,
                normalized: null,
                weighted: null,
                included: false,
                blocked: true,
                explanation: $reason.' Evaluation blocked.',
            ),
        };
    }

    /**
     * A misconfigured criterion (missing params, unknown calculator). Always
     * blocks, regardless of the missing-data policy: it is a rule-set error,
     * not absent data.
     */
    public static function blocked(EvaluationCriterion $criterion, string $reason): CriterionResult
    {
        return new CriterionResult(
            key: $criterion->key,
            weight: $criterion->weight,
            rawValue: null,
            normalized: null,
            weighted: null,
            included: false,
            blocked: true,
            explanation: $reason,
        );
    }
}
