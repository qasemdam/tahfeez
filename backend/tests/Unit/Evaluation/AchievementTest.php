<?php

namespace Tests\Unit\Evaluation;

use App\Domain\Evaluation\Support\Achievement;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The achievement percentage is actual / required x 100, UNCAPPED.
 *
 * This is the Phase 0 decision under test: one page equals 100%, and a student
 * who covers more than the requirement shows more than 100%. We never clamp to
 * 100% and never substitute "top student = 100%".
 */
class AchievementTest extends TestCase
{
    #[DataProvider('percentages')]
    public function test_achievement_percentage_is_uncapped(
        string $actual,
        string $required,
        string $expected,
    ): void {
        $this->assertSame($expected, Achievement::percentage($actual, $required));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function percentages(): array
    {
        return [
            'quarter page' => ['0.25', '1', '25.00'],
            'half page' => ['0.5', '1', '50.00'],
            'exactly one page' => ['1', '1', '100.00'],
            'page and a half' => ['1.5', '1', '150.00'],
            'two pages' => ['2', '1', '200.00'],
            'three of two' => ['3', '2', '150.00'],
        ];
    }

    public function test_zero_required_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Achievement::percentage('1', '0');
    }
}
