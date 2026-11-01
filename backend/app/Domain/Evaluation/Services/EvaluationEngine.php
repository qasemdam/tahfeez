<?php

namespace App\Domain\Evaluation\Services;

use App\Domain\Evaluation\Calculators\AttendanceCalculator;
use App\Domain\Evaluation\Calculators\ErrorRateCalculator;
use App\Domain\Evaluation\Calculators\MemorizationPagesCalculator;
use App\Domain\Evaluation\Calculators\RecitationQualityCalculator;
use App\Domain\Evaluation\Calculators\ReviewCompletionCalculator;
use App\Domain\Evaluation\Contracts\CriterionCalculator;
use App\Domain\Evaluation\DTOs\CriterionResult;
use App\Domain\Evaluation\DTOs\EvaluationInput;
use App\Domain\Evaluation\DTOs\EvaluationResult;
use App\Domain\Evaluation\Rules\CriterionKey;
use App\Domain\Evaluation\Rules\EvaluationRuleSet;
use App\Domain\Evaluation\Support\Achievement;
use App\Domain\Evaluation\Support\Decimal;
use App\Domain\Evaluation\Support\MissingData;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Pure, deterministic evaluation engine.
 *
 * It always computes the uncapped achievement percentage; when a rule set is
 * supplied it also computes the weighted score (0..100). It never persists,
 * never reads the clock, never calls a network or an LLM, and never measures
 * Quran amounts (the caller supplies `actualPages`).
 */
final class EvaluationEngine
{
    /** @var array<string, CriterionCalculator> */
    private array $calculators = [];

    /**
     * @param  array<int, CriterionCalculator>|null  $calculators
     */
    public function __construct(?array $calculators = null)
    {
        $defaults = [
            new MemorizationPagesCalculator,
            new ReviewCompletionCalculator,
            new RecitationQualityCalculator,
            new ErrorRateCalculator,
            new AttendanceCalculator,
        ];

        foreach ($defaults as $calculator) {
            $this->register($calculator);
        }

        foreach ($calculators ?? [] as $calculator) {
            $this->register($calculator);
        }
    }

    public function register(CriterionCalculator $calculator): void
    {
        $this->calculators[$calculator->key()] = $calculator;
    }

    public function evaluate(EvaluationInput $input): EvaluationResult
    {
        $required = $input->profile?->requiredPages ?? $input->requiredPages;

        $this->assertPositive($required, 'required_pages');
        $this->assertNonNegative($input->actualPages, 'actual_pages');

        if ($input->reviewPages !== null) {
            $this->assertNonNegative($input->reviewPages, 'review_pages');
        }

        $this->assertRecitation($input);
        $this->assertAttendance($input);

        // Always computed, always uncapped (BR-EVL-13).
        $achievement = Achievement::percentage($input->actualPages, $required);

        if ($input->ruleSet === null) {
            return $this->result($input, $required, $achievement, null, true, [], []);
        }

        $this->assertRuleSet($input->ruleSet);

        $components = [];
        $blocked = [];
        $includedWeight = BigDecimal::zero();
        $weightedSum = BigDecimal::zero();

        foreach ($input->ruleSet->criteria as $criterion) {
            $calculator = $this->calculators[$criterion->calculator] ?? null;

            if ($calculator === null) {
                $components[] = MissingData::blocked(
                    $criterion,
                    "No calculator registered for '{$criterion->calculator}'.",
                );
                $blocked[] = $criterion->key;

                continue;
            }

            $result = $calculator->calculate($input, $criterion);
            $components[] = $result;

            if ($result->blocked) {
                $blocked[] = $criterion->key;

                continue;
            }

            if (! $result->included) {
                continue;
            }

            $includedWeight = $includedWeight->plus(BigDecimal::of($criterion->weight));
            $weightedSum = $weightedSum->plus(BigDecimal::of($result->weighted ?? '0'));
        }

        $isComplete = $blocked === [];
        $weightedScore = null;

        if ($isComplete && ! $includedWeight->isZero()) {
            // Σ(normalized x weight / 100) / Σ weight x 100, i.e. the
            // documented weighted average; excluded criteria renormalise.
            $weightedScore = $weightedSum
                ->dividedBy($includedWeight, 12, RoundingMode::HALF_UP)
                ->multipliedBy(100)
                ->toScale(2, RoundingMode::HALF_UP)
                ->__toString();

            // The aggregate is a quality scale, so it is clamped (BR-EVL-15).
            if (BigDecimal::of($weightedScore)->isGreaterThan(Decimal::hundred())) {
                $weightedScore = '100.00';
            }

            if (BigDecimal::of($weightedScore)->isLessThan(Decimal::zero())) {
                $weightedScore = '0.00';
            }
        }

        return $this->result(
            $input,
            $required,
            $achievement,
            $weightedScore,
            $isComplete,
            $components,
            $blocked,
        );
    }

