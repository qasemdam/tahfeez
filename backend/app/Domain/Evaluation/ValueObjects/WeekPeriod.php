<?php

namespace App\Domain\Evaluation\ValueObjects;

use App\Domain\Evaluation\Enums\WeekStartDay;

/**
 * A resolved evaluation week. Dates are center-local Gregorian `Y-m-d`
 * strings; `key` is the week start, used as the uniqueness key.
 */
final readonly class WeekPeriod
{
    public function __construct(
        public string $start,
        public string $end,
        public string $key,
        public WeekStartDay $startDay,
    ) {}
}
