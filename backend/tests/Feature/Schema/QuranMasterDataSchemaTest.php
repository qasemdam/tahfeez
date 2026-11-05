<?php

namespace Tests\Feature\Schema;

use App\Domain\Groups\Models\Group;
use App\Domain\Memorization\Models\Memorization;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The Quran master-data schema (docs/quran-data-model.md §2).
 *
 * These tests cover the shape of the tables only: no Quran text is inserted and
 * no dataset is imported. The rows used here are structural fixtures.
 */
class QuranMasterDataSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function checksum(): string
    {
        return str_repeat('a', 64);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function version(array $overrides = []): int
    {
        return (int) DB::table('quran_dataset_versions')->insertGetId([
            'source' => 'test',
            'source_url' => 'https://example.test/dataset',
            'source_version' => 'v1',
            'edition' => 'madinah_1405h_hafs_uthmani',
            'source_format' => 'json',
            'downloaded_at' => now(),
            'imported_at' => now(),
            'checksum_sha256' => $this->checksum(),
            'validation_status' => 'pending',
            'validation_report' => json_encode([]),
            'is_active' => false,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
            ...$overrides,
        ]);
    }

    public function test_the_four_master_data_tables_exist(): void
    {
        foreach ([
            'quran_dataset_versions',
            'quran_surahs',
            'quran_pages',
            'quran_ayahs',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
        }
    }

    public function test_the_documented_columns_exist(): void
    {
        $versions = Schema::getColumnListing('quran_dataset_versions');
        foreach ([
            'id', 'source', 'source_url', 'source_version', 'edition', 'source_format',
            'downloaded_at', 'imported_at', 'checksum_sha256', 'validation_status',
            'validation_report', 'is_active', 'notes',
        ] as $column) {
            $this->assertContains($column, $versions, "Missing column {$column}");
        }

        $ayahs = Schema::getColumnListing('quran_ayahs');
        foreach ([
            'dataset_version_id', 'id', 'surah_id', 'number_in_surah', 'text_uthmani',
            'page_start', 'line_start', 'page_end', 'line_end', 'juz_number', 'is_sajda',
        ] as $column) {
            $this->assertContains($column, $ayahs, "Missing column {$column}");
        }

        $pages = Schema::getColumnListing('quran_pages');
        foreach (['dataset_version_id', 'page_number', 'lines_count', 'line_offset', 'juz_number', 'hizb_number', 'rub_number'] as $column) {
            $this->assertContains($column, $pages, "Missing column {$column}");
        }
    }

    public function test_the_edition_is_required_and_has_no_default(): void
    {
        $this->expectException(QueryException::class);

        DB::table('quran_dataset_versions')->insert([
            'source' => 'test',
            'source_url' => 'https://example.test/dataset',
            'source_version' => 'v1',
            'source_format' => 'json',
            'downloaded_at' => now(),
            'imported_at' => now(),
            'checksum_sha256' => $this->checksum(),
            'validation_status' => 'pending',
            'validation_report' => json_encode([]),
            'is_active' => false,
        ]);
    }

    public function test_several_dataset_versions_may_coexist(): void
    {
        $first = $this->version(['edition' => 'madinah_1405h_hafs_uthmani']);
        $second = $this->version(['edition' => 'madinah_1441h_hafs_uthmani', 'source_version' => 'v2']);

        DB::table('quran_surahs')->insert([
            ['dataset_version_id' => $first, 'id' => 1, 'name_ar' => 'الفاتحة', 'name_en' => 'Al-Fatihah', 'ayah_count' => 7, 'page_start' => 1, 'page_end' => 1],
            ['dataset_version_id' => $second, 'id' => 1, 'name_ar' => 'الفاتحة', 'name_en' => 'Al-Fatihah', 'ayah_count' => 7, 'page_start' => 1, 'page_end' => 1],
        ]);

        $this->assertSame(2, DB::table('quran_surahs')->where('id', 1)->count());
        $this->assertSame(2, DB::table('quran_dataset_versions')->count());
    }

    public function test_only_one_dataset_version_can_be_active(): void
    {
        $this->version(['is_active' => true]);

        $this->expectException(QueryException::class);

        $this->version(['is_active' => true]);
    }

    public function test_an_inactive_version_does_not_conflict(): void
    {
        $this->version(['is_active' => true]);
        $this->version(['is_active' => false]);
        $this->version(['is_active' => false]);

        $this->assertSame(1, DB::table('quran_dataset_versions')->where('is_active', true)->count());
        $this->assertSame(3, DB::table('quran_dataset_versions')->count());
    }

    public function test_a_page_and_an_ayah_are_keyed_by_version(): void
    {
        $first = $this->version();
        $second = $this->version(['source_version' => 'v2']);

        foreach ([$first, $second] as $versionId) {
            DB::table('quran_pages')->insert([
                'dataset_version_id' => $versionId,
                'page_number' => 1,
                'lines_count' => 8,
                'line_offset' => 0,
            ]);
        }

        $this->assertSame(2, DB::table('quran_pages')->where('page_number', 1)->count());
    }

    public function test_an_ayah_number_is_unique_within_its_surah_and_version(): void
    {
        $version = $this->version();

        $row = [
            'dataset_version_id' => $version,
            'id' => 1,
            'surah_id' => 1,
            'number_in_surah' => 1,
            'text_uthmani' => 'بِسْمِ اللَّهِ',
            'page_start' => 1,
            'line_start' => 1,
            'page_end' => 1,
            'line_end' => 1,
        ];

        DB::table('quran_ayahs')->insert($row);

        $this->expectException(QueryException::class);

        DB::table('quran_ayahs')->insert([...$row, 'id' => 2]);
    }

    public function test_deleting_a_version_removes_its_children(): void
    {
        $version = $this->version();

        DB::table('quran_surahs')->insert([
            'dataset_version_id' => $version, 'id' => 1, 'name_ar' => 'الفاتحة', 'name_en' => 'Al-Fatihah',
            'ayah_count' => 7, 'page_start' => 1, 'page_end' => 1,
        ]);
        DB::table('quran_pages')->insert([
            'dataset_version_id' => $version, 'page_number' => 1, 'lines_count' => 8, 'line_offset' => 0,
        ]);
        DB::table('quran_ayahs')->insert([
            'dataset_version_id' => $version, 'id' => 1, 'surah_id' => 1, 'number_in_surah' => 1,
            'text_uthmani' => 'بِسْمِ اللَّهِ', 'page_start' => 1, 'line_start' => 1, 'page_end' => 1, 'line_end' => 1,
        ]);

        DB::table('quran_dataset_versions')->where('id', $version)->delete();

        $this->assertSame(0, DB::table('quran_surahs')->count());
        $this->assertSame(0, DB::table('quran_pages')->count());
        $this->assertSame(0, DB::table('quran_ayahs')->count());
    }

    public function test_historical_records_keep_their_orphan_dataset_version(): void
    {
        $center = Center::factory()->create();
        $teacher = User::factory()->teacher()->for($center)->create();
        $group = Group::factory()->for($center)->create(['teacher_id' => $teacher->id]);
        $student = Student::factory()->for($center)->create();

        $record = Memorization::factory()->for($center)->create([
            'student_id' => $student->id,
            'group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'quran_dataset_version_id' => 1,
            'quran_dataset_version' => 'fixture-dev-v1',
        ]);

        // No dataset version row exists for id 1 — the record must still stand,
        // and the historical text marker must be preserved alongside it.
        $this->assertSame(0, DB::table('quran_dataset_versions')->count());

        $fresh = $record->fresh();
        $this->assertSame(1, $fresh->quran_dataset_version_id);
        $this->assertSame('fixture-dev-v1', $fresh->quran_dataset_version);
    }

    public function test_the_record_tables_keep_their_columns_and_gain_no_foreign_key(): void
    {
        foreach (['memorizations', 'reviews', 'recitations'] as $table) {
            $columns = Schema::getColumnListing($table);

            $this->assertContains('quran_dataset_version_id', $columns);
            $this->assertContains('quran_dataset_version', $columns);

            $foreignKeys = collect(Schema::getForeignKeys($table))
                ->pluck('columns')
                ->flatten()
                ->all();

            $this->assertNotContains(
                'quran_dataset_version_id',
                $foreignKeys,
                "{$table}.quran_dataset_version_id must not be constrained while historical orphans exist.",
            );
        }
    }
}
