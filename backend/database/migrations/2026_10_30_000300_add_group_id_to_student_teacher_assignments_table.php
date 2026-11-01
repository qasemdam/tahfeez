<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks which group produced an active teacher assignment (OD-030).
 *
 * Null means the assignment was made directly (the pre-groups path). A
 * group-driven assignment carries the group id so that leaving the group can
 * close exactly the assignment it created, without touching a manual one.
 *
 * Deliberately not a foreign key: assignment history must survive even if a
 * group row is ever hard-deleted during a data purge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_teacher_assignments', function (Blueprint $table): void {
            $table->unsignedBigInteger('group_id')->nullable()->after('teacher_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('student_teacher_assignments', function (Blueprint $table): void {
            $table->dropColumn('group_id');
        });
    }
};
