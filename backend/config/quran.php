<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Which Quran data provider answers the domain
    |--------------------------------------------------------------------------
    |
    | `fixture`  — the development fixture in App\Infrastructure\Quran. Four
    |              surahs and a handful of ayahs, clearly not canonical.
    | `database` — the active imported snapshot in the quran_* tables. It
    |              refuses to answer when no active, validated snapshot exists.
    | `auto`     — `database` in production, `fixture` everywhere else. The
    |              production default is deliberate: a hosted deployment must
    |              never serve fixture data as if it were canonical.
    |
    | Switching to `database` does not make a dataset appear — one has to be
    | imported and activated (OD-029). Until then the database provider raises
    | QuranDataUnavailable instead of inventing data.
    |
    */

    'provider' => env('QURAN_PROVIDER', 'auto'),

];
