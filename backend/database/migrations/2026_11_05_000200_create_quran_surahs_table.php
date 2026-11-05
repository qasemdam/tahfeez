<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The 114 surahs of one dataset snapshot (docs/quran-data-model.md §2.2).
 *
 * Keyed by `(dataset_version_id, id)` so the same surah exists once per version
 * and two editions never overwrite each other.
 *
 * `ayah_count` is the canonical count (7, 286, …), never a count of decoration
 * slots a third-party dataset happens to carry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_surahs', function (Blueprint $table): void {
            $table->foreignId('dataset_version_id')
                ->constrained('quran_dataset_versions')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('id');

            $table->string('name_ar', 64);
            $table->string('name_en', 64);
            $table->string('name_transliteration', 64)->nullable();
            $table->string('revelation_place', 16)->nullable();

            $table->unsignedSmallInteger('ayah_count');
            $table->unsignedSmallInteger('page_start');
            $table->unsignedSmallInteger('page_end');

            $table->timestamps();

            $table->primary(['dataset_version_id', 'id']);
            $table->index(['dataset_version_id', 'page_start']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE quran_surahs ADD CONSTRAINT quran_surahs_range_check
                 CHECK (page_end >= page_start)'
            );
            DB::statement(
                'ALTER TABLE quran_surahs ADD CONSTRAINT quran_surahs_ayah_count_check
                 CHECK (ayah_count > 0)'
            );
            DB::statement(
                "ALTER TABLE quran_surahs ADD CONSTRAINT quran_surahs_revelation_check
                 CHECK (revelation_place IS NULL OR revelation_place IN ('makkah','madinah'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_surahs');
    }
};
