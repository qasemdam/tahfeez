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
 * Attendance is opt-in (BR-ATT-04, docs/evaluation.md §7). The status scores
 * and the denominator flag are rule-set data.
 *
 * Formula: sum(count(status) x score(status)) / denominator, where the
 * denominator is all records when `count_absences_in_denominator` is true,
 * and records excluding unexcused absences when it is false.
 */
final class AttendanceCalculator implements CriterionCalculator
{
    public function key(): string
    {
        return CriterionKey::ATTENDANCE;
    }

    public function calculate(EvaluationInput $input, EvaluationCriterion $criterion): CriterionResult
    {
        $attendance = $input->attendance;

        if ($attendance === null || $attendance->total() === 0) {
            return MissingData::result($criterion, 'No attendance records supplied.');
        }

        $scores = $criterion->params['status_scores'] ?? null;
        $countAbsences = $criterion->params['count_absences_in_denominator'] ?? null;

        if (! is_array($scores) || ! is_bool($countAbsences)) {
            return MissingData::blocked(
                $criterion,
                'status_scores and count_absences_in_denominator are required.',
            );
        }

        $counts = [
            'present' => $attendance->present,
            'late' => $attendance->late,
            'excused_absence' => $attendance->excusedAbsence,
            'unexcused_absence' => $attendance->unexcusedAbsence,
        ];

        try {
            $numerator = BigDecimal::zero();

            foreach ($counts as $status => $count) {
                if (! array_key_exists($status, $scores)) {
                    return MissingData::blocked(
                        $criterion,
                        "status_scores.{$status} is required.",
                    );
                }

                $score = BigDecimal::of((string) $scores[$status]);

                if (! Decimal::isInScoreRange($score->__toString())) {
                    return MissingData::blocked(
                        $criterion,
                        'Attendance scores must be between 0 and 100.',
                    );
                }

                $numerator = $numerator->plus(
                    BigDecimal::of((string) $count)->multipliedBy($score)
                );
            }

            $denominator = $countAbsences
                ? $attendance->total()
                : $attendance->total() - $attendance->unexcusedAbsence;

            if ($denominator === 0) {
                return CriterionResultBuilder::scored(
                    $criterion,
                    (string) $attendance->total(),
                    BigDecimal::zero(),
                    'No attended records; attendance contributes zero.',
                );
            }

            $normalized = $numerator->dividedBy(
                BigDecimal::of((string) $denominator),
                12,
                RoundingMode::HALF_UP,
            );
        } catch (NumberFormatException) {
            return MissingData::blocked(
                $criterion,
                'status_scores and attendance counts must be numeric.',
            );
        }

        return CriterionResultBuilder::scored(
            $criterion,
            (string) $attendance->total(),
            $normalized,
            'Weighted average of status scores; absences are in the denominator only when configured.',
        );
    }
}
