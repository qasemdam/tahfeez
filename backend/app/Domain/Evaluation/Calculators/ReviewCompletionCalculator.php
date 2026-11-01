<?php

namespace App\Domain\Evaluation\Calculators;

use App\Domain\Evaluation\Contracts\CriterionCalculator;
use App\Domain\Evaluation\DTOs\CriterionResult;
use App\Domain\Evaluation\DTOs\EvaluationInput;
use App\Domain\Evaluation\Rules\CriterionKey;
use App\Domain\Evaluation\Rules\EvaluationCriterion;
use App\Domain\Evaluation\Support\CriterionResultBuilder;
use App\Domain\Evaluation\Support\Decimal;
use App\Domain\Evaluation\Support\MissingData;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;

/**
 * Review completion: review pages / target pages x 100. The target is rule-set
 * data, never a hard-coded constant.
 */
final class ReviewCompletionCalculator implements CriterionCalculator
{
    public function key(): string
    {
        return CriterionKey::REVIEW_COMPLETION;
    }

    public function calculate(EvaluationInput $input, EvaluationCriterion $criterion): CriterionResult
    {
        $raw = $input->reviewPages;

        if ($raw === null) {
            return MissingData::result($criterion, 'No review pages supplied.');
        }

        $target = $criterion->params['target_pages'] ?? null;

        if ($target === null) {
            return MissingData::blocked($criterion, 'target_pages is required.');
        }

        try {
            $targetDecimal = BigDecimal::of((string) $target);
            $rawDecimal = BigDecimal::of($raw);
        } catch (NumberFormatException) {
            return MissingData::blocked($criterion, 'target_pages and review_pages must be numeric.');
        }

        if ($targetDecimal->isLessThanOrEqualTo(Decimal::zero())) {
            return MissingData::blocked($criterion, 'target_pages must be greater than zero.');
        }

        try {
            $normalized = $rawDecimal
                ->dividedBy($targetDecimal, 12, RoundingMode::HALF_UP)
                ->multipliedBy(100);
            $normalized = Decimal::cap($normalized, $criterion->params['cap'] ?? null);
        } catch (NumberFormatException) {
            return MissingData::blocked($criterion, 'cap must be numeric.');
        }

        return CriterionResultBuilder::scored(
            $criterion,
            $raw,
            $normalized,
            'review_pages / target_pages x 100.',
        );
    }
}
