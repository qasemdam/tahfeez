# ADR-0003 — Page measurement by mushaf line coordinates

**Status:** **Accepted** (amended 2026-10-28, review round 1) · **Date:** 2026-10-28 · **Phase:** 0

## Context
§9 and §10 require the system to compute how much a student memorized from an ayah range, explicitly forbidding "one surah = one page" and requiring the real amount to be recorded (2.5 pages → 250%). The Madinah mushaf is a fixed sequence of pages, each holding a number of text lines — mostly uniform, but not guaranteed to be — and ayah boundaries do not align with page boundaries; long ayahs cross pages.

## Decision
Measure as a **sum of page fractions** over the mushaf's own coordinates:

```text
covered(p)   = lines of page p inside the range
amount_pages = Σ covered(p) / lines_count(p)
amount_lines = Σ covered(p)
```

- `lines_count(p)` is **derived from the imported dataset**, never assumed. There is no `1 page = 15 lines` constant anywhere in the system.
- `line_offset(p)` (Σ lines of preceding pages) is retained as ordering/audit metadata only; it is not the measurement basis.
- Store `amount_lines` (integer) and `amount_pages numeric(8,4)` for every `CALCULATED` record, plus `quran_dataset_version_id` so the number can be traced to the exact dataset that produced it.
- Rounding is a separate, explicit policy applied only at display/evaluation boundaries, never to the stored value.

When every covered page happens to share a height, the formula reduces to `lines / lines_count` — a consequence, not an assumption.

## Consequences
- Exact, reproducible, auditable; supports mid-page ranges, page-break ayahs and cross-surah ranges.
- Correct on irregular pages (e.g. 8-line opening pages) where a fixed-height model understates by ~24%.
- The stored integer + dataset version make recomputation possible after a dataset correction, without silently altering history.
- Requires a dataset with page **and** line coordinates for both ends of every ayah. This is satisfied by the KFGQPC official dataset (ADR-0008), subject to verifying how page-break ayahs are encoded (**OD-025**).
- Two implementations exist (PHP + Dart); both are pinned to one shared fixture file, and CI fails on divergence.

## Amendment (2026-10-28)
The original decision divided by a fixed page height. Review round 1 established that page structure must be data-driven, so the formula became a sum of page fractions. The change is strictly more general: for uniform pages the result is identical, and for irregular pages it is correct where the old formula was not.

## Amendment 2 (2026-10-29) — what `lines_count` actually is

Measured on the real KFGQPC artifact (see `quran-validation-report.md`):

| Observation | Value |
|---|---|
| Pages reaching line 15 | 602 |
| Pages reaching line 8 | 2 (pages 1–2) |
| Pages with 2 lines uncovered by ayahs | 57 (surah title + basmala) |

Consequences:

- **`lines_count(page)` is the physical page height**, seeded from the edition specification (15, with 8 on pages 1–2). It is **not** the count of lines covered by ayah records — that yields 13 on a surah-start page and would inflate measurements taken there by ~15%.
- The formula `amount_pages = Σ covered(p) / lines_count(p)` is **unchanged and correct**. Only the source of the denominator is clarified.
- Mandatory fixtures: page 1 (8 lines), page 2 (8 lines), a surah-start page (13 covered / 15 physical), and 2:282 (page 48, lines 1–15 → exactly 1.0 page).

No change to A+, no change to the formula, no change to the 250% rule.

## Alternatives rejected
- **Ayah count × average per page** — wrong for short surahs and mid-page starts.
- **`last_page − first_page + 1`** — off by up to a full page for mid-page ranges.
- **Single page per ayah** — ambiguous for page-spanning ayahs.
- **Manual-only entry** — the spec explicitly requires calculation from the range.
