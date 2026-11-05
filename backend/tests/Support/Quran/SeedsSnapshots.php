<?php

namespace Tests\Support\Quran;

use App\Domain\Quran\Import\ContentDigest;
use App\Domain\Quran\Import\DatasetPayload;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a small synthetic snapshot directly.
 *
 * Small on purpose: the digest and immutability tests do not need 6,236 rows,
 * and the validator's totals are overridden where a full dataset is required.
 * No Quran text is involved.
 */
trait SeedsSnapshots
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function seedVersion(string $status = 'passed', array $overrides = []): int
    {
        return (int) DB::table('quran_dataset_versions')->insertGetId([
            'source' => 'synthetic-test',
            'source_url' => '',
            'source_version' => 'test-v1',
            'edition' => 'synthetic_regression',
            'source_format' => 'json',
            'downloaded_at' => now(),
            'imported_at' => now(),
            'checksum_sha256' => str_repeat('a', 64),
            'content_digest' => null,
            'validation_status' => $status,
            'validation_report' => json_encode(['passed' => false, 'status' => $status, 'checks' => [], 'stats' => []]),
            'is_active' => false,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
            ...$overrides,
        ]);
    }

    /**
     * @return array{edition: string, source_version: string, surahs: array<int, array<string, mixed>>, pages: array<int, array<string, mixed>>, ayahs: array<int, array<string, mixed>>}
     */
    protected function tinyPayload(): array
    {
        return [
            'edition' => 'synthetic_regression',
            'source_version' => 'test-v1',
            'surahs' => [
                ['id' => 1, 'name_ar' => 'سورة اصطناعية أولى', 'name_en' => 'Synthetic One', 'ayah_count' => 2, 'page_start' => 1, 'page_end' => 1],
                ['id' => 2, 'name_ar' => 'سورة اصطناعية ثانية', 'name_en' => 'Synthetic Two', 'ayah_count' => 1, 'page_start' => 1, 'page_end' => 2],
            ],
            'pages' => [
                ['page_number' => 1, 'lines_count' => 8, 'line_offset' => 0, 'juz_number' => 1],
                ['page_number' => 2, 'lines_count' => 15, 'line_offset' => 8, 'juz_number' => 1],
            ],
            'ayahs' => [
                ['id' => 1, 'surah_id' => 1, 'number_in_surah' => 1, 'text_uthmani' => 'نص اصطناعي ١', 'page_start' => 1, 'line_start' => 1, 'page_end' => 1, 'line_end' => 1, 'juz_number' => 1, 'is_sajda' => false],
                ['id' => 2, 'surah_id' => 1, 'number_in_surah' => 2, 'text_uthmani' => 'نص اصطناعي ٢', 'page_start' => 1, 'line_start' => 2, 'page_end' => 1, 'line_end' => 3, 'juz_number' => 1, 'is_sajda' => false],
                ['id' => 3, 'surah_id' => 2, 'number_in_surah' => 1, 'text_uthmani' => 'نص اصطناعي ٣', 'page_start' => 1, 'line_start' => 8, 'page_end' => 2, 'line_end' => 2, 'juz_number' => 1, 'is_sajda' => false],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function insertPayload(int $versionId, array $payload): void
    {
        foreach ($payload['surahs'] as $row) {
            DB::table('quran_surahs')->insert([...$row, 'dataset_version_id' => $versionId]);
        }

        foreach ($payload['pages'] as $row) {
            DB::table('quran_pages')->insert([...$row, 'dataset_version_id' => $versionId]);
        }

        foreach ($payload['ayahs'] as $row) {
            DB::table('quran_ayahs')->insert([...$row, 'dataset_version_id' => $versionId]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function digestOf(array $payload): string
    {
        return ContentDigest::of(new DatasetPayload(
            edition: (string) $payload['edition'],
            sourceVersion: (string) $payload['source_version'],
            surahs: $payload['surahs'],
            pages: $payload['pages'],
            ayahs: $payload['ayahs'],
        ));
    }
}
