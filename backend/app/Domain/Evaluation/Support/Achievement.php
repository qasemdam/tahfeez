<?php

namespace App\Domain\Evaluation\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Achievement percentage for memorization / review progress.
 *
 * Rule: one page equals 100% of that page's requirement. The percentage is the
 * ratio of the amount actually accomplished to the amount required, and it is
 * UNCAPPED (BR-EVAL-02): a student who covers two required pages shows 200%,
 * never a capped 100%. We do not substitute "top student = 100%" — the number
 * is derived, never assigned.
 *
 * Amounts are decimal strings, never floats (ADR-0003 / ADR-0007).
 */
final class Achievement
{
    /**
     * @param  string  $actual  Accomplished amount, e.g. "1.5" pages.
     * @param  string  $required  Required amount, e.g. "1" page. Must be > 0.
     * @return string Percentage at 2 dp, e.g. "150.00".
     *
     * @throws InvalidArgumentException when the required amount is zero.
     */
    public static function percentage(string $actual, string $required): string
    {
        $requiredDecimal = BigDecimal::of($required);

        if ($requiredDecimal->isZero()) {
            throw new InvalidArgumentException('Required amount must be greater than zero.');
        }

        return BigDecimal::of($actual)
            ->dividedBy($requiredDecimal, 12, RoundingMode::HALF_UP)
            ->multipliedBy(100)
            ->toScale(2, RoundingMode::HALF_UP);
    }
}
