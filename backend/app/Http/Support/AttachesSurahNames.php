<?php

namespace App\Http\Support;

use App\Domain\Quran\Contracts\QuranDataProvider;
use App\Domain\Quran\Exceptions\QuranDataUnavailable;
use Illuminate\Support\Collection;

/**
 * Adds a display-only `surah_name` to records before they are serialized.
 * Surah names belong to Quran metadata, so they are not stored on the record.
 *
 * The name is decoration: a deployment without an active dataset must still be
 * able to list, show and record memorization, review and recitation. So the one
 * failure that means "there is no reference data right now" degrades to a null
 * name instead of taking the whole response down with it. Every other failure
 * still propagates — this is not a blanket catch.
 */
trait AttachesSurahNames
{
    /**
     * @param  Collection<int, mixed>  $records
     */
    protected function attachSurahNames(Collection $records, QuranDataProvider $quran): void
    {
        try {
            $names = collect($quran->surahs())->pluck('name_ar', 'id');
        } catch (QuranDataUnavailable) {
            $names = collect();
        }

        foreach ($records as $record) {
            $record->setAttribute('surah_name', $names->get($record->surah_id));
        }
    }
}
