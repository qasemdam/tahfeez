# ADR-0003 — Page measurement by mushaf line coordinates

**Status:** Proposed · **Date:** 2026-10-28 · **Phase:** 0

## Context
§9 and §10 require the system to compute how much a student memorized from an ayah range, explicitly forbidding "one surah = one page" and requiring the real amount to be recorded (2.5 pages → 250%). The Madinah mushaf is a fixed 604-page grid of 15-line pages, but ayah boundaries do not align with page boundaries and long ayahs cross pages.

## Decision
Measure in **global line coordinates**:

```text
global_line = quran_pages.line_offset(page) + line_in_page
amount_lines = global_line(end,end) − global_line(start,start) + 1
amount_pages = amount_lines / lines_per_page
```

Store `amount_lines` (integer) and `amount_pages numeric(8,4)` for every `CALCULATED` record. `line_offset` is precomputed per page at import (not `(page−1)×15`) so irregular opening pages are handled correctly. Rounding is a separate, explicit policy applied only at display/evaluation boundaries.

## Consequences
- Exact, reproducible, auditable; supports mid-page ranges and cross-surah ranges.
- The stored integer makes recomputation possible after a dataset correction.
- Requires a dataset with page **and** line coordinates for both ends of every ayah — a stricter requirement than most public datasets. This is the main procurement risk (OD-005).
- Two implementations exist (PHP + Dart); both are pinned to one shared fixture file, and CI fails on divergence.

## Alternatives rejected
- **Ayah count × average per page** — wrong for short surahs and mid-page starts.
- **`last_page − first_page + 1`** — off by up to a full page for mid-page ranges.
- **Single page per ayah** — ambiguous for page-spanning ayahs.
- **Manual-only entry** — the spec explicitly requires calculation from the range.
