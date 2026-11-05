<?php

namespace App\Domain\Quran\Import;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds the message shown when a dataset cannot be written.
 *
 * A database exception is never echoed: `QueryException::getMessage()` embeds the
 * statement and its bound values, and for `quran_ayahs` those values are the text
 * of the ayahs. The operator gets a reference they can quote; the log gets the
 * exception class and the same reference, nothing else.
 */
final class StorageFailure
{
    public static function describe(Throwable $exception): string
    {
        $reference = (string) Str::uuid();

        Log::error('quran.import.store_failed', [
            'reference' => $reference,
            'exception' => $exception::class,
        ]);

        return "The dataset could not be stored (reference {$reference}). Nothing was written.";
    }
}
