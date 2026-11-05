<?php

namespace Tests\Support\Quran;

use App\Domain\Quran\Contracts\QuranDataProvider;
use App\Domain\Quran\Exceptions\QuranDataUnavailable;

/**
 * The behaviour every QuranDataProvider must exhibit, asserted against both the
 * development fixture and the database implementation.
 *
 * The cases use the shared synthetic shape both providers are seeded with:
 * surah 1 spans page 1 (8 lines), surah 2 starts on page 2 (15 lines), and
 * ayah 14 crosses from page 2 to page 3.
 */
trait QuranProviderContract
{
    private function assertProviderContract(QuranDataProvider $provider): void
    {
        $surahs = collect($provider->surahs())->keyBy('id');

        $this->assertArrayHasKey(1, $surahs->all());
        $this->assertSame(7, $surahs[1]['ayah_count']);
        $this->assertSame(1, $surahs[1]['page_start']);
        $this->assertSame(1, $surahs[1]['page_end']);
        $this->assertNotSame('', (string) $surahs[1]['name_ar']);

        // Page height is data: the opening pages are shorter than the rest.
        $this->assertSame(8, $provider->linesOnPage(1));
        $this->assertSame(15, $provider->linesOnPage(2));

        $first = $provider->ayahLocation(1, 1);
        $this->assertSame(1, $first->ayahId);
        $this->assertSame(1, $first->pageStart);
        $this->assertSame(1, $first->lineStart);
        $this->assertSame(1, $first->pageEnd);
        $this->assertSame(1, $first->lineEnd);

        // The last ayah of the surah ends at the bottom of its page.
        $last = $provider->ayahLocation(1, 7);
        $this->assertSame(7, $last->ayahId);
        $this->assertSame(1, $last->pageStart);
        $this->assertSame(7, $last->lineStart);
        $this->assertSame(1, $last->pageEnd);
        $this->assertSame(8, $last->lineEnd);

        // Ayah 7 occupies lines 7 and 8, so 1…7 covers the whole 8-line page.
        $onePage = $provider->measure(1, 7);
        $this->assertSame(8, $onePage->amountLines);
        $this->assertSame('1.000000', $onePage->amountPages);
        $this->assertSame(1, $onePage->pageStart);
        $this->assertSame(1, $onePage->pageEnd);
        $this->assertSame(8, $onePage->lineEnd);

        // A range crossing from the 8-line page into a 15-line page.
        $twoPages = $provider->measure(1, 8);
        $this->assertSame(9, $twoPages->amountLines);
        $this->assertSame('1.066667', $twoPages->amountPages);
        $this->assertSame(2, $twoPages->pageEnd);

        // A range that starts mid-page and ends on the next one.
        $across = $provider->measure(8, 14);
        $this->assertSame(17, $across->amountLines);
        $this->assertSame('1.133333', $across->amountPages);
        $this->assertSame(2, $across->pageStart);
        $this->assertSame(3, $across->pageEnd);

        // Decimal strings, six places, never floats.
        $this->assertMatchesRegularExpression('/^\d+\.\d{6}$/', $onePage->amountPages);

        $this->assertNotSame('', $provider->datasetVersion());
        $this->assertGreaterThan(0, $provider->datasetVersionId());

        $this->assertSame($provider->datasetVersion(), $onePage->datasetVersion);
    }

    private function assertRejectsReversedRange(QuranDataProvider $provider): void
    {
        $this->expectException(QuranDataUnavailable::class);

        $provider->measure(8, 1);
    }
}
