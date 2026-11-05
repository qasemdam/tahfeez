<?php

namespace App\Domain\Quran\Import;

use App\Domain\Quran\Exceptions\QuranImportFailed;

/**
 * A reader for one source file format.
 *
 * The importer knows nothing about KFGQPC's CSV, a vendor's JSON or a SQL dump:
 * each format gets an adapter that produces a DatasetPayload, so adding a format
 * never touches validation or persistence.
 */
interface QuranSource
{
    /**
     * @throws QuranImportFailed
     */
    public function read(string $path): DatasetPayload;
}
