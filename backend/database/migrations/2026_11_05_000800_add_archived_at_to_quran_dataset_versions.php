<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A safe way to retire a snapshot without weakening the immutability guard.
 *
 * Accepted snapshots cannot be deleted — that is the point of 000700 — but an
 * operator still needs a way to take a mistaken import out of the working set.
 * Hard deletion would require dropping triggers inside a transaction, which is
 * exactly the kind of temporary hole the guard exists to prevent.
 *
 * So the accepted path is a tombstone: `archived_at` is set, the rows stay where
 * they are, and nothing reads an archived snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quran_dataset_versions', function (Blueprint $table): void {
            $table->timestampTz('archived_at')->nullable()->after('is_active');
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('quran_dataset_versions', function (Blueprint $table): void {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
