<?php

namespace App\Domain\Evaluation\Support;

use App\Domain\Evaluation\DTOs\CriterionResult;
use App\Domain\Evaluation\Rules\EvaluationCriterion;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Builds a scored criterion result. The weighted contribution is
 * `normalized x weight / 100`; the engine later divides the sum of these
 * contributions by the included weight fraction.
 */
final class CriterionResultBuilder
{
    public static function scored(
        EvaluationCriterion $criterion,
        ?string $rawValue,
        BigDecimal $normalized,
        string $explanation = 'Calculated.',
    ): CriterionResult {
        $normalized = $normalized->toScale(6, RoundingMode::HALF_UP);

        $weighted = $normalized
            ->multipliedBy(BigDecimal::of($criterion->weight))
            ->dividedBy(100, 6, RoundingMode::HALF_UP);

        return new CriterionResult(
            key: $criterion->key,
            weight: $criterion->weight,
            rawValue: $rawValue,
            normalized: $normalized->__toString(),
            weighted: $weighted->__toString(),
            included: true,
            blocked: false,
            explanation: $explanation,
        );
    }
}
