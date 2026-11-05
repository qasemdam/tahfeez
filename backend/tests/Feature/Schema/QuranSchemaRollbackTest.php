<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The Quran master-data migrations must be reversible.
 *
 * Each file is rolled back by path, newest first, rather than by step count or
 * by `migrate:reset`:
 *
 *  - a step count drifts the moment another migration is added after them;
 *  - `migrate:reset` walks unrelated migrations, and one of them
 *    (`add_group_id_to_student_teacher_assignments`) cannot roll back on SQLite.
 */
class QuranSchemaRollbackTest extends TestCase
{
    use DatabaseMigrations;

    private const TABLES = [
        'quran_dataset_versions',
        'quran_surahs',
        'quran_pages',
        'quran_ayahs',
    ];

    private const MIGRATIONS = [
        '2026_11_05_000800_add_archived_at_to_quran_dataset_versions.php',
        '2026_11_05_000700_protect_accepted_quran_snapshots.php',
        '2026_11_05_000600_add_content_digest_to_quran_dataset_versions.php',
        '2026_11_05_000500_constrain_quran_dataset_versions.php',
        '2026_11_05_000400_create_quran_ayahs_table.php',
        '2026_11_05_000300_create_quran_pages_table.php',
        '2026_11_05_000200_create_quran_surahs_table.php',
        '2026_11_05_000100_create_quran_dataset_versions_table.php',
    ];

    public function test_the_quran_migrations_roll_back_cleanly(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table} before rollback");
        }

        foreach (self::MIGRATIONS as $migration) {
            Artisan::call('migrate:rollback', [
                '--path' => 'database/migrations/'.$migration,
                '--force' => true,
            ]);
        }

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "Table {$table} survived the rollback");
        }

        // Re-applying restores the same shape, so a failed deploy can be retried.
        Artisan::call('migrate', ['--force' => true]);

        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} was not restored");
        }
    }
}
