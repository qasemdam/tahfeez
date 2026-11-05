<?php

namespace Tests\Feature\Quran;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Quran\SeedsSnapshots;
use Tests\TestCase;

/**
 * Forging an accepted snapshot, or smuggling rows into one, must fail at the
 * database — not merely in the application.
 */
class SnapshotForgeryTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSnapshots;

    private function accepted(): int
    {
        return $this->seedSnapshotWithChildren('passed', $this->tinyPayload());
    }

    public function test_the_status_cannot_be_flipped_to_passed_by_hand(): void
    {
        $version = $this->seedSnapshotWithChildren('incomplete', $this->tinyPayload());

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/immutable/');

        DB::table('quran_dataset_versions')->where('id', $version)->update(['validation_status' => 'passed']);
    }

    public function test_a_passed_status_cannot_be_reverted_either(): void
    {
        $version = $this->accepted();

        $this->expectException(QueryException::class);

        DB::table('quran_dataset_versions')->where('id', $version)->update(['validation_status' => 'incomplete']);
    }

    public function test_the_validation_report_cannot_be_rewritten(): void
    {
        $version = $this->accepted();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/immutable/');

        DB::table('quran_dataset_versions')->where('id', $version)
            ->update(['validation_report' => json_encode(['passed' => true])]);
    }

    public function test_the_content_digest_cannot_be_rewritten(): void
    {
        $version = $this->accepted();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/immutable/');

        DB::table('quran_dataset_versions')->where('id', $version)
            ->update(['content_digest' => str_repeat('0', 64)]);
    }

    public function test_the_identity_of_the_snapshot_cannot_be_rewritten(): void
    {
        $version = $this->accepted();

        $this->expectException(QueryException::class);

        DB::table('quran_dataset_versions')->where('id', $version)->update(['edition' => 'something_else']);
    }

    public function test_rows_cannot_be_inserted_into_an_accepted_snapshot(): void
    {
        $version = $this->accepted();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/immutable/');

        DB::table('quran_ayahs')->insert([
            'dataset_version_id' => $version,
            'id' => 900,
            'surah_id' => 1,
            'number_in_surah' => 9,
            'text_uthmani' => 'صف مُقحم',
            'page_start' => 1, 'line_start' => 1, 'page_end' => 1, 'line_end' => 1,
        ]);
    }

    public function test_a_row_cannot_be_moved_into_an_accepted_snapshot(): void
    {
        $accepted = $this->accepted();

        $pending = $this->seedVersion('pending');
        $this->insertPayload($pending, [
            'surahs' => [['id' => 9, 'name_ar' => 'س', 'name_en' => 'S', 'ayah_count' => 1, 'page_start' => 1, 'page_end' => 1]],
            'pages' => [['page_number' => 1, 'lines_count' => 8, 'line_offset' => 0, 'juz_number' => 1]],
            'ayahs' => [['id' => 900, 'surah_id' => 9, 'number_in_surah' => 1, 'text_uthmani' => 'ن', 'page_start' => 1, 'line_start' => 1, 'page_end' => 1, 'line_end' => 1, 'juz_number' => 1, 'is_sajda' => false]],
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/immutable/');

        DB::table('quran_ayahs')->where('dataset_version_id', $pending)->where('id', 900)
            ->update(['dataset_version_id' => $accepted]);
    }

    public function test_activation_state_may_still_change(): void
    {
        $version = $this->accepted();

        DB::table('quran_dataset_versions')->where('id', $version)->update(['is_active' => true]);

        $this->assertSame(1, DB::table('quran_dataset_versions')->where('id', $version)->value('is_active'));
    }

    public function test_an_unsupported_driver_is_refused_explicitly(): void
    {
        $migration = require database_path('migrations/2026_11_05_000700_protect_accepted_quran_snapshots.php');

        DB::shouldReceive('getDriverName')->andReturn('mysql');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/PostgreSQL and SQLite only/');

        $migration->up();
    }
}
