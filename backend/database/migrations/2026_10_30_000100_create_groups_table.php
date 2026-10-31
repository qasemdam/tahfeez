<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Groups (حلقات) — the organising unit between a teacher and their students.
 *
 * Not defined in the Phase 0 schema; the design is recorded in
 * docs/open-decisions.md (OD-030). A group always belongs to one center and
 * one teacher, and has an active/inactive state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();

            $table->string('name', 120);
            $table->string('status', 16)->default('active');
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['center_id', 'status']);
            $table->index(['center_id', 'teacher_id', 'status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE "groups" ADD CONSTRAINT groups_status_check
                 CHECK (status IN (\'active\',\'inactive\'))'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
