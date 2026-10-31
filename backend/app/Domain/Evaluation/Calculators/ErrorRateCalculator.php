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

/**
 * Weighted error count mapped through a configured band table. Both the
 * per-type weights and the bands are rule-set data; no band or weight is
 * hard-coded.
 */
final class ErrorRateCalculator implements CriterionCalculator
{
    public function key(): string
    {
        return CriterionKey::ERROR_RATE;
    }

    public function calculate(EvaluationInput $input, EvaluationCriterion $criterion): CriterionResult
    {
        $recitation = $input->recitation;

        if ($recitation === null) {
            return MissingData::result($criterion, 'No recitation error data supplied.');
        }

        $weights = $criterion->params['weights_by_type'] ?? null;
        $bands = $criterion->params['bands'] ?? null;

        if (! is_array($weights) || ! is_array($bands) || $bands === []) {
            return MissingData::blocked(
                $criterion,
                'weights_by_type and bands are required.',
            );
        }

        try {
            $raw = BigDecimal::zero();

            $counts = [
                'hifz' => $recitation->hifzErrors,
                'tajweed' => $recitation->tajweedErrors,
                'reading' => $recitation->readingErrors,
            ];

            foreach ($counts as $type => $count) {
                $weight = $weights[$type] ?? '0';
                $raw = $raw->plus(
                    BigDecimal::of((string) $count)
                        ->multipliedBy(BigDecimal::of((string) $weight))
                );
            }

            usort(
                $bands,
                static fn (array $a, array $b): int => ((int) ($a['max'] ?? 0)) <=> ((int) ($b['max'] ?? 0)),
            );

            $score = null;

            foreach ($bands as $band) {
                if (! array_key_exists('max', $band) || ! array_key_exists('score', $band)) {
                    return MissingData::blocked($criterion, 'Each band needs max and score.');
                }

                if ($raw->isLessThanOrEqualTo(BigDecimal::of((string) $band['max']))) {
                    $score = BigDecimal::of((string) $band['score']);
                    break;
                }
            }

            if ($score === null) {
                return MissingData::blocked(
                    $criterion,
                    'No band covers the raw error count; add a catch-all band.',
                );
            }

            if (! Decimal::isInScoreRange($score->__toString())) {
                return MissingData::blocked($criterion, 'Band scores must be between 0 and 100.');
            }
        } catch (NumberFormatException) {
            return MissingData::blocked(
                $criterion,
                'weights_by_type, bands and error counts must be numeric.',
            );
        }

        return CriterionResultBuilder::scored(
            $criterion,
            $raw->__toString(),
            $score,
            'Weighted error count mapped through the configured bands.',
        );
    }
}
