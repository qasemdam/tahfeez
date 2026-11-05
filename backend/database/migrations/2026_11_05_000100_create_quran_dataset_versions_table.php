<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One immutable snapshot of a Quran dataset (docs/quran-data-model.md §2.1).
 *
 * `edition` is mandatory and has no default: page and line coordinates are
 * edition-specific, so a dataset without a declared edition cannot be activated
 * and must never be assumed to be the Madinah 1405H print (OD-026, OD-029).
 *
 * A snapshot is written once by the importer and never updated; a new import
 * creates a new row (invariants I3/I5).
 *
 * `timestamps()` is added on top of the documented fields because every table in
 * this schema carries them and Eloquent expects them; it is a convention, not a
 * change to the data model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_dataset_versions', function (Blueprint $table): void {
            $table->id();

            $table->string('source', 32);
            $table->string('source_url', 255);
            $table->string('source_version', 64);
            $table->string('edition', 32);
            $table->string('source_format', 8);

            $table->timestampTz('downloaded_at');
            $table->timestampTz('imported_at');

            $table->char('checksum_sha256', 64);

            $table->string('validation_status', 12)->default('pending');
            $table->json('validation_report');

            $table->boolean('is_active')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();
        });

        // Exactly one active snapshot. A partial unique index works on both
        // PostgreSQL and SQLite, the same pattern the membership tables use.
        DB::statement(
            'CREATE UNIQUE INDEX quran_dataset_versions_one_active
             ON quran_dataset_versions (is_active)
             WHERE is_active'
        );

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE quran_dataset_versions ADD CONSTRAINT quran_dataset_versions_status_check
                 CHECK (validation_status IN ('pending','passed','failed'))"
            );
            DB::statement(
                'ALTER TABLE quran_dataset_versions ADD CONSTRAINT quran_dataset_versions_checksum_check
                 CHECK (char_length(checksum_sha256) = 64)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_dataset_versions');
    }
};
