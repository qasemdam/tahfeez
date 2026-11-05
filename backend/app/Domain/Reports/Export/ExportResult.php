<?php

namespace App\Domain\Reports\Export;

/**
 * A generated file, ready to be handed to the response.
 *
 * The controller only reads these three values: it never inspects the binary
 * and never decides a content type.
 */
final readonly class ExportResult
{
    public function __construct(
        public ExportFormat $format,
        public string $filename,
        public string $contents,
    ) {}
}
