<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Student ↔ group membership history (OD-030).
 *
 * `left_at = null` means "currently in this group". A student has at most one
 * active membership; moving between groups closes the old row and inserts a
 * new one, so history is never overwritten (same principle as BR-STU-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            $table->date('joined_at');
            $table->date('left_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();

            $table->timestamps();

            $table->index(['center_id', 'group_id', 'left_at']);
            $table->index(['student_id', 'left_at']);
        });

        // Partial unique index works on both PostgreSQL and SQLite.
        DB::statement(
            'CREATE UNIQUE INDEX group_memberships_one_active
             ON group_memberships (student_id)
             WHERE left_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('group_memberships');
    }
};
