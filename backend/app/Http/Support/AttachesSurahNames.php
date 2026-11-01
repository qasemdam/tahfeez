<?php

namespace App\Http\Support;

use App\Domain\Quran\Contracts\QuranDataProvider;
use Illuminate\Support\Collection;

/**
 * Adds a display-only `surah_name` to records before they are serialized.
 * Surah names belong to Quran metadata, so they are not stored on the record.
 */
trait AttachesSurahNames
{
    /**
     * @param  Collection<int, mixed>  $records
     */
    protected function attachSurahNames(Collection $records, QuranDataProvider $quran): void
    {
        $names = collect($quran->surahs())->pluck('name_ar', 'id');

        foreach ($records as $record) {
            $record->setAttribute('surah_name', $names->get($record->surah_id));
        }
    }
}
