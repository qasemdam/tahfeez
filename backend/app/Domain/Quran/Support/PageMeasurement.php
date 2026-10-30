<?php

namespace App\Domain\Quran\Support;

/**
 * Result of measuring a range. Amounts are decimal strings, never floats
 * (ADR-0003, ADR-0007).
 */
final readonly class PageMeasurement
{
    public function __construct(
        public int $fromAyahId,
        public int $toAyahId,
        public int $amountLines,
        public string $amountPages,
        public int $pageStart,
        public int $lineStart,
        public int $pageEnd,
        public int $lineEnd,
        public string $datasetVersion,
    ) {}
}
