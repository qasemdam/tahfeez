<?php

namespace App\Domain\Quran\Import;

/**
 * The outcome of one import attempt.
 *
 * `status` is the snapshot's `validation_status`: `passed`, `incomplete` or
 * `failed`. Only `passed` is servable by the data provider; `incomplete` means
 * the dataset was stored but a critical check could not run.
 *
 * `versionId` is null only when the file could not be read at all: a dataset
 * that parsed but failed validation is recorded as a failed snapshot, so the
 * attempt leaves an audit trail without becoming usable.
 */
final readonly class ImportResult
{
    public function __construct(
        public bool $passed,
        public string $status,
        public ValidationReport $report,
        public string $checksum,
        public ?int $versionId,
        public int $surahs,
        public int $pages,
        public int $ayahs,
        public string $message,
    ) {}
}
