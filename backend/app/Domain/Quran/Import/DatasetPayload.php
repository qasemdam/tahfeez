<?php

namespace App\Domain\Quran\Import;

/**
 * One parsed dataset, before anything is written.
 *
 * Rows are plain arrays in the column shape of the quran_* tables: the importer
 * validates them first and only then persists them. Keeping the payload free of
 * Eloquent models means a source adapter cannot accidentally save a partial
 * dataset.
 */
final readonly class DatasetPayload
{
    /**
     * @param  array<int, array<string, mixed>>  $surahs
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<int, array<string, mixed>>  $ayahs
     */
    public function __construct(
        public string $edition,
        public string $sourceVersion,
        public array $surahs,
        public array $pages,
        public array $ayahs,
    ) {}

    public function surahCount(): int
    {
        return count($this->surahs);
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    public function ayahCount(): int
    {
        return count($this->ayahs);
    }
}
