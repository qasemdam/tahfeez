<?php

namespace App\Domain\Evaluation\Services;

use App\Domain\Evaluation\Enums\WeekStartDay;
use App\Domain\Evaluation\ValueObjects\WeekPeriod;
use DateTimeImmutable;

/**
 * Resolves a week from a reference date and the center's configured start
 * day. Deterministic: the caller passes the date (already in the center's
 * timezone); the resolver never reads the clock.
 */
final class WeekPeriodResolver
{
    public function resolve(DateTimeImmutable $reference, WeekStartDay $startDay): WeekPeriod
    {
        $reference = $reference->setTime(0, 0);
        $current = (int) $reference->format('N');
        $difference = ($current - $startDay->isoWeekday() + 7) % 7;

        $start = $reference->modify("-{$difference} days");
        $end = $start->modify('+6 days');

        return new WeekPeriod(
            start: $start->format('Y-m-d'),
            end: $end->format('Y-m-d'),
            key: $start->format('Y-m-d'),
            startDay: $startDay,
        );
    }
}
