<?php

namespace App\Infrastructure\Quran;

use App\Domain\Quran\Contracts\QuranDataProvider;
use App\Domain\Quran\Exceptions\QuranDataUnavailable;
use App\Domain\Quran\Support\AyahLocation;
use App\Domain\Quran\Support\PageMeasurement;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads the Quran master data from the quran_* tables (Phase 7, S2).
 *
 * Every query is scoped to one dataset version — the active, validated snapshot
 * — so two editions can live in the same database without ever being mixed.
 * There is no cross-version join and no fallback: if the snapshot is missing,
 * incomplete or unvalidated, the provider raises QuranDataUnavailable rather
 * than answering with something plausible.
 *
 * Page height is read from `quran_pages.lines_count`, never assumed to be 15 and
 * never derived from the lines the ayah rows happen to cover.
 */
final class DatabaseQuranDataProvider implements QuranDataProvider
{
    private ?object $active = null;

    /** @var array<int, array{id:int, name_ar:string, ayah_count:int, page_start:int, page_end:int}>|null */
    private ?array $surahs = null;

    /** @var array<int, int> */
    private array $pageHeights = [];

    public function surahs(): array
    {
        if ($this->surahs !== null) {
            return $this->surahs;
        }

        $rows = DB::table('quran_surahs')
            ->where('dataset_version_id', $this->datasetVersionId())
            ->orderBy('id')
            ->get(['id', 'name_ar', 'ayah_count', 'page_start', 'page_end']);

        return $this->surahs = array_map(
            static fn (object $row): array => [
                'id' => (int) $row->id,
                'name_ar' => (string) $row->name_ar,
                'ayah_count' => (int) $row->ayah_count,
                'page_start' => (int) $row->page_start,
                'page_end' => (int) $row->page_end,
            ],
            $rows->all(),
        );
    }

    public function linesOnPage(int $page): int
    {
        if (array_key_exists($page, $this->pageHeights)) {
            return $this->pageHeights[$page];
        }

        $height = DB::table('quran_pages')
            ->where('dataset_version_id', $this->datasetVersionId())
            ->where('page_number', $page)
            ->value('lines_count');

        if ($height === null) {
            throw new QuranDataUnavailable("Page {$page} is not in the active dataset.");
        }

        return $this->pageHeights[$page] = (int) $height;
    }

    public function ayahLocation(int $surahId, int $ayahNumber): AyahLocation
    {
        $surah = DB::table('quran_surahs')
            ->where('dataset_version_id', $this->datasetVersionId())
            ->where('id', $surahId)
            ->first(['id', 'ayah_count']);

        if ($surah === null) {
            throw new QuranDataUnavailable("Surah {$surahId} is not in the active dataset.");
        }

        if ($ayahNumber < 1 || $ayahNumber > (int) $surah->ayah_count) {
            throw new QuranDataUnavailable("Ayah {$ayahNumber} is outside surah {$surahId}.");
        }

        $ayah = DB::table('quran_ayahs')
            ->where('dataset_version_id', $this->datasetVersionId())
            ->where('surah_id', $surahId)
            ->where('number_in_surah', $ayahNumber)
            ->first(['id', 'page_start', 'line_start', 'page_end', 'line_end']);

        if ($ayah === null) {
            throw new QuranDataUnavailable("Ayah {$surahId}:{$ayahNumber} is not in the active dataset.");
        }

        return new AyahLocation(
            surahId: $surahId,
            ayahNumber: $ayahNumber,
            ayahId: (int) $ayah->id,
            pageStart: (int) $ayah->page_start,
            lineStart: (int) $ayah->line_start,
            pageEnd: (int) $ayah->page_end,
            lineEnd: (int) $ayah->line_end,
        );
    }

    public function measure(int $fromAyahId, int $toAyahId): PageMeasurement
    {
        if ($fromAyahId > $toAyahId) {
            throw new QuranDataUnavailable('The start ayah must not come after the end ayah.');
        }

        $from = $this->ayahRow($fromAyahId);
        $to = $this->ayahRow($toAyahId);

        $pageStart = (int) $from->page_start;
        $pageEnd = (int) $to->page_end;
        $heights = $this->heightsFor($pageStart, $pageEnd);

        $lines = 0;
        $pages = BigDecimal::zero();

        for ($page = $pageStart; $page <= $pageEnd; $page++) {
            if (! array_key_exists($page, $heights)) {
                throw new QuranDataUnavailable("Page {$page} is not in the active dataset.");
            }

            $height = $heights[$page];
            $first = $page === $pageStart ? (int) $from->line_start : 1;
            $last = $page === $pageEnd ? (int) $to->line_end : $height;
            $covered = max(0, $last - $first + 1);

            $lines += $covered;
            $pages = $pages->plus(
                BigDecimal::of($covered)->dividedBy($height, 6, RoundingMode::HALF_UP),
            );
        }

        return new PageMeasurement(
            fromAyahId: $fromAyahId,
            toAyahId: $toAyahId,
            amountLines: $lines,
            amountPages: $pages->toScale(6)->__toString(),
            pageStart: $pageStart,
            lineStart: (int) $from->line_start,
            pageEnd: $pageEnd,
            lineEnd: (int) $to->line_end,
            datasetVersion: $this->datasetVersion(),
        );
    }

    public function datasetVersion(): string
    {
        return (string) $this->activeVersion()->source_version;
    }

    public function datasetVersionId(): int
    {
        return (int) $this->activeVersion()->id;
    }

    /**
     * The single snapshot every query is scoped to.
     *
     * A row that is flagged active but never passed validation is treated as
     * "no dataset": activating an unvalidated import must not make it readable.
     */
    private function activeVersion(): object
    {
        if ($this->active !== null) {
            return $this->active;
        }

        $row = DB::table('quran_dataset_versions')
            ->where('is_active', true)
            ->first(['id', 'source_version', 'validation_status', 'edition']);

        if ($row === null) {
            throw new QuranDataUnavailable('No active Quran dataset is configured.');
        }

        if ((string) $row->validation_status !== 'passed') {
            throw new QuranDataUnavailable(
                "The active Quran dataset (version {$row->id}) has not passed validation.",
            );
        }

        return $this->active = $row;
    }

    private function ayahRow(int $ayahId): object
    {
        $row = DB::table('quran_ayahs')
            ->where('dataset_version_id', $this->datasetVersionId())
            ->where('id', $ayahId)
            ->first(['id', 'page_start', 'line_start', 'page_end', 'line_end']);

        if ($row === null) {
            throw new QuranDataUnavailable("Ayah id {$ayahId} is not in the active dataset.");
        }

        return $row;
    }

    /**
     * Page heights for a contiguous range, in one query rather than one per page.
     *
     * @return array<int, int>
     */
    private function heightsFor(int $pageStart, int $pageEnd): array
    {
        /** @var Collection<int, object> $rows */
        $rows = DB::table('quran_pages')
            ->where('dataset_version_id', $this->datasetVersionId())
            ->whereBetween('page_number', [$pageStart, $pageEnd])
            ->get(['page_number', 'lines_count']);

        $heights = [];

        foreach ($rows as $row) {
            $heights[(int) $row->page_number] = (int) $row->lines_count;
        }

        return $heights;
    }
}
