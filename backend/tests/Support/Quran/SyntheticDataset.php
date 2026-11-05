<?php

namespace Tests\Support\Quran;

/**
 * Builds a structurally valid, entirely synthetic dataset for import tests.
 *
 * No Quran text is used: surah names are "سورة N" and ayah text is "نص N". The
 * numbers are synthetic too — 114 surahs whose counts happen to sum to 6,236,
 * and 604 pages with the documented heights (8, 8, then 15) so the edition
 * profile check has something real to assert.
 *
 * Ayahs are spread across every page proportionally, so the dataset covers
 * pages 1…604 rather than stopping early.
 */
final class SyntheticDataset
{
    public const SURAHS = 114;

    public const AYAHS = 6236;

    public const PAGES = 604;

    /**
     * @return array{edition: string, source_version: string, surahs: array<int, array<string, mixed>>, pages: array<int, array<string, mixed>>, ayahs: array<int, array<string, mixed>>}
     */
    public static function make(string $edition = 'madinah_1405h_hafs_uthmani'): array
    {
        $pages = [];
        $flat = [];
        $offset = 0;

        for ($page = 1; $page <= self::PAGES; $page++) {
            $lines = $page <= 2 ? 8 : 15;

            $pages[] = [
                'page_number' => $page,
                'lines_count' => $lines,
                'line_offset' => $offset,
                'juz_number' => null,
            ];

            for ($line = 1; $line <= $lines; $line++) {
                $flat[] = [$page, $line];
            }

            $offset += $lines;
        }

        $totalLines = count($flat);

        // 113 surahs of 55 ayahs plus 21 in the last one = 6,236.
        $counts = array_fill(1, 113, 55);
        $counts[114] = self::AYAHS - array_sum($counts);

        $ayahs = [];
        $firstPage = [];
        $lastPage = [];
        $globalId = 1;

        foreach ($counts as $surahId => $count) {
            for ($number = 1; $number <= $count; $number++) {
                $index = intdiv(($globalId - 1) * $totalLines, self::AYAHS);
                [$page, $line] = $flat[$index];

                $ayahs[] = [
                    'id' => $globalId,
                    'surah_id' => $surahId,
                    'number_in_surah' => $number,
                    'text_uthmani' => 'نص '.$globalId,
                    'page_start' => $page,
                    'line_start' => $line,
                    'page_end' => $page,
                    'line_end' => $line,
                    'juz_number' => null,
                    'is_sajda' => false,
                ];

                $firstPage[$surahId] ??= $page;
                $lastPage[$surahId] = $page;

                $globalId++;
            }
        }

        $surahs = [];

        foreach ($counts as $surahId => $count) {
            $surahs[] = [
                'id' => $surahId,
                'name_ar' => 'سورة '.$surahId,
                'name_en' => 'Surah '.$surahId,
                'ayah_count' => $count,
                'page_start' => $firstPage[$surahId],
                'page_end' => $lastPage[$surahId],
            ];
        }

        return [
            'edition' => $edition,
            'source_version' => 'synthetic-v1',
            'surahs' => $surahs,
            'pages' => $pages,
            'ayahs' => $ayahs,
        ];
    }

    /**
     * @param  array<string, mixed>  $dataset
     */
    public static function write(array $dataset, string $suffix = ''): string
    {
        $path = sys_get_temp_dir().'/tahfeez-quran-'.uniqid($suffix, true).'.json';

        file_put_contents($path, json_encode($dataset, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $path;
    }
}
