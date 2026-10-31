<?php

namespace App\Domain\Evaluation\Support;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;

/**
 * Decimal helpers. All evaluation arithmetic is decimal-string based; floats
 * never enter the engine (ADR-0003, docs/evaluation.md §8).
 */
final class Decimal
{
    public static function zero(): BigDecimal
    {
        return BigDecimal::zero();
    }

    public static function hundred(): BigDecimal
    {
        return BigDecimal::of('100');
    }

    /**
     * Applies an optional cap supplied by the rule set. No cap is implied.
     */
    public static function cap(BigDecimal $value, ?string $cap): BigDecimal
    {
        if ($cap === null) {
            return $value;
        }

        $capDecimal = BigDecimal::of($cap);

        return $value->isGreaterThan($capDecimal) ? $capDecimal : $value;
    }

    public static function isInScoreRange(string $value): bool
    {
        try {
            $decimal = BigDecimal::of($value);
        } catch (NumberFormatException) {
            return false;
        }

        return ! $decimal->isLessThan(self::zero())
            && ! $decimal->isGreaterThan(self::hundred());
    }
}
