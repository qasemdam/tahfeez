<?php

namespace App\Domain\Quran\Import;

/**
 * The outcome of one import attempt.
 *
 * `versionId` is null only when the file could not be read at all: a dataset
 * that parsed but failed validation is recorded as a failed snapshot, so the
 * attempt leaves an audit trail without becoming usable.
 */
final readonly class ImportResult
{
    public function __construct(
        public bool $passed,
        public ValidationReport $report,
        public string $checksum,
        public ?int $versionId,
        public int $surahs,
        public int $pages,
        public int $ayahs,
        public string $message,
    ) {}
}
