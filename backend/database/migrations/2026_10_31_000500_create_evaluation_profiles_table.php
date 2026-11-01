<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-student evaluation profile (BR-EVL-05, OD-038). The profile is stable;
 * its versions carry the requirement and the rule-set snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['center_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_profiles');
    }
};
