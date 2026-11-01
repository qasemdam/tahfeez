<?php

namespace App\Domain\Reports\ValueObjects;

/**
 * A calendar month in the center's timezone.
 *
 * Unlike the evaluation week, a month is not configuration-driven: it always
 * starts on the 1st and ends on the last day of that month. `key` is `YYYY-MM`.
 */
final readonly class MonthPeriod
{
    public function __construct(
        public string $key,
        public string $start,
        public string $end,
    ) {}
}
