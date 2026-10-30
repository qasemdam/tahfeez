<?php

namespace App\Domain\Quran\Contracts;

use App\Domain\Quran\Support\PageMeasurement;

/**
 * The only way business logic reaches Quran data.
 *
 * Nothing outside this namespace may know whether the data comes from KFGQPC,
 * QUL, or a fixture — that decision is deferred (OD-029) and must not leak
 * into the domain.
 */
interface QuranDataProvider
{
    /**
     * @return array<int, array{id:int, name_ar:string, ayah_count:int, page_start:int, page_end:int}>
     */
    public function surahs(): array;

    /**
     * Number of physical lines on a page, from the dataset (never a constant).
     */
    public function linesOnPage(int $page): int;

    /**
     * Measures an inclusive ayah range against the dataset's page structure.
     */
    public function measure(int $fromAyahId, int $toAyahId): PageMeasurement;

    /**
     * Identifies the dataset version used for a measurement.
     */
    public function datasetVersion(): string;
}
