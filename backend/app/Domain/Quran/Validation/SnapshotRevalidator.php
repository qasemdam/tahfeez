<?php

namespace App\Domain\Quran\Validation;

use App\Domain\Quran\Exceptions\QuranDataUnavailable;
use App\Domain\Quran\Import\ContentDigest;
use App\Domain\Quran\Import\DatasetValidator;
use App\Domain\Quran\Import\SnapshotReader;
use Illuminate\Support\Facades\DB;

/**
 * Re-runs validation against a snapshot that is already in the database (S6.2).
 *
 * It exists because the import report describes the payload *before* it was
 * written. Once the rows are in, nothing re-checks them: the provider reads
 * `validation_status` and trusts it. This service closes that gap without
 * importing anything and without writing anything — it reads the snapshot,
 * runs the same DatasetValidator the importer ran, recomputes the content
 * digest, and compares all of it with what the snapshot claims.
 */
final class SnapshotRevalidator
{
    public function __construct(
        private SnapshotReader $reader,
        private DatasetValidator $validator,
    ) {}

    /**
     * @throws QuranDataUnavailable
     */
    public function revalidate(int $versionId): RevalidationResult
    {
        $version = DB::table('quran_dataset_versions')
            ->where('id', $versionId)
            ->first(['id', 'validation_status', 'validation_report', 'content_digest', 'checksum_sha256']);

        if ($version === null) {
            throw new QuranDataUnavailable("Quran dataset version {$versionId} does not exist.");
        }

        $payload = $this->reader->read($versionId);
        $report = $this->validator->validate($payload);

        $stored = json_decode((string) $version->validation_report, true);

        return new RevalidationResult(
            versionId: $versionId,
            storedStatus: (string) $version->validation_status,
            recomputedStatus: $report->status(),
            report: $report,
            storedDigest: $version->content_digest === null ? null : (string) $version->content_digest,
            computedDigest: ContentDigest::of($payload),
            checksum: (string) $version->checksum_sha256,
            storedStats: is_array($stored) && isset($stored['stats']) && is_array($stored['stats']) ? $stored['stats'] : [],
            computedStats: $report->stats,
        );
    }
}
