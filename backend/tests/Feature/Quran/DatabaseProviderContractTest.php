<?php

namespace Tests\Feature\Quran;

use App\Domain\Quran\Exceptions\QuranDataUnavailable;
use App\Infrastructure\Quran\DatabaseQuranDataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Quran\QuranProviderContract;
use Tests\TestCase;

/**
 * The database provider against synthetic data only.
 *
 * No Quran text is inserted: the rows describe coordinates and carry placeholder
 * text. The shape mirrors the fixture (surah 1 on an 8-line page, surah 2 from a
 * 15-line page, ayah 14 crossing to page 3) so the shared contract can be
 * asserted identically against both implementations.
 */
class DatabaseProviderContractTest extends TestCase
{
    use QuranProviderContract;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function version(array $overrides = []): int
    {
        return (int) DB::table('quran_dataset_versions')->insertGetId([
            'source' => 'synthetic-test',
            'source_url' => 'https://example.test/synthetic',
            'source_version' => 'test-v1',
            'edition' => 'test_synthetic',
            'source_format' => 'json',
            'downloaded_at' => now(),
            'imported_at' => now(),
            'checksum_sha256' => str_repeat('b', 64),
            'validation_status' => 'pending',
            'validation_report' => json_encode(['checks' => []]),
            'is_active' => true,
            'notes' => 'Synthetic coordinates for tests; no Quran text.',
            'created_at' => now(),
            'updated_at' => now(),
            ...$overrides,
            // Children cannot be written into an accepted snapshot, so the
            // version stays pending until finalise() flips it.
            'validation_status' => 'pending',
        ]);
    }

    private function finalise(int $versionId, string $status = 'passed'): void
    {
        DB::table('quran_dataset_versions')->where('id', $versionId)->update([
            'validation_status' => $status,
            'updated_at' => now(),
        ]);
    }

    private function seedSurahs(int $versionId): void
    {
        DB::table('quran_surahs')->insert([
            [
                'dataset_version_id' => $versionId, 'id' => 1,
                'name_ar' => 'سورة اختبار', 'name_en' => 'Test One',
                'ayah_count' => 7, 'page_start' => 1, 'page_end' => 1,
            ],
            [
                'dataset_version_id' => $versionId, 'id' => 2,
                'name_ar' => 'سورة اختبار ثانية', 'name_en' => 'Test Two',
                'ayah_count' => 286, 'page_start' => 2, 'page_end' => 49,
            ],
        ]);
    }

    private function seedPages(int $versionId, int $firstPageLines = 8, int $lastPage = 3): void
    {
        $offset = 0;

        for ($page = 1; $page <= $lastPage; $page++) {
            $lines = $page === 1 ? $firstPageLines : 15;

            DB::table('quran_pages')->insert([
                'dataset_version_id' => $versionId,
                'page_number' => $page,
                'lines_count' => $lines,
                'line_offset' => $offset,
            ]);

            $offset += $lines;
        }
    }

    private function seedAyahs(int $versionId): void
    {
        $rows = [
            [1, 1, 1, 1, 1, 1, 1],
            [2, 1, 2, 1, 2, 1, 2],
            [3, 1, 3, 1, 3, 1, 3],
            [4, 1, 4, 1, 4, 1, 4],
            [5, 1, 5, 1, 5, 1, 5],
            [6, 1, 6, 1, 6, 1, 6],
            [7, 1, 7, 1, 7, 1, 8],
            [8, 2, 1, 2, 1, 2, 1],
            [9, 2, 2, 2, 2, 2, 2],
            [10, 2, 3, 2, 3, 2, 5],
            [11, 2, 4, 2, 6, 2, 8],
            [12, 2, 5, 2, 9, 2, 11],
            [13, 2, 6, 2, 12, 2, 15],
            [14, 2, 7, 2, 15, 3, 2],
        ];

        $payload = [];

        foreach ($rows as [$id, $surahId, $number, $pageStart, $lineStart, $pageEnd, $lineEnd]) {
            $payload[] = [
                'dataset_version_id' => $versionId,
                'id' => $id,
                'surah_id' => $surahId,
                'number_in_surah' => $number,
                'text_uthmani' => 'نص اصطناعي '.$id,
                'page_start' => $pageStart,
                'line_start' => $lineStart,
                'page_end' => $pageEnd,
                'line_end' => $lineEnd,
            ];
        }

        DB::table('quran_ayahs')->insert($payload);
    }

