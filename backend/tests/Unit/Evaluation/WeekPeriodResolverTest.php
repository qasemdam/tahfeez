<?php

namespace Tests\Unit\Evaluation;

use App\Domain\Evaluation\Enums\WeekStartDay;
use App\Domain\Evaluation\Services\WeekPeriodResolver;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WeekPeriodResolverTest extends TestCase
{
    #[DataProvider('startDays')]
    public function test_each_start_day_resolves_the_week(
        WeekStartDay $startDay,
        string $expectedStart,
        string $expectedEnd,
    ): void {
        $period = (new WeekPeriodResolver)->resolve(
            new DateTimeImmutable('2026-10-31', new DateTimeZone('Asia/Riyadh')),
            $startDay,
        );

        $this->assertSame($expectedStart, $period->start);
        $this->assertSame($expectedEnd, $period->end);
        $this->assertSame($expectedStart, $period->key);
        $this->assertSame($startDay, $period->startDay);
    }

    /**
     * @return array<string, array{0: WeekStartDay, 1: string, 2: string}>
     */
    public static function startDays(): array
    {
        return [
            'saturday' => [WeekStartDay::Saturday, '2026-10-31', '2026-11-06'],
            'sunday' => [WeekStartDay::Sunday, '2026-10-25', '2026-10-31'],
            'monday' => [WeekStartDay::Monday, '2026-10-26', '2026-11-01'],
            'tuesday' => [WeekStartDay::Tuesday, '2026-10-27', '2026-11-02'],
            'wednesday' => [WeekStartDay::Wednesday, '2026-10-28', '2026-11-03'],
            'thursday' => [WeekStartDay::Thursday, '2026-10-29', '2026-11-04'],
            'friday' => [WeekStartDay::Friday, '2026-10-30', '2026-11-05'],
        ];
    }

    #[DataProvider('daysOfWeek')]
    public function test_every_day_of_the_week_maps_to_the_same_start(string $date): void
    {
        $period = (new WeekPeriodResolver)->resolve(
            new DateTimeImmutable($date, new DateTimeZone('Asia/Riyadh')),
            WeekStartDay::Saturday,
        );

        $this->assertSame('2026-10-24', $period->start);
        $this->assertSame('2026-10-30', $period->end);
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function daysOfWeek(): array
    {
        return [
            ['2026-10-24'],
            ['2026-10-25'],
            ['2026-10-26'],
            ['2026-10-27'],
            ['2026-10-28'],
            ['2026-10-29'],
            ['2026-10-30'],
        ];
    }

    public function test_a_week_can_cross_a_month_boundary(): void
    {
        $period = (new WeekPeriodResolver)->resolve(
            new DateTimeImmutable('2026-11-01', new DateTimeZone('Asia/Riyadh')),
            WeekStartDay::Saturday,
        );

        $this->assertSame('2026-10-31', $period->start);
        $this->assertSame('2026-11-06', $period->end);
    }

    public function test_a_week_can_cross_a_year_boundary(): void
    {
        $period = (new WeekPeriodResolver)->resolve(
            new DateTimeImmutable('2027-01-01', new DateTimeZone('Asia/Riyadh')),
            WeekStartDay::Saturday,
        );

        $this->assertSame('2026-12-26', $period->start);
        $this->assertSame('2027-01-01', $period->end);
    }

    public function test_the_reference_timezone_is_preserved(): void
    {
        $period = (new WeekPeriodResolver)->resolve(
            new DateTimeImmutable('2026-10-31 23:30:00', new DateTimeZone('Asia/Riyadh')),
            WeekStartDay::Saturday,
        );

        $this->assertSame('2026-10-31', $period->start);
        $this->assertSame('2026-11-06', $period->end);
    }
}
