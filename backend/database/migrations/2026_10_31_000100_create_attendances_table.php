<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance (BR-ATT-01 … BR-ATT-06, OD-007, OD-032).
 *
 * One record per student per day: `unique (center_id, student_id,
 * attendance_date)`. `group_id` stores the group the student was in **at the
 * time of recording**, not their current group — a later transfer must not
 * rewrite history. `group_id` is protected with RESTRICT so a hard purge
 * cannot silently drop the historical reference; groups are soft-deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();

            $table->date('attendance_date');
            $table->string('status', 20);
            $table->unsignedSmallInteger('late_minutes')->nullable();
            $table->text('notes')->nullable();

            // Offline-ready: a client may retry the same operation safely.
            $table->uuid('client_uuid')->nullable();
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['center_id', 'student_id', 'attendance_date']);
            $table->unique(['center_id', 'client_uuid']);
            $table->index(['center_id', 'attendance_date', 'status']);
            $table->index(['center_id', 'group_id', 'attendance_date']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE attendances ADD CONSTRAINT attendances_status_check
                 CHECK (status IN ('present','excused_absence','unexcused_absence','late'))"
            );
            DB::statement(
                'ALTER TABLE attendances ADD CONSTRAINT attendances_late_minutes_check
                 CHECK (status <> \'late\' OR late_minutes IS NULL OR late_minutes >= 0)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
