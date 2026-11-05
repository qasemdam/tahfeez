<?php

use App\Infrastructure\Quran\Import\CanonicalJsonSource;

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

    /*
    |--------------------------------------------------------------------------
    | Import
    |--------------------------------------------------------------------------
    |
    | `sources` maps a `--format` value to the adapter that reads it. Only the
    | neutral Tahfeez JSON is supported today; a vendor file (KFGQPC CSV, SQL
    | dump, third-party layout database) needs its own adapter that produces the
    | same DatasetPayload — validation and persistence never learn vendor quirks.
    |
    | `expected` are the structural totals of the target edition. They are
    | assertions about the dataset, never inputs to a measurement.
    |
    | `page_height_profiles` records the documented physical height of each page
    | for an edition, so an import can be checked against the print it claims to
    | be. A dataset still declares `lines_count` itself; the profile validates
    | that declaration, it never replaces it.
    |
    */

    'import' => [
        'sources' => [
            'json' => CanonicalJsonSource::class,
        ],

        'expected' => [
            'surahs' => 114,
            'ayahs' => 6236,
            'pages' => 604,
        ],

        'page_height_profiles' => [
            'madinah_1405h_hafs_uthmani' => [
                'short_pages' => [1, 2],
                'short_lines' => 8,
                'lines' => 15,
            ],
        ],
    ],

];
