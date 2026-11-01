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
 * New-memorization volume: actual pages / required pages x 100. Uncapped
 * unless the rule set supplies a cap (BR-EVL-13).
 */
final class MemorizationPagesCalculator implements CriterionCalculator
{
    public function key(): string
    {
        return CriterionKey::MEMORIZATION_PAGES;
    }

    public function calculate(EvaluationInput $input, EvaluationCriterion $criterion): CriterionResult
    {
        $required = $criterion->params['required_pages']
            ?? $input->profile?->requiredPages
            ?? $input->requiredPages;

        try {
            $requiredDecimal = BigDecimal::of((string) $required);
            $actualDecimal = BigDecimal::of($input->actualPages);
        } catch (NumberFormatException) {
            return MissingData::blocked(
                $criterion,
                'required_pages and actual_pages must be numeric.',
            );
        }

        if ($requiredDecimal->isLessThanOrEqualTo(Decimal::zero())) {
            return MissingData::blocked($criterion, 'required_pages must be greater than zero.');
        }

        try {
            $normalized = $actualDecimal
                ->dividedBy($requiredDecimal, 12, RoundingMode::HALF_UP)
                ->multipliedBy(100);
            $normalized = Decimal::cap($normalized, $criterion->params['cap'] ?? null);
        } catch (NumberFormatException) {
            return MissingData::blocked($criterion, 'cap must be numeric.');
        }

        return CriterionResultBuilder::scored(
            $criterion,
            $input->actualPages,
            $normalized,
            'actual_pages / required_pages x 100 (uncapped unless a cap is configured).',
        );
    }
}
