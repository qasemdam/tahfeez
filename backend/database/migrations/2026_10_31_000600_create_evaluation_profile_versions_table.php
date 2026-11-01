<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable profile versions (BR-EVL-09). A change inserts a new version with
 * `effective_from`; historical weeks keep the version effective at their
 * start. The rule set is embedded here until the template tables ship
 * (OD-038), so the weekly evaluation can snapshot it without a join.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_profile_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('evaluation_profiles')->cascadeOnDelete();
            $table->unsignedInteger('version_number');

            $table->decimal('required_pages', 6, 2)->default('1.00');
            $table->date('effective_from');

            // { version, criteria[], recitation_grade_scores{} } — the rule
            // set snapshot the engine consumes.
            $table->json('rule_set')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();

            $table->timestamps();

            $table->unique(['profile_id', 'version_number']);
            $table->index(['profile_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_profile_versions');
    }
};
