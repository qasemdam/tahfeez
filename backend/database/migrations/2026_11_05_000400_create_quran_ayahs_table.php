<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ayah coordinates of one dataset snapshot (docs/quran-data-model.md §2.4).
 *
 * Both ends of an ayah are stored. With only `page`, an ayah that crosses a page
 * break is ambiguous — `line_end = 15` may mean "bottom of this page" or
 * "continues on the next" — and the range stops being measurable without
 * inference (invariant I7).
 *
 * `id` is the global ayah id (1…6236) assigned deterministically at import.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_ayahs', function (Blueprint $table): void {
            $table->foreignId('dataset_version_id')
                ->constrained('quran_dataset_versions')
                ->cascadeOnDelete();

            $table->unsignedInteger('id');

            $table->unsignedSmallInteger('surah_id');
            $table->unsignedSmallInteger('number_in_surah');

            $table->text('text_uthmani');

            $table->unsignedSmallInteger('page_start');
            $table->unsignedSmallInteger('line_start');
            $table->unsignedSmallInteger('page_end');
            $table->unsignedSmallInteger('line_end');

            $table->unsignedSmallInteger('juz_number')->nullable();
            $table->boolean('is_sajda')->default(false);

            $table->timestamps();

            $table->primary(['dataset_version_id', 'id']);
            $table->unique(['dataset_version_id', 'surah_id', 'number_in_surah']);
            $table->index(['dataset_version_id', 'page_start', 'line_start']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE quran_ayahs ADD CONSTRAINT quran_ayahs_line_start_check
                 CHECK (line_start >= 1)'
            );
            DB::statement(
                'ALTER TABLE quran_ayahs ADD CONSTRAINT quran_ayahs_range_check
                 CHECK (page_end > page_start OR line_end >= line_start)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_ayahs');
    }
};
