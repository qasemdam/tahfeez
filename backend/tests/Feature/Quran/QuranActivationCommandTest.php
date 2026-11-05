<?php

namespace Tests\Feature\Quran;

use App\Domain\Groups\Models\Group;
use App\Domain\Memorization\Models\Memorization;
use App\Domain\Quran\Exceptions\QuranDataUnavailable;
use App\Domain\Quran\Import\ContentDigest;
use App\Domain\Quran\Import\DatasetPayload;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Infrastructure\Quran\DatabaseQuranDataProvider;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Tests\Support\Quran\SyntheticDataset;
use Tests\TestCase;

/**
 * quran:activate — the only way a snapshot becomes servable.
 *
 * Snapshots are seeded directly rather than imported: the importer cannot reach
 * `passed` while V-10/V-12 are critical and have no golden sample (OD-029), and
 * activation is what these tests are about.
 */
class QuranActivationCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function version(array $overrides = []): int
    {
        $status = $overrides['validation_status'] ?? 'passed';

        return (int) DB::table('quran_dataset_versions')->insertGetId([
            'source' => 'synthetic-test',
            'source_url' => 'https://example.test/synthetic',
            'source_version' => 'test-v1',
            'edition' => 'madinah_1405h_hafs_uthmani',
            'source_format' => 'json',
            'downloaded_at' => now(),
            'imported_at' => now(),
            'checksum_sha256' => str_repeat('e', 64),
            'validation_status' => $status,
            'validation_report' => json_encode(['passed' => $status === 'passed', 'status' => $status, 'checks' => [], 'stats' => []]),
            'is_active' => false,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
            ...$overrides,
        ]);
    }

    private function withChildren(int $versionId): int
    {
        $dataset = SyntheticDataset::make();

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

        return $versionId;
    }

    /**
     * Seeds a snapshot the way the importer does — pending, children, then the
     * verdict — because children cannot be written into an accepted snapshot.
     */
    private function completeVersion(array $overrides = []): int
    {
        $status = (string) ($overrides['validation_status'] ?? 'passed');
        $digest = $overrides['content_digest'] ?? null;
        $report = $overrides['validation_report']
            ?? json_encode(['passed' => $status === 'passed', 'status' => $status, 'checks' => [], 'stats' => []]);

        unset($overrides['validation_status'], $overrides['content_digest'], $overrides['validation_report']);

        $version = $this->version(['validation_status' => 'pending', ...$overrides]);
        $this->withChildren($version);

        DB::table('quran_dataset_versions')->where('id', $version)->update([
            'validation_status' => $status,
            'validation_report' => $report,
            'content_digest' => $digest ?? $this->digestOf(SyntheticDataset::make()),
            'updated_at' => now(),
        ]);

        return $version;
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

    private function activate(int $versionId): PendingCommand
    {
        return $this->artisan('quran:activate', ['version_id' => $versionId]);
    }

    private function activeIds(): array
    {
        return DB::table('quran_dataset_versions')->where('is_active', true)->pluck('id')->all();
    }

    public function test_a_passed_snapshot_is_activated(): void
    {
        $id = $this->completeVersion();

        $this->activate($id)->assertExitCode(0);

        $this->assertSame([$id], $this->activeIds());
        $this->assertSame($id, (new DatabaseQuranDataProvider)->datasetVersionId());
    }

    public function test_an_incomplete_snapshot_is_refused(): void
    {
        $id = $this->completeVersion(['validation_status' => 'incomplete']);

        $this->activate($id)->assertExitCode(1);

        $this->assertSame([], $this->activeIds());
    }

    public function test_a_failed_snapshot_is_refused(): void
    {
        $id = $this->completeVersion(['validation_status' => 'failed']);

        $this->activate($id)->assertExitCode(1);

        $this->assertSame([], $this->activeIds());
    }

    public function test_a_missing_version_is_refused(): void
    {
        $this->activate(9999)->assertExitCode(1);

        $this->assertSame([], $this->activeIds());
    }

    public function test_a_passed_snapshot_without_data_is_refused(): void
    {
        // "passed" is a statement about validation, not a substitute for rows.
        $id = $this->version();

        $this->activate($id)->assertExitCode(1);

        $this->assertSame([], $this->activeIds());
    }

    public function test_a_snapshot_marked_passed_with_a_failed_report_is_refused(): void
    {
        $id = $this->completeVersion([
            'validation_report' => json_encode(['passed' => false, 'status' => 'failed', 'checks' => [], 'stats' => []]),
        ]);

        $this->activate($id)->assertExitCode(1);

        $this->assertSame([], $this->activeIds());
    }

    public function test_activation_replaces_the_previous_snapshot(): void
    {
        $first = $this->completeVersion(['checksum_sha256' => str_repeat('a', 64)]);
        $second = $this->completeVersion(['checksum_sha256' => str_repeat('b', 64), 'source_version' => 'test-v2']);

        $this->activate($first)->assertExitCode(0);
        $this->activate($second)->assertExitCode(0);

        $this->assertSame([$second], $this->activeIds());
        $this->assertSame(0, DB::table('quran_dataset_versions')->where('id', $first)->value('is_active'));
        $this->assertSame($second, (new DatabaseQuranDataProvider)->datasetVersionId());
    }

    public function test_a_refused_activation_leaves_the_previous_snapshot_active(): void
    {
        $active = $this->completeVersion(['checksum_sha256' => str_repeat('a', 64)]);
        $broken = $this->completeVersion([
            'checksum_sha256' => str_repeat('b', 64),
            'validation_status' => 'failed',
        ]);

        $this->activate($active)->assertExitCode(0);
        $this->activate($broken)->assertExitCode(1);

        $this->assertSame([$active], $this->activeIds());
    }

    public function test_only_one_snapshot_is_ever_active(): void
    {
        $first = $this->completeVersion(['checksum_sha256' => str_repeat('a', 64)]);
        $second = $this->completeVersion(['checksum_sha256' => str_repeat('b', 64)]);

        $this->activate($first)->assertExitCode(0);
        $this->activate($second)->assertExitCode(0);

        $this->assertSame(1, DB::table('quran_dataset_versions')->where('is_active', true)->count());
    }

    public function test_the_database_refuses_a_second_active_snapshot_even_without_the_command(): void
    {
        $first = $this->completeVersion(['checksum_sha256' => str_repeat('a', 64)]);
        $this->activate($first)->assertExitCode(0);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('quran_dataset_versions')->insert([
            'source' => 'manual',
            'source_url' => '',
            'source_version' => 'manual',
            'edition' => 'madinah_1405h_hafs_uthmani',
            'source_format' => 'json',
            'downloaded_at' => now(),
            'imported_at' => now(),
            'checksum_sha256' => str_repeat('c', 64),
            'validation_status' => 'passed',
            'validation_report' => json_encode(['passed' => true, 'status' => 'passed', 'checks' => [], 'stats' => []]),
            'is_active' => true,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_the_previous_snapshot_keeps_its_rows(): void
    {
        $first = $this->completeVersion(['checksum_sha256' => str_repeat('a', 64)]);
        $second = $this->completeVersion(['checksum_sha256' => str_repeat('b', 64)]);

        $before = [
            'surahs' => DB::table('quran_surahs')->where('dataset_version_id', $first)->count(),
            'pages' => DB::table('quran_pages')->where('dataset_version_id', $first)->count(),
            'ayahs' => DB::table('quran_ayahs')->where('dataset_version_id', $first)->count(),
            'checksum' => DB::table('quran_dataset_versions')->where('id', $first)->value('checksum_sha256'),
        ];

        $this->activate($first)->assertExitCode(0);
        $this->activate($second)->assertExitCode(0);

        $this->assertSame($before['surahs'], DB::table('quran_surahs')->where('dataset_version_id', $first)->count());
        $this->assertSame($before['pages'], DB::table('quran_pages')->where('dataset_version_id', $first)->count());
        $this->assertSame($before['ayahs'], DB::table('quran_ayahs')->where('dataset_version_id', $first)->count());
        $this->assertSame($before['checksum'], DB::table('quran_dataset_versions')->where('id', $first)->value('checksum_sha256'));
        $this->assertSame('passed', DB::table('quran_dataset_versions')->where('id', $first)->value('validation_status'));
    }

    public function test_historical_records_are_not_touched(): void
    {
        $first = $this->completeVersion(['checksum_sha256' => str_repeat('a', 64)]);
        $second = $this->completeVersion(['checksum_sha256' => str_repeat('b', 64)]);

        $center = Center::factory()->create();
        $teacher = User::factory()->teacher()->for($center)->create();
        $group = Group::factory()->for($center)->create(['teacher_id' => $teacher->id]);
        $student = Student::factory()->for($center)->create();

        $record = Memorization::factory()->for($center)->create([
            'student_id' => $student->id,
            'group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'quran_dataset_version_id' => $first,
            'quran_dataset_version' => 'test-v1',
        ]);

        $snapshot = $record->fresh()->toArray();

        $this->activate($first)->assertExitCode(0);
        $this->activate($second)->assertExitCode(0);

        $this->assertSame($snapshot, $record->fresh()->toArray());
    }

    public function test_reactivating_an_invalid_snapshot_is_refused_each_time(): void
    {
        $broken = $this->completeVersion(['validation_status' => 'incomplete']);

        $this->activate($broken)->assertExitCode(1);
        $this->activate($broken)->assertExitCode(1);

        $this->assertSame([], $this->activeIds());
        $this->assertSame(0, DB::table('quran_dataset_versions')->where('id', $broken)->value('is_active'));
    }

    public function test_activating_the_active_snapshot_is_idempotent(): void
    {
        $id = $this->completeVersion();

        $this->activate($id)->assertExitCode(0);
        $this->activate($id)->expectsOutputToContain('already active')->assertExitCode(0);

        $this->assertSame([$id], $this->activeIds());
    }

    public function test_activation_is_refused_when_the_digest_does_not_match(): void
    {
        $version = $this->completeVersion(['content_digest' => str_repeat('0', 64)]);

        $this->activate($version)
            ->expectsOutputToContain('content digest')
            ->assertExitCode(1);

        $this->assertSame([], $this->activeIds());
    }

    public function test_a_snapshot_without_a_digest_is_refused(): void
    {
        $version = $this->version(['validation_status' => 'pending']);
        $this->withChildren($version);

        DB::table('quran_dataset_versions')->where('id', $version)->update([
            'validation_status' => 'passed',
            'validation_report' => json_encode(['passed' => true, 'status' => 'passed', 'checks' => [], 'stats' => []]),
        ]);

        $this->activate($version)
            ->expectsOutputToContain('no content digest')
            ->assertExitCode(1);

        $this->assertSame([], $this->activeIds());
    }

    public function test_a_corrupt_snapshot_does_not_replace_the_active_one(): void
    {
        $good = $this->completeVersion(['checksum_sha256' => str_repeat('a', 64)]);
        $this->activate($good)->assertExitCode(0);

        $corrupt = $this->completeVersion([
            'checksum_sha256' => str_repeat('b', 64),
            'content_digest' => str_repeat('0', 64),
        ]);

        $this->activate($corrupt)->assertExitCode(1);

        $this->assertSame([$good], $this->activeIds());
        $this->assertSame($good, (new DatabaseQuranDataProvider)->datasetVersionId());
    }

    public function test_the_provider_serves_nothing_before_activation(): void
    {
        $this->completeVersion();

        $this->expectException(QuranDataUnavailable::class);

        (new DatabaseQuranDataProvider)->surahs();
    }

    public function test_the_provider_refuses_an_active_snapshot_that_is_not_passed(): void
    {
        $id = $this->completeVersion(['validation_status' => 'incomplete']);

        // Bypasses activation on purpose: the provider must not trust is_active alone.
        DB::table('quran_dataset_versions')->where('id', $id)->update(['is_active' => true]);

        $this->expectException(QuranDataUnavailable::class);

        (new DatabaseQuranDataProvider)->datasetVersionId();
    }
}
