<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Teacher assignment history (BR-STU-04).
 *
 * History, not a single FK: teacher-performance reports need the assignment
 * that was active at the time of a record.
 *
 * The "at most one active assignment" invariant is enforced by a partial
 * unique index on PostgreSQL and by the action + tests on SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_teacher_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();

            $table->date('assigned_at');
            $table->date('unassigned_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();

            $table->timestamps();

            $table->index(['center_id', 'teacher_id', 'unassigned_at']);
            $table->index(['student_id', 'unassigned_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX student_one_active_teacher
                 ON student_teacher_assignments (student_id)
                 WHERE unassigned_at IS NULL'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_teacher_assignments');
    }
};
