<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two constraints the import engine needs (S3 review, F3 and F4).
 *
 * 1. A checksum may appear once among *successfully imported* snapshots. The
 *    application-level duplicate guard is a check-then-insert; two concurrent
 *    imports of the same file can both pass it. This index is what actually
 *    prevents the second one. Failed attempts are deliberately not covered —
 *    a broken file must be retryable.
 *
 * 2. `incomplete` joins the status vocabulary: a dataset that parsed and passed
 *    every structural check but skipped a critical one (the golden-sample checks
 *    V-10/V-12, which have no fixture yet) is imported but not activatable.
 *    `DatabaseQuranDataProvider` only reads `passed`, so an incomplete snapshot
 *    is stored without ever being served.
 *
 * The checksum index is partial, supported by SQLite and PostgreSQL. The status
 * constraint is PostgreSQL-only, like every other CHECK in this schema.
 */
return new class extends Migration
{
    private const CHECKSUM_INDEX = 'quran_dataset_versions_checksum_imported';

    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX '.self::CHECKSUM_INDEX.'
             ON quran_dataset_versions (checksum_sha256)
             WHERE validation_status IN (\'passed\', \'incomplete\')'
        );

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE quran_dataset_versions DROP CONSTRAINT IF EXISTS quran_dataset_versions_status_check');
            DB::statement(
                "ALTER TABLE quran_dataset_versions ADD CONSTRAINT quran_dataset_versions_status_check
                 CHECK (validation_status IN ('pending','passed','incomplete','failed'))"
            );
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::CHECKSUM_INDEX);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE quran_dataset_versions DROP CONSTRAINT IF EXISTS quran_dataset_versions_status_check');
            DB::statement(
                "ALTER TABLE quran_dataset_versions ADD CONSTRAINT quran_dataset_versions_status_check
                 CHECK (validation_status IN ('pending','passed','failed'))"
            );
        }
    }
};
