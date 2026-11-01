<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historical weekly evaluation (BR-EVL-08/09/10/16, OD-038).
 *
 * The scalar columns are for querying; `rules_snapshot` and `result_snapshot`
 * make the historical result explainable without re-running the engine or
 * reading the current profile/center settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();

            $table->date('week_key');
            $table->date('week_start_date');
            $table->date('week_end_date');
            $table->string('weekly_evaluation_start_day', 10);

            $table->foreignId('evaluation_profile_version_id')
                ->nullable()
                ->constrained('evaluation_profile_versions')
                ->nullOnDelete();
            $table->unsignedInteger('evaluation_profile_version')->nullable();
            $table->unsignedBigInteger('template_version_id')->nullable();

            $table->decimal('required_pages', 6, 2);
            $table->decimal('actual_pages', 8, 4);
            $table->decimal('achievement_percentage', 8, 2);
            $table->decimal('weighted_score', 6, 2)->nullable();
            $table->boolean('is_complete')->default(true);
            $table->string('status', 12)->default('computed');

            $table->json('rules_snapshot');
            $table->json('result_snapshot');

            $table->uuid('client_uuid')->nullable();
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['center_id', 'student_id', 'week_key']);
            $table->unique(['center_id', 'client_uuid']);
            $table->index(['center_id', 'week_start_date']);
            $table->index(['center_id', 'group_id', 'week_start_date']);
            $table->index(['center_id', 'student_id', 'week_start_date']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE weekly_evaluations ADD CONSTRAINT weekly_evaluations_required_check
                 CHECK (required_pages > 0)'
            );
            DB::statement(
                'ALTER TABLE weekly_evaluations ADD CONSTRAINT weekly_evaluations_actual_check
                 CHECK (actual_pages >= 0)'
            );
            DB::statement(
                'ALTER TABLE weekly_evaluations ADD CONSTRAINT weekly_evaluations_achievement_check
                 CHECK (achievement_percentage >= 0)'
            );
            DB::statement(
                'ALTER TABLE weekly_evaluations ADD CONSTRAINT weekly_evaluations_weighted_check
                 CHECK (weighted_score IS NULL OR (weighted_score >= 0 AND weighted_score <= 100))'
            );
            DB::statement(
                "ALTER TABLE weekly_evaluations ADD CONSTRAINT weekly_evaluations_status_check
                 CHECK (status IN ('computed','approved'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_evaluations');
    }
};
