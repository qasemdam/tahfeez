<?php

namespace App\Infrastructure\Quran\Import;

use App\Domain\Quran\Exceptions\QuranImportFailed;
use App\Domain\Quran\Import\DatasetPayload;
use App\Domain\Quran\Import\QuranSource;
use JsonException;

/**
 * Reads the Tahfeez import file: one JSON document with `surahs`, `pages` and
 * `ayahs` arrays in the column shape of the quran_* tables.
 *
 * This is the neutral format the importer owns. A vendor file (KFGQPC CSV, a
 * SQL dump, a third-party layout database) is not read directly: it gets its own
 * adapter that produces the same DatasetPayload, so validation and persistence
 * never learn about vendor quirks.
 *
 * The format is documented in docs/quran-import.md.
 */
final class CanonicalJsonSource implements QuranSource
{
    public function read(string $path): DatasetPayload
    {
        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new QuranImportFailed("Unable to read [{$path}].");
        }

        try {
            $document = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new QuranImportFailed('The file is not valid JSON: '.$exception->getMessage());
        }

        if (! is_array($document)) {
            throw new QuranImportFailed('The file must contain a JSON object.');
        }

        foreach (['surahs', 'pages', 'ayahs'] as $key) {
            if (! isset($document[$key]) || ! is_array($document[$key]) || $document[$key] === []) {
                throw new QuranImportFailed("The file must contain a non-empty \"{$key}\" array.");
            }
        }

        return new DatasetPayload(
            edition: (string) ($document['edition'] ?? ''),
            sourceVersion: (string) ($document['source_version'] ?? ''),
            surahs: $this->surahs($document['surahs']),
            pages: $this->pages($document['pages']),
            ayahs: $this->ayahs($document['ayahs']),
        );
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function surahs(array $rows): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name_ar' => (string) $row['name_ar'],
            'name_en' => (string) $row['name_en'],
            'name_transliteration' => $row['name_transliteration'] ?? null,
            'revelation_place' => $row['revelation_place'] ?? null,
            'ayah_count' => (int) $row['ayah_count'],
            'page_start' => (int) $row['page_start'],
            'page_end' => (int) $row['page_end'],
        ], $rows);
    }

    /**
     * `line_offset` is the running sum of the previous page heights. It is an
     * ordering/audit field, so it is computed when the file omits it — never
     * used to derive a page height.
     *
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function pages(array $rows): array
    {
        usort($rows, static fn (array $a, array $b): int => (int) $a['page_number'] <=> (int) $b['page_number']);

        $offset = 0;
        $pages = [];

        foreach ($rows as $row) {
            $lines = (int) $row['lines_count'];

            $pages[] = [
                'page_number' => (int) $row['page_number'],
                'lines_count' => $lines,
                'line_offset' => isset($row['line_offset']) ? (int) $row['line_offset'] : $offset,
                'juz_number' => $row['juz_number'] ?? null,
                'hizb_number' => $row['hizb_number'] ?? null,
                'rub_number' => $row['rub_number'] ?? null,
            ];

            $offset += $lines;
        }

        return $pages;
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function ayahs(array $rows): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'surah_id' => (int) $row['surah_id'],
            'number_in_surah' => (int) $row['number_in_surah'],
            'text_uthmani' => (string) $row['text_uthmani'],
            'page_start' => (int) $row['page_start'],
            'line_start' => (int) $row['line_start'],
            'page_end' => (int) $row['page_end'],
            'line_end' => (int) $row['line_end'],
            'juz_number' => $row['juz_number'] ?? null,
            'is_sajda' => (bool) ($row['is_sajda'] ?? false),
        ], $rows);
    }
}
