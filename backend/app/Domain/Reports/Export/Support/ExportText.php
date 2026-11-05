<?php

namespace App\Domain\Reports\Export\Support;

/**
 * Bidi isolation for free text in the print view.
 *
 * A date or a decimal inside an Arabic paragraph is reordered by the bidi
 * algorithm: "2026-10-24" is drawn as "24-10-2026" unless the run is isolated.
 * The value is escaped first, so wrapping it cannot introduce markup.
 */
final class ExportText
{
    public static function isolate(?string $value): string
    {
        $escaped = e((string) $value);

        return preg_replace(
            '/([0-9][0-9\-.:\/]*)/u',
            '<span dir="ltr">$1</span>',
            $escaped,
        ) ?? $escaped;
    }
}
