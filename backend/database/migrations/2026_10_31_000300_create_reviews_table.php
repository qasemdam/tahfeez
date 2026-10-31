<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Review (Murajaʿah) records — an independent entity from new memorization
 * (BR-REV-01, BR-REV-02). It shares the measurement pipeline but never feeds
 * the new-memorization totals (BR-REV-04).
 *
 * `group_id` is the historical group at recording time; `week_key` is deferred
 * with the evaluation slice (OD-035).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();

            $table->date('review_date');

            $table->unsignedSmallInteger('surah_id')->nullable();
            $table->unsignedInteger('from_ayah_id')->nullable();
            $table->unsignedInteger('to_ayah_id')->nullable();
            $table->unsignedSmallInteger('from_ayah_number')->nullable();
            $table->unsignedSmallInteger('to_ayah_number')->nullable();
            $table->unsignedSmallInteger('from_page')->nullable();
            $table->unsignedSmallInteger('to_page')->nullable();

            $table->decimal('amount_pages', 8, 4);
            $table->unsignedInteger('amount_lines')->nullable();
            $table->string('amount_source', 12);
            $table->unsignedBigInteger('quran_dataset_version_id')->nullable();
            $table->string('quran_dataset_version', 64)->nullable();

            $table->string('grade', 16)->nullable();
            $table->unsignedSmallInteger('hifz_errors')->nullable();
            $table->unsignedSmallInteger('tajweed_errors')->nullable();
            $table->unsignedSmallInteger('reading_errors')->nullable();
            $table->text('notes')->nullable();

            $table->uuid('client_uuid')->nullable();
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['center_id', 'client_uuid']);
            $table->index(['center_id', 'student_id', 'review_date']);
            $table->index(['center_id', 'teacher_id', 'review_date']);
            $table->index(['center_id', 'review_date']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE reviews ADD CONSTRAINT reviews_amount_check
                 CHECK (amount_pages >= 0)'
            );
            DB::statement(
                "ALTER TABLE reviews ADD CONSTRAINT reviews_source_check
                 CHECK (amount_source IN ('CALCULATED','MANUAL'))"
            );
            DB::statement(
                'ALTER TABLE reviews ADD CONSTRAINT reviews_ayah_check
                 CHECK (from_ayah_id IS NULL OR to_ayah_id IS NULL OR to_ayah_id >= from_ayah_id)'
            );
            DB::statement(
                "ALTER TABLE reviews ADD CONSTRAINT reviews_grade_check
                 CHECK (grade IS NULL OR grade IN ('excellent','very_good','good','needs_review'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
