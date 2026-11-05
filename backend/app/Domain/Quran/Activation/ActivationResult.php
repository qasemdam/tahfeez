<?php

namespace App\Domain\Quran\Activation;

/**
 * What activation did.
 *
 * `previousVersionId` is the snapshot that was deactivated, if any. `alreadyActive`
 * is true when the requested version was the active one — an idempotent call,
 * not an error.
 */
final readonly class ActivationResult
{
    public function __construct(
        public int $versionId,
        public ?int $previousVersionId,
        public bool $alreadyActive,
        public int $surahs,
        public int $pages,
        public int $ayahs,
        public string $edition,
        public string $sourceVersion,
    ) {}
}
