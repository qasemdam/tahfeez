<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Synchronous export ceiling
    |--------------------------------------------------------------------------
    |
    | A report export is generated in memory, in the request that asked for it.
    | Beyond a certain size that stops being safe: mPDF refuses an HTML string
    | larger than `pcre.backtrack_limit`, and every format pays for the whole
    | document in RAM.
    |
    | The default of 200 rows is measured, not guessed: the weekly report then
    | renders in ~4 s and ~75 MB of peak memory, and its HTML reaches ~730 KB —
    | 73% of the default 1 MB `pcre.backtrack_limit`, leaving room for longer
    | names without hitting the engine's hard limit.
    |
    | Raise it only together with `memory_limit`; the asynchronous export path
    | (BR-RPT-03) is what removes the ceiling, not a bigger number here.
    |
    */

    'max_rows' => (int) env('EXPORT_MAX_ROWS', 200),

    /*
    |--------------------------------------------------------------------------
    | PDF HTML budget
    |--------------------------------------------------------------------------
    |
    | The share of `pcre.backtrack_limit` a single PDF document may use. It is a
    | backstop for the row ceiling: a report inside the row limit whose cells are
    | unusually long can still produce an HTML string mPDF refuses. Keeping 20%
    | in reserve means the engine's own limit is never the thing that fails.
    |
    */

    'pdf_html_budget_ratio' => (float) env('EXPORT_PDF_HTML_BUDGET_RATIO', 0.8),

];