    /**
     * @param  array<int, CriterionResult>  $components
     * @param  array<int, string>  $blocked
     */
    private function result(
        EvaluationInput $input,
        string $required,
        string $achievement,
        ?string $weightedScore,
        bool $isComplete,
        array $components,
        array $blocked,
    ): EvaluationResult {
        return new EvaluationResult(
            requiredPages: $required,
            actualPages: $input->actualPages,
            achievementPercentage: $achievement,
            weightedScore: $weightedScore,
            isComplete: $isComplete,
            components: $components,
            blockedCriteria: $blocked,
            profileVersion: $input->profile?->version,
            profileEffectiveFrom: $input->profile?->effectiveFrom,
            templateVersionId: $input->profile?->templateVersionId,
            ruleSetVersion: $input->ruleSet?->version,
        );
    }

    private function assertRuleSet(EvaluationRuleSet $ruleSet): void
    {
        if ($ruleSet->criteria === []) {
            throw new InvalidArgumentException('A rule set needs at least one criterion.');
        }

        $seen = [];
        $total = BigDecimal::zero();

        foreach ($ruleSet->criteria as $criterion) {
            if (! in_array($criterion->key, CriterionKey::ALL, true)) {
                throw new InvalidArgumentException(
                    "Unknown criterion key '{$criterion->key}'.",
                );
            }

            if (isset($seen[$criterion->key])) {
                throw new InvalidArgumentException(
                    "Duplicate criterion '{$criterion->key}'.",
                );
            }

            $seen[$criterion->key] = true;

            try {
                $weight = BigDecimal::of($criterion->weight);
            } catch (NumberFormatException) {
                throw new InvalidArgumentException(
                    "Weight for '{$criterion->key}' must be numeric.",
                );
            }

            if ($weight->isLessThanOrEqualTo(Decimal::zero())) {
                throw new InvalidArgumentException(
                    "Weight for '{$criterion->key}' must be greater than zero.",
                );
            }

            if ($weight->isGreaterThan(Decimal::hundred())) {
                throw new InvalidArgumentException(
                    "Weight for '{$criterion->key}' must not exceed 100.",
                );
            }

            $total = $total->plus($weight);
        }

        if ($total->toScale(2, RoundingMode::HALF_UP)->__toString() !== '100.00') {
            throw new InvalidArgumentException(
                'Criterion weights must total exactly 100.00.',
            );
        }
    }

    private function assertPositive(string $value, string $field): void
    {
        try {
            $decimal = BigDecimal::of($value);
        } catch (NumberFormatException) {
            throw new InvalidArgumentException("{$field} must be numeric.");
        }

        if ($decimal->isLessThanOrEqualTo(Decimal::zero())) {
            throw new InvalidArgumentException("{$field} must be greater than zero.");
        }
    }

    private function assertNonNegative(string $value, string $field): void
    {
        try {
            $decimal = BigDecimal::of($value);
        } catch (NumberFormatException) {
            throw new InvalidArgumentException("{$field} must be numeric.");
        }

        if ($decimal->isLessThan(Decimal::zero())) {
            throw new InvalidArgumentException("{$field} must not be negative.");
        }
    }

    private function assertRecitation(EvaluationInput $input): void
    {
        $recitation = $input->recitation;

        if ($recitation === null) {
            return;
        }

        foreach ([
            $recitation->hifzErrors,
            $recitation->tajweedErrors,
            $recitation->readingErrors,
        ] as $count) {
            if ($count < 0) {
                throw new InvalidArgumentException(
                    'Recitation error counts must not be negative.',
                );
            }
        }

        foreach ($recitation->scores as $score) {
            if (! Decimal::isInScoreRange((string) $score)) {
                throw new InvalidArgumentException(
                    'Recitation scores must be between 0 and 100.',
                );
            }
        }
    }

    private function assertAttendance(EvaluationInput $input): void
    {
        $attendance = $input->attendance;

        if ($attendance === null) {
            return;
        }

        if ($attendance->present < 0
            || $attendance->late < 0
            || $attendance->excusedAbsence < 0
            || $attendance->unexcusedAbsence < 0) {
            throw new InvalidArgumentException(
                'Attendance counts must not be negative.',
            );
        }
    }
}
