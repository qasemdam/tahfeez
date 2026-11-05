<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A fingerprint of the *stored rows*, not of the source file (S6.3).
 *
 * `checksum_sha256` proves which file was imported. It says nothing about what
 * is in the tables afterwards: a row can be edited without touching the file,
 * and the checksum still matches. This column is the other half — a digest of
 * the snapshot's own content, written once the rows are in and compared on every
 * revalidation.
 *
 * Nullable because a failed import stores a snapshot row with no children: there
 * is no content to fingerprint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quran_dataset_versions', function (Blueprint $table): void {
            $table->char('content_digest', 64)->nullable()->after('checksum_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('quran_dataset_versions', function (Blueprint $table): void {
            $table->dropColumn('content_digest');
        });
    }
};
