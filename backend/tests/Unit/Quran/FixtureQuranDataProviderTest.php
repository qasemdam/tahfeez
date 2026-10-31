<?php

namespace Tests\Unit\Quran;

use App\Domain\Quran\Exceptions\QuranDataUnavailable;
use App\Infrastructure\Quran\FixtureQuranDataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Proves the measurement engine reads page height from data and never divides
 * by a hard-coded 15 (BR-MEM-06 / BR-QRN-09).
 */
class FixtureQuranDataProviderTest extends TestCase
{
    public function test_page_height_is_data_not_a_constant(): void
    {
        $provider = new FixtureQuranDataProvider;

        $this->assertSame(8, $provider->linesOnPage(1));
        $this->assertSame(15, $provider->linesOnPage(2));
    }

    public function test_measures_a_full_short_page(): void
    {
        $provider = new FixtureQuranDataProvider;
        $start = $provider->ayahLocation(1, 1);
        $end = $provider->ayahLocation(1, 7);

        $measurement = $provider->measure($start->ayahId, $end->ayahId);

        $this->assertSame(8, $measurement->amountLines);
        $this->assertSame('1.000000', $measurement->amountPages);
        $this->assertSame(1, $measurement->pageStart);
        $this->assertSame(1, $measurement->pageEnd);
    }

    public function test_measures_a_partial_page(): void
    {
        $provider = new FixtureQuranDataProvider;
        $start = $provider->ayahLocation(2, 1);
        $end = $provider->ayahLocation(2, 5);

        $measurement = $provider->measure($start->ayahId, $end->ayahId);

        $this->assertSame(11, $measurement->amountLines);
        $this->assertSame('0.733333', $measurement->amountPages);
        $this->assertSame(2, $measurement->pageStart);
        $this->assertSame(2, $measurement->pageEnd);
    }

    public function test_measures_across_pages(): void
    {
        $provider = new FixtureQuranDataProvider;
        $start = $provider->ayahLocation(2, 1);
        $end = $provider->ayahLocation(2, 7);

        $measurement = $provider->measure($start->ayahId, $end->ayahId);

        $this->assertSame(17, $measurement->amountLines);
        $this->assertSame('1.133333', $measurement->amountPages);
        $this->assertSame(2, $measurement->pageStart);
        $this->assertSame(3, $measurement->pageEnd);
    }

    public function test_an_ayah_may_span_two_pages(): void
    {
        $provider = new FixtureQuranDataProvider;
        $ayah = $provider->ayahLocation(2, 7);

        $measurement = $provider->measure($ayah->ayahId, $ayah->ayahId);

        $this->assertSame(2, $measurement->pageStart);
        $this->assertSame(3, $measurement->pageEnd);
        $this->assertSame(3, $measurement->amountLines);
        $this->assertSame('0.200000', $measurement->amountPages);
    }

    public function test_unknown_surah_is_rejected(): void
    {
        $this->expectException(QuranDataUnavailable::class);

        (new FixtureQuranDataProvider)->ayahLocation(999, 1);
    }

    public function test_unmapped_ayah_is_rejected(): void
    {
        $this->expectException(QuranDataUnavailable::class);

        (new FixtureQuranDataProvider)->ayahLocation(2, 100);
    }

    public function test_dataset_version_is_labelled_as_a_fixture(): void
    {
        $provider = new FixtureQuranDataProvider;

        $this->assertSame('fixture-dev-v1', $provider->datasetVersion());
        $this->assertSame(1, $provider->datasetVersionId());
    }
}
