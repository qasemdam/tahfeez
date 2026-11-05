<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Page geometry of one dataset snapshot (docs/quran-data-model.md §2.3).
 *
 * `lines_count` is the physical height of the page as published by the edition
 * — 8 on pages 1–2 and 15 on pages 3–604 for the Madinah 1405H print. It is the
 * measurement denominator and is never derived from the lines the ayah rows
 * happen to cover: 57 pages carry surah/basmala decoration, so coverage-based
 * counting reports 13 instead of 15 and overstates those pages by up to 15%.
 *
 * `line_offset` is the running sum of previous page heights. It exists for
 * ordering and audit, not as a measurement basis.
 *
 * `juz_number` comes from the source; `hizb_number` / `rub_number` are not in
 * the official field list and stay nullable — they are never guessed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_pages', function (Blueprint $table): void {
            $table->foreignId('dataset_version_id')
                ->constrained('quran_dataset_versions')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('page_number');

            $table->unsignedSmallInteger('lines_count');
            $table->unsignedInteger('line_offset');

            $table->unsignedSmallInteger('juz_number')->nullable();
            $table->unsignedSmallInteger('hizb_number')->nullable();
            $table->unsignedSmallInteger('rub_number')->nullable();

            $table->timestamps();

            $table->primary(['dataset_version_id', 'page_number']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE quran_pages ADD CONSTRAINT quran_pages_lines_check
                 CHECK (lines_count >= 1)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_pages');
    }
};
