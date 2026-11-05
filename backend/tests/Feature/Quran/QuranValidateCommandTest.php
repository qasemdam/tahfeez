<?php

namespace Tests\Feature\Quran;

use App\Domain\Quran\Import\ContentDigest;
use App\Domain\Quran\Import\DatasetPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Quran\SyntheticDataset;
use Tests\TestCase;

/**
 * quran:validate — re-checking a stored snapshot without touching it (S6.2).
 *
 * The importer is used to create the intact snapshot so the test exercises the
 * real report, stats and digest rather than a hand-made approximation.
 */
class QuranValidateCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    private function importedSnapshot(): int
    {
        $path = SyntheticDataset::write(SyntheticDataset::make(), 'validate');
        $this->files[] = $path;

        $this->artisan('quran:import', [
            'path' => $path,
            '--edition' => 'madinah_1405h_hafs_uthmani',
        ])->assertExitCode(0);

        return (int) DB::table('quran_dataset_versions')->value('id');
    }

    public function test_an_intact_snapshot_is_reported_as_intact_but_incomplete(): void
    {
        $version = $this->importedSnapshot();

        $this->artisan('quran:validate', ['version_id' => $version])
            ->expectsOutputToContain('matches')
            ->expectsOutputToContain('V-10')
            ->expectsOutputToContain('V-12')
            // V-10 is skipped and critical, so the snapshot is not activatable.
            ->assertExitCode(1);
    }

    public function test_revalidation_does_not_modify_any_record(): void
    {
        $version = $this->importedSnapshot();

        $before = [
            'version' => (array) DB::table('quran_dataset_versions')->where('id', $version)->first(),
            'ayahs' => DB::table('quran_ayahs')->where('dataset_version_id', $version)->count(),
            'surahs' => DB::table('quran_surahs')->where('dataset_version_id', $version)->count(),
            'pages' => DB::table('quran_pages')->where('dataset_version_id', $version)->count(),
            'sample' => (array) DB::table('quran_ayahs')->where('dataset_version_id', $version)->where('id', 1)->first(),
        ];

        $this->artisan('quran:validate', ['version_id' => $version])->assertExitCode(1);

        $this->assertSame($before['version'], (array) DB::table('quran_dataset_versions')->where('id', $version)->first());
        $this->assertSame($before['ayahs'], DB::table('quran_ayahs')->where('dataset_version_id', $version)->count());
        $this->assertSame($before['surahs'], DB::table('quran_surahs')->where('dataset_version_id', $version)->count());
        $this->assertSame($before['pages'], DB::table('quran_pages')->where('dataset_version_id', $version)->count());
        $this->assertSame($before['sample'], (array) DB::table('quran_ayahs')->where('dataset_version_id', $version)->where('id', 1)->first());
    }

    public function test_corrupted_rows_are_detected_by_the_digest(): void
    {
        // Seed as pending so the rows can be edited, then freeze the snapshot
        // with the digest of the *original* content.
        $dataset = SyntheticDataset::make();
        $digest = $this->digestOf($dataset);

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

        $this->insertPayload($version, $dataset);

        DB::table('quran_ayahs')->where('dataset_version_id', $version)->where('id', 1)
            ->update(['text_uthmani' => 'نص محرّف']);

        DB::table('quran_dataset_versions')->where('id', $version)->update([
            'validation_status' => 'incomplete',
            'content_digest' => $digest,
        ]);

        $this->artisan('quran:validate', ['version_id' => $version])
            ->expectsOutputToContain('does not match')
            ->assertExitCode(1);
    }

    public function test_a_missing_version_is_reported(): void
    {
        $this->artisan('quran:validate', ['version_id' => 9999])
            ->expectsOutputToContain('does not exist')
            ->assertExitCode(1);
    }

    public function test_an_invalid_id_is_rejected(): void
    {
        $this->artisan('quran:validate', ['version_id' => 0])->assertExitCode(1);
    }

    /**
     * @param  array<string, mixed>  $dataset
     */
    private function digestOf(array $dataset): string
    {
        return ContentDigest::of(new DatasetPayload(
            edition: (string) $dataset['edition'],
            sourceVersion: (string) $dataset['source_version'],
            surahs: $dataset['surahs'],
            pages: $dataset['pages'],
            ayahs: $dataset['ayahs'],
        ));
    }

    /**
     * @param  array<string, mixed>  $dataset
     */
    private function insertPayload(int $versionId, array $dataset): void
    {
        foreach (array_chunk($dataset['surahs'], 200) as $chunk) {
            DB::table('quran_surahs')->insert(array_map(
                static fn (array $row): array => [...$row, 'dataset_version_id' => $versionId],
                $chunk,
            ));
        }

        foreach (array_chunk($dataset['pages'], 200) as $chunk) {
            DB::table('quran_pages')->insert(array_map(
                static fn (array $row): array => [...$row, 'dataset_version_id' => $versionId],
                $chunk,
            ));
        }

        foreach (array_chunk($dataset['ayahs'], 500) as $chunk) {
            DB::table('quran_ayahs')->insert(array_map(
                static fn (array $row): array => [...$row, 'dataset_version_id' => $versionId],
                $chunk,
            ));
        }
    }
}
