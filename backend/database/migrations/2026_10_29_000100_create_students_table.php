<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Student record (BR-STU-01 … BR-STU-08).
 *
 * `version` supports optimistic concurrency for the sync layer later.
 * `client_uuid` is the offline-creation identity (ADR-0006).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();

            $table->string('code', 24)->nullable();
            $table->string('full_name', 160);

            // Age is derived from birth_date where available; age_years is the
            // fallback captured at enrollment (OD-001).
            $table->date('birth_date')->nullable();
            $table->unsignedSmallInteger('age_years')->nullable();

            $table->string('grade_level', 64)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('guardian_name', 160);
            $table->string('guardian_phone', 32)->nullable();

            $table->date('enrolled_at');
            $table->string('status', 16)->default('active');
            $table->text('notes')->nullable();

            $table->uuid('client_uuid')->nullable();
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['center_id', 'client_uuid']);
            $table->unique(['center_id', 'code']);
            $table->index(['center_id', 'status']);
            $table->index(['center_id', 'full_name']);
        });

        // PostgreSQL gets a real CHECK constraint. SQLite cannot add one after
        // the fact, so the invariant is enforced by validation + tests there.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE students ADD CONSTRAINT students_status_check
                 CHECK (status IN ('active','inactive'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
