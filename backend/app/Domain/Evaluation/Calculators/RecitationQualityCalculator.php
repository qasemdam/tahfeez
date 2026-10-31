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
use Brick\Math\RoundingMode;

/**
 * Aggregates pre-computed recitation scores (0..100). The grade-to-score
 * mapping belongs to the recitation rule set (docs/evaluation.md §4), so the
 * engine never invents one; the caller supplies the scores.
 */
final class RecitationQualityCalculator implements CriterionCalculator
{
    public function key(): string
    {
        return CriterionKey::RECITATION_QUALITY;
    }

    public function calculate(EvaluationInput $input, EvaluationCriterion $criterion): CriterionResult
    {
        $recitation = $input->recitation;

        if ($recitation === null || $recitation->scores === []) {
            return MissingData::result($criterion, 'No recitation scores supplied.');
        }

        $aggregate = $criterion->params['aggregate'] ?? null;

        if (! is_string($aggregate)
            || ! in_array($aggregate, ['mean', 'min', 'latest'], true)) {
            return MissingData::blocked(
                $criterion,
                'aggregate must be mean, min or latest.',
            );
        }

        $scores = [];

        foreach ($recitation->scores as $score) {
            if (! Decimal::isInScoreRange((string) $score)) {
                return MissingData::blocked(
                    $criterion,
                    'Recitation scores must be between 0 and 100.',
                );
            }

            $scores[] = BigDecimal::of((string) $score);
        }

        $value = match ($aggregate) {
            'mean' => $this->mean($scores),
            'min' => $this->minimum($scores),
            'latest' => $scores[count($scores) - 1],
        };

        return CriterionResultBuilder::scored(
            $criterion,
            (string) count($scores),
            $value,
            "Aggregate ({$aggregate}) of ".count($scores).' recitation scores.',
        );
    }

    /**
     * @param  array<int, BigDecimal>  $scores
     */
    private function mean(array $scores): BigDecimal
    {
        $sum = BigDecimal::zero();

        foreach ($scores as $score) {
            $sum = $sum->plus($score);
        }

        return $sum->dividedBy(count($scores), 12, RoundingMode::HALF_UP);
    }

    /**
     * @param  array<int, BigDecimal>  $scores
     */
    private function minimum(array $scores): BigDecimal
    {
        $minimum = $scores[0];

        foreach ($scores as $score) {
            if ($score->isLessThan($minimum)) {
                $minimum = $score;
            }
        }

        return $minimum;
    }
}
