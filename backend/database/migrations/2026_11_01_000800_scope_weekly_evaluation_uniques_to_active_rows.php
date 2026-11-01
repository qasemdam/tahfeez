<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A soft-deleted weekly evaluation is *removed*, not a tombstone that blocks
 * the week forever (OD-038). The uniqueness constraints therefore only cover
 * live rows:
 *
 *   unique (center_id, student_id, week_key) WHERE deleted_at IS NULL
 *   unique (center_id, client_uuid)          WHERE deleted_at IS NULL
 *
 * Without the partial predicate a deleted week could never be recomputed — the
 * insert would hit the unique index and surface as a 500.
 */
return new class extends Migration
{
    private const STUDENT_WEEK_INDEX = 'weekly_evaluations_student_week_active_unique';

    private const CLIENT_UUID_INDEX = 'weekly_evaluations_client_uuid_active_unique';

    public function up(): void
    {
        Schema::table('weekly_evaluations', function (Blueprint $table): void {
            $table->dropUnique('weekly_evaluations_center_id_student_id_week_key_unique');
            $table->dropUnique('weekly_evaluations_center_id_client_uuid_unique');
        });

        if ($this->supportsPartialIndexes()) {
            DB::statement(
                'CREATE UNIQUE INDEX '.self::STUDENT_WEEK_INDEX.'
                 ON weekly_evaluations (center_id, student_id, week_key)
                 WHERE deleted_at IS NULL'
            );

            DB::statement(
                'CREATE UNIQUE INDEX '.self::CLIENT_UUID_INDEX.'
                 ON weekly_evaluations (center_id, client_uuid)
                 WHERE deleted_at IS NULL'
            );

            return;
        }

        // MySQL has no partial indexes; the live-row rule cannot be expressed.
        Schema::table('weekly_evaluations', function (Blueprint $table): void {
            $table->unique(['center_id', 'student_id', 'week_key']);
            $table->unique(['center_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        if ($this->supportsPartialIndexes()) {
            DB::statement('DROP INDEX IF EXISTS '.self::STUDENT_WEEK_INDEX);
            DB::statement('DROP INDEX IF EXISTS '.self::CLIENT_UUID_INDEX);
        } else {
            Schema::table('weekly_evaluations', function (Blueprint $table): void {
                $table->dropUnique(['center_id', 'student_id', 'week_key']);
                $table->dropUnique(['center_id', 'client_uuid']);
            });
        }

        Schema::table('weekly_evaluations', function (Blueprint $table): void {
            $table->unique(['center_id', 'student_id', 'week_key']);
            $table->unique(['center_id', 'client_uuid']);
        });
    }

    private function supportsPartialIndexes(): bool
    {
        return in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true);
    }
};
