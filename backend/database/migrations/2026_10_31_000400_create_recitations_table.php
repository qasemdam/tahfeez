<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recitation records — a standalone performance event (BR-REC-01/05,
 * OD-036). It records the range and quality, not a page amount: no
 * `amount_pages`/`amount_lines`, and no rule set or computed score yet
 * (BR-REC-02 forbids a hard-coded formula).
 *
 * `group_id` is the historical group at recording time; the page range is
 * derived from the provider, so the dataset version is stored for provenance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();

            $table->date('recitation_date');

            $table->unsignedSmallInteger('surah_id');
            $table->unsignedInteger('from_ayah_id');
            $table->unsignedInteger('to_ayah_id');
            $table->unsignedSmallInteger('from_ayah_number');
            $table->unsignedSmallInteger('to_ayah_number');
            $table->unsignedSmallInteger('from_page')->nullable();
            $table->unsignedSmallInteger('to_page')->nullable();

            $table->string('grade', 16)->nullable();
            $table->unsignedSmallInteger('hifz_errors')->nullable();
            $table->unsignedSmallInteger('tajweed_errors')->nullable();
            $table->unsignedSmallInteger('reading_errors')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('quran_dataset_version_id')->nullable();
            $table->string('quran_dataset_version', 64)->nullable();

            $table->uuid('client_uuid')->nullable();
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['center_id', 'client_uuid']);
            $table->index(['center_id', 'student_id', 'recitation_date']);
            $table->index(['center_id', 'teacher_id', 'recitation_date']);
            $table->index(['center_id', 'recitation_date']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE recitations ADD CONSTRAINT recitations_grade_check
                 CHECK (grade IS NULL OR grade IN ('excellent','very_good','good','needs_review'))"
            );
            DB::statement(
                'ALTER TABLE recitations ADD CONSTRAINT recitations_ayah_check
                 CHECK (to_ayah_id >= from_ayah_id)'
            );
            DB::statement(
                'ALTER TABLE recitations ADD CONSTRAINT recitations_page_check
                 CHECK (from_page IS NULL OR to_page IS NULL OR to_page >= from_page)'
            );
            DB::statement(
                'ALTER TABLE recitations ADD CONSTRAINT recitations_errors_check
                 CHECK (
                    (hifz_errors IS NULL OR hifz_errors >= 0)
                    AND (tajweed_errors IS NULL OR tajweed_errors >= 0)
                    AND (reading_errors IS NULL OR reading_errors >= 0)
                 )'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recitations');
    }
};
