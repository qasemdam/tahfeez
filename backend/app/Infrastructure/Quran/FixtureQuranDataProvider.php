<?php

namespace App\Infrastructure\Quran;

use App\Domain\Quran\Contracts\QuranDataProvider;
use App\Domain\Quran\Exceptions\QuranDataUnavailable;
use App\Domain\Quran\Support\AyahLocation;
use App\Domain\Quran\Support\PageMeasurement;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Development fixture. NOT canonical Quran data.
 *
 * It exists so the memorization domain can be built and tested while the real
 * dataset is blocked (OD-029). It deliberately uses different page heights
 * (8 lines on page 1, 15 on page 2) to prove the measurement engine reads
 * `lines_count` from data instead of dividing by a hard-coded 15.
 */
final class FixtureQuranDataProvider implements QuranDataProvider
{
    private const VERSION = 'fixture-dev-v1';

    private const VERSION_ID = 1;

    /** @var array<int, array{id:int, name_ar:string, ayah_count:int, page_start:int, page_end:int}> */
    private const SURAHS = [
        1 => ['id' => 1, 'name_ar' => 'الفاتحة', 'ayah_count' => 7, 'page_start' => 1, 'page_end' => 1],
        2 => ['id' => 2, 'name_ar' => 'البقرة', 'ayah_count' => 286, 'page_start' => 2, 'page_end' => 49],
        112 => ['id' => 112, 'name_ar' => 'الإخلاص', 'ayah_count' => 4, 'page_start' => 604, 'page_end' => 604],
        114 => ['id' => 114, 'name_ar' => 'الناس', 'ayah_count' => 6, 'page_start' => 604, 'page_end' => 604],
    ];

    /**
     * A deliberately partial map, keyed by global ayah id.
     *
     * @var array<int, array{surah:int, ayah:int, page_start:int, line_start:int, page_end:int, line_end:int}>
     */
    private const AYAHS = [
        1 => ['surah' => 1, 'ayah' => 1, 'page_start' => 1, 'line_start' => 1, 'page_end' => 1, 'line_end' => 1],
        2 => ['surah' => 1, 'ayah' => 2, 'page_start' => 1, 'line_start' => 2, 'page_end' => 1, 'line_end' => 2],
        3 => ['surah' => 1, 'ayah' => 3, 'page_start' => 1, 'line_start' => 3, 'page_end' => 1, 'line_end' => 3],
        4 => ['surah' => 1, 'ayah' => 4, 'page_start' => 1, 'line_start' => 4, 'page_end' => 1, 'line_end' => 4],
        5 => ['surah' => 1, 'ayah' => 5, 'page_start' => 1, 'line_start' => 5, 'page_end' => 1, 'line_end' => 5],
        6 => ['surah' => 1, 'ayah' => 6, 'page_start' => 1, 'line_start' => 6, 'page_end' => 1, 'line_end' => 6],
        7 => ['surah' => 1, 'ayah' => 7, 'page_start' => 1, 'line_start' => 7, 'page_end' => 1, 'line_end' => 8],
        8 => ['surah' => 2, 'ayah' => 1, 'page_start' => 2, 'line_start' => 1, 'page_end' => 2, 'line_end' => 1],
        9 => ['surah' => 2, 'ayah' => 2, 'page_start' => 2, 'line_start' => 2, 'page_end' => 2, 'line_end' => 2],
        10 => ['surah' => 2, 'ayah' => 3, 'page_start' => 2, 'line_start' => 3, 'page_end' => 2, 'line_end' => 5],
        11 => ['surah' => 2, 'ayah' => 4, 'page_start' => 2, 'line_start' => 6, 'page_end' => 2, 'line_end' => 8],
        12 => ['surah' => 2, 'ayah' => 5, 'page_start' => 2, 'line_start' => 9, 'page_end' => 2, 'line_end' => 11],
        13 => ['surah' => 2, 'ayah' => 6, 'page_start' => 2, 'line_start' => 12, 'page_end' => 2, 'line_end' => 15],
        14 => ['surah' => 2, 'ayah' => 7, 'page_start' => 2, 'line_start' => 15, 'page_end' => 3, 'line_end' => 2],
        6222 => ['surah' => 112, 'ayah' => 1, 'page_start' => 604, 'line_start' => 1, 'page_end' => 604, 'line_end' => 2],
        6223 => ['surah' => 112, 'ayah' => 2, 'page_start' => 604, 'line_start' => 3, 'page_end' => 604, 'line_end' => 3],
        6224 => ['surah' => 112, 'ayah' => 3, 'page_start' => 604, 'line_start' => 4, 'page_end' => 604, 'line_end' => 4],
        6225 => ['surah' => 112, 'ayah' => 4, 'page_start' => 604, 'line_start' => 5, 'page_end' => 604, 'line_end' => 5],
        6231 => ['surah' => 114, 'ayah' => 1, 'page_start' => 604, 'line_start' => 6, 'page_end' => 604, 'line_end' => 6],
        6232 => ['surah' => 114, 'ayah' => 2, 'page_start' => 604, 'line_start' => 7, 'page_end' => 604, 'line_end' => 7],
        6233 => ['surah' => 114, 'ayah' => 3, 'page_start' => 604, 'line_start' => 8, 'page_end' => 604, 'line_end' => 8],
        6234 => ['surah' => 114, 'ayah' => 4, 'page_start' => 604, 'line_start' => 9, 'page_end' => 604, 'line_end' => 9],
        6235 => ['surah' => 114, 'ayah' => 5, 'page_start' => 604, 'line_start' => 10, 'page_end' => 604, 'line_end' => 10],
        6236 => ['surah' => 114, 'ayah' => 6, 'page_start' => 604, 'line_start' => 11, 'page_end' => 604, 'line_end' => 11],
    ];

