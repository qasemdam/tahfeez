<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant root. Every business record hangs off a center (BR-TEN-01).
 *
 * `weekly_evaluation_start_day` is configuration, not a constant (BR-TEN-04):
 * the value is data, and historical periods keep their own boundaries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 80)->unique();
            $table->string('timezone', 64)->default('Asia/Riyadh');
            $table->string('locale', 5)->default('ar');

            // WeekStartDay enum: saturday … friday. Configured per center.
            $table->string('weekly_evaluation_start_day', 10)->default('saturday');
            $table->date('weekly_evaluation_start_day_effective_from')->nullable();

            // default required pages, rounding policy, feature flags…
            $table->json('settings')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centers');
    }
};
