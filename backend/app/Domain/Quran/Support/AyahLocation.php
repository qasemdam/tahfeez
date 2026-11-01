<?php

namespace App\Domain\Quran\Support;

/**
 * Where one ayah sits in the mushaf. `pageStart` and `pageEnd` may differ when
 * an ayah continues onto the next page (BR-QRN-09 / OD-025).
 */
final readonly class AyahLocation
{
    public function __construct(
        public int $surahId,
        public int $ayahNumber,
        public int $ayahId,
        public int $pageStart,
        public int $lineStart,
        public int $pageEnd,
        public int $lineEnd,
    ) {}
}
