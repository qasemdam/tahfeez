<?php

namespace Tests\Feature\Quran;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Quran\SeedsSnapshots;
use Tests\TestCase;

/**
 * An accepted snapshot is frozen in the database, not only in the application.
 */
class SnapshotImmutabilityTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSnapshots;

    private function passedSnapshot(): int
    {
        $version = $this->seedVersion('passed');
        $this->insertPayload($version, $this->tinyPayload());

        return $version;
    }

    public function test_an_ayah_of_a_passed_snapshot_cannot_be_updated(): void
    {
        $version = $this->passedSnapshot();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/immutable/');

        DB::table('quran_ayahs')->where('dataset_version_id', $version)->where('id', 1)
            ->update(['text_uthmani' => 'تعديل']);
    }

    public function test_an_ayah_of_a_passed_snapshot_cannot_be_deleted(): void
    {
        $version = $this->passedSnapshot();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/immutable/');

        DB::table('quran_ayahs')->where('dataset_version_id', $version)->where('id', 1)->delete();
    }

    public function test_a_surah_of_a_passed_snapshot_cannot_be_updated(): void
    {
        $version = $this->passedSnapshot();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/immutable/');

        DB::table('quran_surahs')->where('dataset_version_id', $version)->where('id', 1)
            ->update(['name_ar' => 'تعديل']);
    }

    public function test_a_page_of_a_passed_snapshot_cannot_be_updated(): void
    {
        $version = $this->passedSnapshot();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/immutable/');

        DB::table('quran_pages')->where('dataset_version_id', $version)->where('page_number', 1)
            ->update(['lines_count' => 9]);
    }

    public function test_a_passed_snapshot_cannot_be_deleted(): void
    {
        $version = $this->passedSnapshot();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/immutable/');

        DB::table('quran_dataset_versions')->where('id', $version)->delete();
    }

    public function test_an_incomplete_snapshot_is_protected_too(): void
    {
        $version = $this->seedVersion('incomplete');
        $this->insertPayload($version, $this->tinyPayload());

        $this->expectException(QueryException::class);

        DB::table('quran_ayahs')->where('dataset_version_id', $version)->where('id', 1)
            ->update(['text_uthmani' => 'تعديل']);
    }

    public function test_a_pending_snapshot_stays_editable(): void
    {
        $version = $this->seedVersion('pending');
        $this->insertPayload($version, $this->tinyPayload());

        DB::table('quran_ayahs')->where('dataset_version_id', $version)->where('id', 1)
            ->update(['text_uthmani' => 'تعديل']);

        $this->assertSame('تعديل', DB::table('quran_ayahs')
            ->where('dataset_version_id', $version)->where('id', 1)->value('text_uthmani'));
    }

    public function test_a_failed_snapshot_can_be_removed(): void
    {
        $version = $this->seedVersion('failed');

        DB::table('quran_dataset_versions')->where('id', $version)->delete();

        $this->assertSame(0, DB::table('quran_dataset_versions')->where('id', $version)->count());
    }

    public function test_activation_may_still_flip_is_active(): void
    {
        // Activation updates the versions row; only DELETE is frozen there.
        $version = $this->passedSnapshot();

        DB::table('quran_dataset_versions')->where('id', $version)->update(['is_active' => true]);

        $this->assertSame(1, DB::table('quran_dataset_versions')->where('id', $version)->value('is_active'));
    }
}
