<?php

namespace App\Domain\Reports\Support;

use App\Domain\Evaluation\Enums\WeekStartDay;
use App\Domain\Evaluation\Services\WeekPeriodResolver;
use App\Domain\Evaluation\ValueObjects\WeekPeriod;
use App\Domain\Reports\ValueObjects\MonthPeriod;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Resolves the period a report covers.
 *
 * Shared by the JSON reports and their exports so a download can never resolve
 * a different week or month than the screen it was started from.
 */
final class ReportPeriodResolver
{
    /**
     * `week_start_date` is taken verbatim — it is what a historical report
     * needs once the center's start day has changed. Otherwise `week_key` is a
     * reference date inside the week and the center's start day resolves it.
     */
    public function week(
        ?string $weekKey,
        ?string $weekStartDate,
        WeekPeriodResolver $resolver,
        string $timezone,
        WeekStartDay $startDay,
    ): WeekPeriod {
        if ($weekStartDate !== null && $weekStartDate !== '') {
            $start = CarbonImmutable::createFromFormat('Y-m-d', $weekStartDate, $timezone)->startOfDay();

            return new WeekPeriod(
                start: $start->toDateString(),
                end: $start->addDays(6)->toDateString(),
                key: $start->toDateString(),
                // The boundary is verbatim, so the day it starts on is a fact
                // about the date — not the center's current setting.
                startDay: WeekStartDay::fromIsoWeekday((int) $start->format('N')),
            );
        }

        return $resolver->resolve(
            new DateTimeImmutable((string) $weekKey, new DateTimeZone($timezone)),
            $startDay,
        );
    }

    /**
     * A calendar month in the center's timezone. It never depends on
     * `weekly_evaluation_start_day`: a month is not an evaluation week.
     *
     * The `!` in the format resets every field the format does not carry. Without
     * it PHP fills the day from *today*, and on the 31st `2026-11` would overflow
     * to December — the report would silently return another month.
     */
    public function month(string $month, string $timezone): MonthPeriod
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, $timezone)->startOfMonth();

        return new MonthPeriod(
            key: $start->format('Y-m'),
            start: $start->toDateString(),
            end: $start->endOfMonth()->toDateString(),
        );
    }
}
