<?php

namespace Tests\Feature\Quran;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Tests\Support\Quran\SyntheticDataset;
use Tests\TestCase;

/**
 * The quran:import command end to end, against a fully synthetic dataset.
 *
 * Every assertion is about what the command did to the database, not about what
 * it printed — the report is checked where it is stored.
 */
class QuranImportCommandTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $dataset
     */
    private function file(array $dataset, string $suffix = ''): string
    {
        $path = SyntheticDataset::write($dataset, $suffix);
        $this->files[] = $path;

        return $path;
    }

    private function import(string $path, array $options = []): PendingCommand
    {
        return $this->artisan('quran:import', [
            'path' => $path,
            '--edition' => 'madinah_1405h_hafs_uthmani',
            ...$options,
        ]);
    }

    private function activeVersion(): int
    {
        return (int) DB::table('quran_dataset_versions')->insertGetId([
            'source' => 'previous',
            'source_url' => '',
            'source_version' => 'previous-v1',
            'edition' => 'madinah_1405h_hafs_uthmani',
            'source_format' => 'json',
            'downloaded_at' => now(),
            'imported_at' => now(),
            'checksum_sha256' => str_repeat('f', 64),
            'validation_status' => 'passed',
            'validation_report' => json_encode(['passed' => true, 'checks' => []]),
            'is_active' => true,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_valid_dataset_is_imported_as_an_inactive_snapshot(): void
    {
        $path = $this->file(SyntheticDataset::make());

        $this->import($path)->assertExitCode(0);

        $version = DB::table('quran_dataset_versions')->first();

        $this->assertNotNull($version);
        // Structurally sound, but V-10/V-12 (golden sample) cannot run, so the
        // snapshot is stored as incomplete and the provider will not serve it.
        $this->assertSame('incomplete', $version->validation_status);
        $this->assertFalse((bool) $version->is_active, 'An import must never activate its own snapshot.');
        $this->assertSame(hash_file('sha256', $path), $version->checksum_sha256);
        $this->assertSame('madinah_1405h_hafs_uthmani', $version->edition);

        $this->assertSame(114, DB::table('quran_surahs')->where('dataset_version_id', $version->id)->count());
        $this->assertSame(604, DB::table('quran_pages')->where('dataset_version_id', $version->id)->count());
        $this->assertSame(6236, DB::table('quran_ayahs')->where('dataset_version_id', $version->id)->count());
    }

    public function test_the_validation_report_is_stored_with_the_snapshot(): void
    {
        $path = $this->file(SyntheticDataset::make());

        $this->import($path)->assertExitCode(0);

        $report = json_decode((string) DB::table('quran_dataset_versions')->value('validation_report'), true);

        $this->assertFalse($report['passed']);
        $this->assertSame('incomplete', $report['status']);

        $ids = array_column($report['checks'], 'id');

        foreach (['V-01', 'V-02', 'V-03', 'V-04', 'V-05', 'V-06', 'V-07', 'V-08', 'V-09', 'V-11', 'V-13', 'V-14', 'V-15'] as $id) {
            $this->assertContains($id, $ids);
        }

        $statuses = array_column($report['checks'], 'status', 'id');
        $this->assertSame('passed', $statuses['V-01']);
        $this->assertSame('passed', $statuses['V-15']);
        // Golden-sample checks need a checked-in fixture this repository does not have yet.
        $this->assertSame('skipped', $statuses['V-10']);
        $this->assertSame('skipped', $statuses['V-12']);
    }

    public function test_the_edition_must_be_declared(): void
    {
        $path = $this->file(SyntheticDataset::make());

        $this->artisan('quran:import', ['path' => $path])->assertExitCode(1);

        $this->assertSame(0, DB::table('quran_dataset_versions')->count());
    }

    public function test_a_missing_file_is_rejected(): void
    {
        $this->import(sys_get_temp_dir().'/does-not-exist-'.uniqid().'.json')->assertExitCode(1);

        $this->assertSame(0, DB::table('quran_dataset_versions')->count());
    }

    public function test_an_unsupported_format_is_rejected(): void
    {
        $path = $this->file(SyntheticDataset::make());

        $this->import($path, ['--format' => 'csv'])->assertExitCode(1);

        $this->assertSame(0, DB::table('quran_dataset_versions')->count());
    }

    public function test_a_dataset_with_a_missing_surah_is_rejected(): void
    {
        $dataset = SyntheticDataset::make();
        array_pop($dataset['surahs']);

        $this->import($this->file($dataset))->assertExitCode(1);

        $this->assertSame(0, DB::table('quran_dataset_versions')->where('validation_status', 'passed')->count());
        $this->assertFailedCheck('V-01');
    }

    public function test_a_dataset_with_a_duplicate_ayah_is_rejected(): void
    {
        $dataset = SyntheticDataset::make();
        $dataset['ayahs'][] = $dataset['ayahs'][0];

        $this->import($this->file($dataset))->assertExitCode(1);

        $this->assertFailedCheck('V-07');
    }

    public function test_an_ayah_pointing_at_a_missing_surah_is_rejected(): void
    {
        $dataset = SyntheticDataset::make();
        $dataset['ayahs'][0]['surah_id'] = 200;

        $this->import($this->file($dataset))->assertExitCode(1);

        $this->assertFailedCheck('V-13');
    }

    public function test_an_invalid_line_number_is_rejected(): void
    {
        $dataset = SyntheticDataset::make();
        // Page 1 has 8 lines in this edition; line 99 is outside it.
        $dataset['ayahs'][0]['line_start'] = 99;
        $dataset['ayahs'][0]['line_end'] = 99;

        $this->import($this->file($dataset))->assertExitCode(1);

        $this->assertFailedCheck('V-05');
    }

    public function test_an_invalid_page_number_is_rejected(): void
    {
        $dataset = SyntheticDataset::make();
        $dataset['ayahs'][0]['page_start'] = 605;
        $dataset['ayahs'][0]['page_end'] = 605;

        $this->import($this->file($dataset))->assertExitCode(1);

        $this->assertFailedCheck('V-04');
    }

    public function test_a_page_height_that_contradicts_the_edition_is_rejected(): void
    {
        $dataset = SyntheticDataset::make();
        // The 1405H print opens with two 8-line pages; claiming 15 is a wrong edition.
        $dataset['pages'][0]['lines_count'] = 15;

        $this->import($this->file($dataset))->assertExitCode(1);

        $this->assertFailedCheck('V-15');
    }

    public function test_a_failed_import_is_recorded_but_not_usable(): void
    {
        $dataset = SyntheticDataset::make();
        array_pop($dataset['surahs']);

        $this->import($this->file($dataset))->assertExitCode(1);

        $version = DB::table('quran_dataset_versions')->first();

        $this->assertNotNull($version);
        $this->assertSame('failed', $version->validation_status);
        $this->assertFalse((bool) $version->is_active);
        $this->assertSame(0, DB::table('quran_ayahs')->count());
    }

    public function test_a_failed_import_leaves_the_active_snapshot_untouched(): void
    {
        $activeId = $this->activeVersion();

        $dataset = SyntheticDataset::make();
        array_pop($dataset['surahs']);

        $this->import($this->file($dataset))->assertExitCode(1);

        $active = DB::table('quran_dataset_versions')->where('id', $activeId)->first();

        $this->assertSame('passed', $active->validation_status);
        $this->assertTrue((bool) $active->is_active);
        $this->assertSame(1, DB::table('quran_dataset_versions')->where('is_active', true)->count());
    }

    public function test_a_failed_import_does_not_modify_a_previous_snapshot(): void
    {
        $first = $this->file(SyntheticDataset::make());
        $this->import($first)->assertExitCode(0);

        $version = DB::table('quran_dataset_versions')->first();
        $before = [
            'surahs' => DB::table('quran_surahs')->where('dataset_version_id', $version->id)->count(),
            'pages' => DB::table('quran_pages')->where('dataset_version_id', $version->id)->count(),
            'ayahs' => DB::table('quran_ayahs')->where('dataset_version_id', $version->id)->count(),
            'checksum' => $version->checksum_sha256,
        ];

        $broken = SyntheticDataset::make();
        $broken['ayahs'][0]['line_start'] = 99;
        $broken['ayahs'][0]['line_end'] = 99;
        $this->import($this->file($broken, 'broken'))->assertExitCode(1);

        $after = DB::table('quran_dataset_versions')->where('id', $version->id)->first();

        $this->assertSame($before['checksum'], $after->checksum_sha256);
        $this->assertSame('incomplete', $after->validation_status);
        $this->assertSame($before['surahs'], DB::table('quran_surahs')->where('dataset_version_id', $version->id)->count());
        $this->assertSame($before['pages'], DB::table('quran_pages')->where('dataset_version_id', $version->id)->count());
        $this->assertSame($before['ayahs'], DB::table('quran_ayahs')->where('dataset_version_id', $version->id)->count());
    }

    public function test_importing_the_same_file_twice_is_refused(): void
    {
        $path = $this->file(SyntheticDataset::make());

        $this->import($path)->assertExitCode(0);
        $this->import($path)->expectsOutputToContain('already imported')->assertExitCode(1);

        $this->assertSame(1, DB::table('quran_dataset_versions')->count());
    }

    public function test_a_second_file_creates_a_second_independent_snapshot(): void
    {
        $first = $this->file(SyntheticDataset::make());

        // A different version string changes the bytes, so this is a second
        // snapshot rather than a duplicate of the first (same checksum).
        $dataset = SyntheticDataset::make();
        $dataset['source_version'] = 'synthetic-v2';
        $second = $this->file($dataset, 'second');

        $this->import($first)->assertExitCode(0);
        $this->import($second)->assertExitCode(0);

        $versions = DB::table('quran_dataset_versions')->orderBy('id')->get();

        $this->assertCount(2, $versions);
        $this->assertSame(0, DB::table('quran_dataset_versions')->where('is_active', true)->count());

        foreach ($versions as $version) {
            $this->assertSame(6236, DB::table('quran_ayahs')->where('dataset_version_id', $version->id)->count());
        }
    }

    public function test_an_ayah_spanning_the_whole_mushaf_is_rejected(): void
    {
        $dataset = SyntheticDataset::make();
        // The exact corruption the audit reproduced: ayah 1 starts at 1:1 and
        // claims to end at 604:8, which measure() would turn into 603 pages.
        $dataset['ayahs'][0]['page_end'] = 604;
        $dataset['ayahs'][0]['line_end'] = 8;

        $this->import($this->file($dataset))->assertExitCode(1);

        $this->assertFailedCheck('V-16');
        $this->assertSame(0, DB::table('quran_dataset_versions')->where('validation_status', '!=', 'failed')->count());
    }

    public function test_overlapping_ayah_ranges_are_rejected(): void
    {
        $dataset = SyntheticDataset::make();
        // Ayah 1 runs to line 5 of page 1; ayah 2 starts at line 2, inside it.
        // (Sharing a line is legal — a mushaf line carries several ayahs — but
        // starting before the previous ayah ends is not.)
        $dataset['ayahs'][0]['line_end'] = 5;
        $dataset['ayahs'][1]['page_start'] = 1;
        $dataset['ayahs'][1]['line_start'] = 2;

        $this->import($this->file($dataset))->assertExitCode(1);

        $this->assertFailedCheck('V-16');
    }

    public function test_a_surah_page_range_that_ends_before_it_starts_is_rejected(): void
    {
        $dataset = SyntheticDataset::make();
        $dataset['surahs'][113]['page_end'] = 1;

        $this->import($this->file($dataset))->assertExitCode(1);

        $this->assertFailedCheck('V-17');
    }

    public function test_a_surah_with_no_ayahs_is_rejected(): void
    {
        $dataset = SyntheticDataset::make();
        $dataset['surahs'][0]['ayah_count'] = 0;

        $this->import($this->file($dataset))->assertExitCode(1);

        $this->assertFailedCheck('V-14');
    }

    public function test_a_negative_ayah_count_is_rejected(): void
    {
        $dataset = SyntheticDataset::make();
        $dataset['surahs'][0]['ayah_count'] = -5;

        $this->import($this->file($dataset))->assertExitCode(1);

        $this->assertFailedCheck('V-14');
    }

    public function test_the_report_carries_stats_derived_from_the_dataset(): void
    {
        $this->import($this->file(SyntheticDataset::make()))->assertExitCode(0);

        $report = json_decode((string) DB::table('quran_dataset_versions')->value('validation_report'), true);
        $stats = $report['stats'];

        $this->assertSame(114, $stats['surahs']);
        $this->assertSame(604, $stats['pages']);
        $this->assertSame(6236, $stats['ayahs']);
        $this->assertSame(0, $stats['pages_without_ayahs']);
        $this->assertSame(1, $stats['max_ayah_page_span']);
        $this->assertSame([8 => 2, 15 => 602], $stats['line_height_distribution']);
    }

    public function test_a_dataset_reaches_passed_when_no_critical_check_is_skipped(): void
    {
        // The golden-sample checks are skipped because no fixture exists; when
        // they are not treated as critical, the same dataset is activatable.
        config(['quran.import.critical_checks' => [
            'V-01', 'V-02', 'V-03', 'V-04', 'V-05', 'V-06', 'V-07', 'V-08',
            'V-09', 'V-11', 'V-13', 'V-14', 'V-15', 'V-16', 'V-17',
        ]]);

        $this->import($this->file(SyntheticDataset::make()))->assertExitCode(0);

        $this->assertSame('passed', DB::table('quran_dataset_versions')->value('validation_status'));
    }

    public function test_a_checksum_cannot_belong_to_two_accepted_snapshots(): void
    {
        $this->import($this->file(SyntheticDataset::make()))->assertExitCode(0);

        $checksum = (string) DB::table('quran_dataset_versions')->value('checksum_sha256');

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('quran_dataset_versions')->insert([
            'source' => 'second',
            'source_url' => '',
            'source_version' => 'second',
            'edition' => 'madinah_1405h_hafs_uthmani',
            'source_format' => 'json',
            'downloaded_at' => now(),
            'imported_at' => now(),
            'checksum_sha256' => $checksum,
            'validation_status' => 'passed',
            'validation_report' => json_encode(['passed' => true, 'checks' => []]),
            'is_active' => false,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_failed_attempts_may_share_a_checksum(): void
    {
        foreach ([1, 2] as $attempt) {
            DB::table('quran_dataset_versions')->insert([
                'source' => 'retry',
                'source_url' => '',
                'source_version' => 'retry-'.$attempt,
                'edition' => 'madinah_1405h_hafs_uthmani',
                'source_format' => 'json',
                'downloaded_at' => now(),
                'imported_at' => now(),
                'checksum_sha256' => str_repeat('a', 64),
                'validation_status' => 'failed',
                'validation_report' => json_encode(['passed' => false, 'checks' => []]),
                'is_active' => false,
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->assertSame(2, DB::table('quran_dataset_versions')->where('validation_status', 'failed')->count());
    }

    private function assertFailedCheck(string $id): void
    {
        $report = json_decode((string) DB::table('quran_dataset_versions')->value('validation_report'), true);

        $this->assertNotNull($report, 'A rejected dataset must still record its report.');
        $this->assertFalse($report['passed']);

        $failed = array_column(
            array_filter($report['checks'], static fn (array $check): bool => $check['status'] === 'failed'),
            'id',
        );

        $this->assertContains($id, $failed);
    }
}
