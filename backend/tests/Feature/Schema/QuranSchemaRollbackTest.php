<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The four master-data migrations must be reversible in isolation: they are the
 * last four, and nothing else depends on them yet.
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

    public function test_the_four_migrations_roll_back_cleanly(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table} before rollback");
        }

        // The four migrations are the last four in the run order.
        Artisan::call('migrate:rollback', ['--step' => 4]);

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "Table {$table} survived the rollback");
        }

        // Re-applying restores the same shape, so a failed deploy can be retried.
        Artisan::call('migrate');

        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} was not restored");
        }
    }
}
