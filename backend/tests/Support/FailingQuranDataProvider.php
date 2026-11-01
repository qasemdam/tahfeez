<?php

namespace Tests\Support;

use App\Domain\Quran\Contracts\QuranDataProvider;
use App\Domain\Quran\Exceptions\QuranDataUnavailable;
use App\Domain\Quran\Support\AyahLocation;
use App\Domain\Quran\Support\PageMeasurement;

/**
 * A provider that cannot answer anything; used to prove CALCULATED records
 * fail clearly instead of guessing an amount.
 */
final class FailingQuranDataProvider implements QuranDataProvider
{
    public function surahs(): array
    {
        return [];
    }

    public function linesOnPage(int $page): int
    {
        throw new QuranDataUnavailable('No Quran dataset is active.');
    }

    public function ayahLocation(int $surahId, int $ayahNumber): AyahLocation
    {
        throw new QuranDataUnavailable('No Quran dataset is active.');
    }

    public function measure(int $fromAyahId, int $toAyahId): PageMeasurement
    {
        throw new QuranDataUnavailable('No Quran dataset is active.');
    }

    public function datasetVersion(): string
    {
        return 'none';
    }

    public function datasetVersionId(): int
    {
        return 0;
    }
}
