<?php

namespace App\Domain\Quran\Import;

/**
 * What the operator declares about the file being imported.
 *
 * The edition is explicit and has no default: coordinates are edition-specific,
 * and an import must say which print it describes rather than inheriting the
 * product's canonical choice by accident.
 */
final readonly class ImportOptions
{
    public function __construct(
        public string $edition,
        public string $format = 'json',
        public string $source = 'file',
        public ?string $sourceVersion = null,
        public ?string $sourceUrl = null,
        public ?string $notes = null,
    ) {}
}
