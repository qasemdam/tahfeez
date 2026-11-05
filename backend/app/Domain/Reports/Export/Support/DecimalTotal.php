<?php

namespace App\Domain\Reports\Export\Support;

use Brick\Math\BigDecimal;

/**
 * Sums decimal strings for a totals row.
 *
 * Amounts are summed with decimal arithmetic, never with floats: a total that
 * drifts by 0.0001 would contradict the rows above it. A null value means "not
 * reported" and contributes nothing — it is not treated as zero.
 */
final class DecimalTotal
{
    /**
     * @param  iterable<int, string|null>  $values
     */
    public static function of(iterable $values, int $scale = 4): string
    {
        $sum = BigDecimal::zero();

        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $sum = $sum->plus(BigDecimal::of($value));
        }

        return (string) $sum->toScale($scale);
    }
}
