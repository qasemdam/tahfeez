<?php

namespace App\Domain\Quran\Import;

use App\Domain\Quran\Exceptions\QuranDataUnavailable;
use Illuminate\Support\Facades\DB;

/**
 * Reads a stored snapshot back into a DatasetPayload (S6.1).
 *
 * This is what makes revalidation possible without re-importing the file: the
 * same validator that ran before the write can run again against what is in the
 * tables now.
 *
 * Three queries, ordered by primary key, no joins and no per-row lookups. Nothing
 * here writes.
 */
final class SnapshotReader
{
    /**
     * @throws QuranDataUnavailable
     */
    public function read(int $versionId): DatasetPayload
    {
        $version = DB::table('quran_dataset_versions')
            ->where('id', $versionId)
            ->first(['id', 'edition', 'source_version']);

        if ($version === null) {
            throw new QuranDataUnavailable("Quran dataset version {$versionId} does not exist.");
        }

        return new DatasetPayload(
            edition: (string) $version->edition,
            sourceVersion: (string) $version->source_version,
            surahs: $this->surahs($versionId),
            pages: $this->pages($versionId),
            ayahs: $this->ayahs($versionId),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function surahs(int $versionId): array
    {
        return DB::table('quran_surahs')
            ->where('dataset_version_id', $versionId)
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en', 'ayah_count', 'page_start', 'page_end'])
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'name_ar' => (string) $row->name_ar,
                'name_en' => (string) $row->name_en,
                'ayah_count' => (int) $row->ayah_count,
                'page_start' => (int) $row->page_start,
                'page_end' => (int) $row->page_end,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pages(int $versionId): array
    {
        return DB::table('quran_pages')
            ->where('dataset_version_id', $versionId)
            ->orderBy('page_number')
            ->get(['page_number', 'lines_count', 'line_offset', 'juz_number', 'hizb_number', 'rub_number'])
            ->map(static fn (object $row): array => [
                'page_number' => (int) $row->page_number,
                'lines_count' => (int) $row->lines_count,
                'line_offset' => (int) $row->line_offset,
                'juz_number' => $row->juz_number === null ? null : (int) $row->juz_number,
                'hizb_number' => $row->hizb_number === null ? null : (int) $row->hizb_number,
                'rub_number' => $row->rub_number === null ? null : (int) $row->rub_number,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ayahs(int $versionId): array
    {
        return DB::table('quran_ayahs')
            ->where('dataset_version_id', $versionId)
            ->orderBy('id')
            ->get([
                'id', 'surah_id', 'number_in_surah', 'text_uthmani',
                'page_start', 'line_start', 'page_end', 'line_end',
                'juz_number', 'is_sajda',
            ])
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'surah_id' => (int) $row->surah_id,
                'number_in_surah' => (int) $row->number_in_surah,
                'text_uthmani' => (string) $row->text_uthmani,
                'page_start' => (int) $row->page_start,
                'line_start' => (int) $row->line_start,
                'page_end' => (int) $row->page_end,
                'line_end' => (int) $row->line_end,
                'juz_number' => $row->juz_number === null ? null : (int) $row->juz_number,
                'is_sajda' => (bool) $row->is_sajda,
            ])
            ->all();
    }
}