    /** @var array<int, int> */
    private const PAGE_LINES = [1 => 8, 2 => 15, 3 => 15, 604 => 15];

    public function surahs(): array
    {
        return array_values(self::SURAHS);
    }

    public function linesOnPage(int $page): int
    {
        return self::PAGE_LINES[$page]
            ?? throw new QuranDataUnavailable("Page {$page} is not in the fixture dataset.");
    }

    public function ayahLocation(int $surahId, int $ayahNumber): AyahLocation
    {
        $surah = self::SURAHS[$surahId]
            ?? throw new QuranDataUnavailable("Surah {$surahId} is not in the fixture dataset.");

        if ($ayahNumber < 1 || $ayahNumber > $surah['ayah_count']) {
            throw new QuranDataUnavailable("Ayah {$ayahNumber} is outside surah {$surahId}.");
        }

        foreach (self::AYAHS as $ayahId => $row) {
            if ($row['surah'] === $surahId && $row['ayah'] === $ayahNumber) {
                return new AyahLocation(
                    surahId: $surahId,
                    ayahNumber: $ayahNumber,
                    ayahId: $ayahId,
                    pageStart: $row['page_start'],
                    lineStart: $row['line_start'],
                    pageEnd: $row['page_end'],
                    lineEnd: $row['line_end'],
                );
            }
        }

        throw new QuranDataUnavailable(
            "Ayah {$surahId}:{$ayahNumber} is not mapped in the fixture dataset."
        );
    }

    public function measure(int $fromAyahId, int $toAyahId): PageMeasurement
    {
        if ($fromAyahId > $toAyahId) {
            throw new QuranDataUnavailable('The start ayah must not come after the end ayah.');
        }

        $from = self::AYAHS[$fromAyahId]
            ?? throw new QuranDataUnavailable("Ayah id {$fromAyahId} is not in the fixture dataset.");
        $to = self::AYAHS[$toAyahId]
            ?? throw new QuranDataUnavailable("Ayah id {$toAyahId} is not in the fixture dataset.");

        $lines = 0;
        $pages = BigDecimal::zero();

        for ($page = $from['page_start']; $page <= $to['page_end']; $page++) {
            $height = $this->linesOnPage($page);
            $first = $page === $from['page_start'] ? $from['line_start'] : 1;
            $last = $page === $to['page_end'] ? $to['line_end'] : $height;
            $covered = max(0, $last - $first + 1);

            $lines += $covered;
            $pages = $pages->plus(
                BigDecimal::of($covered)->dividedBy($height, 6, RoundingMode::HALF_UP)
            );
        }

        return new PageMeasurement(
            fromAyahId: $fromAyahId,
            toAyahId: $toAyahId,
            amountLines: $lines,
            amountPages: $pages->toScale(6)->__toString(),
            pageStart: $from['page_start'],
            lineStart: $from['line_start'],
            pageEnd: $to['page_end'],
            lineEnd: $to['line_end'],
            datasetVersion: self::VERSION,
        );
    }

    public function datasetVersion(): string
    {
        return self::VERSION;
    }

    public function datasetVersionId(): int
    {
        return self::VERSION_ID;
    }
}
