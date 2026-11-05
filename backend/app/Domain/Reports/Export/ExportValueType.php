<?php

namespace App\Domain\Reports\Export;

/**
 * The kind of value a column carries.
 *
 * The type is the only thing that knows how a raw report value is presented;
 * renderers ask it for a number format instead of hard-coding one. Amounts stay
 * decimal strings all the way from the query to the column, so no rounding is
 * introduced by the export.
 */
enum ExportValueType: string
{
    case Text = 'text';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Percentage = 'percentage';

    /**
     * A weighted score is a 0–100 index, not a ratio: it is never shown with a
     * percent sign and never confused with an achievement percentage.
     */
    case Score = 'score';

    public function isNumeric(): bool
    {
        return $this !== self::Text;
    }

    /**
     * The Excel number format that keeps the source scale visible.
     */
    public function numberFormat(int $scale): string
    {
        if ($this === self::Integer) {
            return '0';
        }

        $decimals = str_repeat('0', max($scale, 0));

        return match ($this) {
            self::Percentage => '0.'.$decimals.'"%"',
            default => '0.'.$decimals,
        };
    }
}