    private function seedDataset(int $firstPageLines = 8): int
    {
        $versionId = $this->version();
        $this->seedSurahs($versionId);
        $this->seedPages($versionId, $firstPageLines);
        $this->seedAyahs($versionId);
        $this->finalise($versionId);

        return $versionId;
    }

    public function test_it_satisfies_the_provider_contract(): void
    {
        $this->seedDataset();

        $this->assertProviderContract(new DatabaseQuranDataProvider);
    }

    public function test_it_rejects_a_reversed_range(): void
    {
        $this->seedDataset();

        $this->assertRejectsReversedRange(new DatabaseQuranDataProvider);
    }

    public function test_it_refuses_to_answer_without_an_active_dataset(): void
    {
        $this->expectException(QuranDataUnavailable::class);

        (new DatabaseQuranDataProvider)->surahs();
    }

    public function test_it_refuses_an_active_dataset_that_failed_validation(): void
    {
        $versionId = $this->version();
        $this->seedSurahs($versionId);
        $this->seedPages($versionId);
        $this->seedAyahs($versionId);
        $this->finalise($versionId, 'failed');

        $this->expectException(QuranDataUnavailable::class);

        (new DatabaseQuranDataProvider)->datasetVersionId();
    }

    public function test_it_reads_only_the_active_version(): void
    {
        $active = $this->seedDataset();

        // A second, inactive snapshot with a different page height.
        $other = $this->version([
            'source_version' => 'test-v2',
            'is_active' => false,
            'checksum_sha256' => str_repeat('c', 64),
        ]);
        $this->seedSurahs($other);
        $this->seedPages($other, 20);
        $this->seedAyahs($other);
        $this->finalise($other);

        $provider = new DatabaseQuranDataProvider;

        $this->assertSame($active, $provider->datasetVersionId());
        $this->assertSame(8, $provider->linesOnPage(1));

        // Switching the active snapshot changes the answer — and only then.
        DB::table('quran_dataset_versions')->where('id', $active)->update(['is_active' => false]);
        DB::table('quran_dataset_versions')->where('id', $other)->update(['is_active' => true]);

        $switched = new DatabaseQuranDataProvider;

        $this->assertSame($other, $switched->datasetVersionId());
        $this->assertSame(20, $switched->linesOnPage(1));
    }

    public function test_a_version_never_borrows_rows_from_another_version(): void
    {
        $versionId = $this->seedDataset();
        $other = $this->version(['is_active' => false, 'checksum_sha256' => str_repeat('d', 64)]);

        // The other version has pages but no ayahs: measuring must not fall back.
        $this->seedPages($other, 15);

        $this->assertCount(2, (new DatabaseQuranDataProvider)->surahs());

        DB::table('quran_dataset_versions')->where('id', $versionId)->update(['is_active' => false]);
        DB::table('quran_dataset_versions')->where('id', $other)->update(['is_active' => true]);

        $this->expectException(QuranDataUnavailable::class);

        (new DatabaseQuranDataProvider)->measure(1, 7);
    }

    public function test_a_missing_page_is_an_error_not_a_guess(): void
    {
        $versionId = $this->version();
        $this->seedSurahs($versionId);
        $this->seedPages($versionId, 8, 2);
        $this->seedAyahs($versionId);

        // Ayah 14 ends on page 3, which this snapshot does not have.
        $this->expectException(QuranDataUnavailable::class);

        (new DatabaseQuranDataProvider)->measure(1, 14);
    }

    public function test_an_unknown_surah_is_rejected(): void
    {
        $this->seedDataset();

        $this->expectException(QuranDataUnavailable::class);

        (new DatabaseQuranDataProvider)->ayahLocation(9, 1);
    }

    public function test_an_ayah_outside_its_surah_is_rejected(): void
    {
        $this->seedDataset();

        $this->expectException(QuranDataUnavailable::class);

        (new DatabaseQuranDataProvider)->ayahLocation(1, 8);
    }

    public function test_an_unknown_ayah_id_is_rejected(): void
    {
        $this->seedDataset();

        $this->expectException(QuranDataUnavailable::class);

        (new DatabaseQuranDataProvider)->measure(1, 6236);
    }
}
