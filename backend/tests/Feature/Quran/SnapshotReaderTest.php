<?php

namespace Tests\Feature\Quran;

use App\Domain\Quran\Exceptions\QuranDataUnavailable;
use App\Domain\Quran\Import\SnapshotReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Quran\SyntheticDataset;
use Tests\TestCase;

/**
 * Reading a stored snapshot back into a payload (S6.1).
 */
class SnapshotReaderTest extends TestCase
{
    use RefreshDatabase;

    private function seedFullSnapshot(): int
    {
        $version = (int) DB::table('quran_dataset_versions')->insertGetId([
            'source' => 'synthetic-test',
            'source_url' => '',
            'source_version' => 'test-v1',
            'edition' => 'madinah_1405h_hafs_uthmani',
            'source_format' => 'json',
            'downloaded_at' => now(),
            'imported_at' => now(),
            'checksum_sha256' => str_repeat('a', 64),
            'content_digest' => null,
            'validation_status' => 'pending',
            'validation_report' => json_encode(['passed' => false, 'status' => 'pending', 'checks' => [], 'stats' => []]),
            'is_active' => false,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dataset = SyntheticDataset::make();

        foreach (array_chunk($dataset['surahs'], 200) as $chunk) {
            DB::table('quran_surahs')->insert(array_map(
                static fn (array $row): array => [...$row, 'dataset_version_id' => $version],
                $chunk,
            ));
        }

        foreach (array_chunk($dataset['pages'], 200) as $chunk) {
            DB::table('quran_pages')->insert(array_map(
                static fn (array $row): array => [...$row, 'dataset_version_id' => $version],
                $chunk,
            ));
        }

        foreach (array_chunk($dataset['ayahs'], 500) as $chunk) {
            DB::table('quran_ayahs')->insert(array_map(
                static fn (array $row): array => [...$row, 'dataset_version_id' => $version],
                $chunk,
            ));
        }

        return $version;
    }

    public function test_it_reads_every_row_of_the_requested_version(): void
    {
        $version = $this->seedFullSnapshot();

        $payload = (new SnapshotReader)->read($version);

        $this->assertSame(114, $payload->surahCount());
        $this->assertSame(604, $payload->pageCount());
        $this->assertSame(6236, $payload->ayahCount());
        $this->assertSame('madinah_1405h_hafs_uthmani', $payload->edition);
        $this->assertSame('test-v1', $payload->sourceVersion);
    }

    public function test_rows_come_back_in_a_stable_order(): void
    {
        $version = $this->seedFullSnapshot();

        $payload = (new SnapshotReader)->read($version);

        $surahIds = array_column($payload->surahs, 'id');
        $sorted = $surahIds;
        sort($sorted);
        $this->assertSame($sorted, $surahIds);

        $pageNumbers = array_column($payload->pages, 'page_number');
        $sortedPages = $pageNumbers;
        sort($sortedPages);
        $this->assertSame($sortedPages, $pageNumbers);

        $ayahIds = array_column($payload->ayahs, 'id');
        $sortedAyahs = $ayahIds;
        sort($sortedAyahs);
        $this->assertSame($sortedAyahs, $ayahIds);
    }

    public function test_a_missing_version_is_reported_clearly(): void
    {
        $this->expectException(QuranDataUnavailable::class);
        $this->expectExceptionMessage('does not exist');

        (new SnapshotReader)->read(4242);
    }

    public function test_reading_does_not_modify_anything(): void
    {
        $version = $this->seedFullSnapshot();

        $before = [
            'version' => (array) DB::table('quran_dataset_versions')->where('id', $version)->first(),
            'ayahs' => DB::table('quran_ayahs')->where('dataset_version_id', $version)->count(),
            'surahs' => DB::table('quran_surahs')->where('dataset_version_id', $version)->count(),
            'pages' => DB::table('quran_pages')->where('dataset_version_id', $version)->count(),
        ];

        (new SnapshotReader)->read($version);

        $this->assertSame($before['version'], (array) DB::table('quran_dataset_versions')->where('id', $version)->first());
        $this->assertSame($before['ayahs'], DB::table('quran_ayahs')->where('dataset_version_id', $version)->count());
        $this->assertSame($before['surahs'], DB::table('quran_surahs')->where('dataset_version_id', $version)->count());
        $this->assertSame($before['pages'], DB::table('quran_pages')->where('dataset_version_id', $version)->count());
    }
}
