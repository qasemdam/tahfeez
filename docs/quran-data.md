# Tahfeez — Quran Data & Page Measurement

Status: **Draft for review** · Phase 0
This document is the single source of truth for "how much did the student memorize".

---

## 1. Scope

The system measures memorization ranges against the **Madinah Mushaf, Hafs ʿan ʿAsim, Uthmani script** (§8). Nothing in this document is user-editable at runtime.

Three separate concerns, deliberately kept apart:

| Concern | Where it lives | Mutable? |
|---|---|---|
| Quran text, ayah numbering, page/line coordinates | `quran_*` tables, seeded from a versioned dataset | No |
| Measurement (range → pages) | `PageMeasurement` domain service | No (pure function) |
| Presentation rounding (2.5 vs 2.47) | Center/student policy | Yes, policy |

---

## 2. Why line coordinates, not ayah counts

Naive approaches and why they fail:

| Approach | Failure |
|---|---|
| 1 surah = 1 page | Al-Baqarah is 48 pages; Al-Kawthar is 3 lines (§10 explicitly forbids this) |
| pages = ayahs / average ayahs-per-page | Wrong for short surahs and for ranges that start mid-page |
| pages = last_page − first_page + 1 | A range from page 3 line 14 to page 4 line 2 is 4 lines, not 2 pages |
| single `page_number` per ayah | Long ayahs span a page break; the range becomes ambiguous |

The mushaf is a **grid of lines**. A page is 15 lines (with documented exceptions on the opening pages). Therefore the only correct measurement unit is the **line**, and a page is `lines / lines_per_page`.

---

## 3. Coordinate model

```text
global_line(ayah, side) = quran_pages.line_offset(page_side) + line_side
```

- `line_offset(p)` = total number of lines on pages `1 … p-1`, precomputed at import.
- `side` ∈ {`start`, `end`} — the first and last line the ayah occupies.
- `line_start` / `line_end` are 1-based within the page.

### Measurement

```text
amount_lines = global_line(to_ayah, end) − global_line(from_ayah, start) + 1
amount_pages = amount_lines / lines_per_page
```

`+1` because both boundary lines are included: a single-line ayah is 1 line, not 0.

`lines_per_page` is read from `quran_pages.lines_count` of the **start page** and must be 15 for any page that is not one of the documented exceptions. The importer rejects a dataset where `lines_count` deviates without an explicit allow-list entry.

### Worked example (illustrative coordinates)

```text
from = page 3, line 10   →  offset(3) = 30  →  global 40
to   = page 4, line 12   →  offset(4) = 45  →  global 57

amount_lines = 57 − 40 + 1 = 18
amount_pages = 18 / 15     = 1.2
```

```text
from = page 3, line 10   →  global 40
to   = page 6, line  9   →  offset(6) = 75  →  global 84

amount_lines = 84 − 40 + 1 = 45
amount_pages = 45 / 15     = 3.0
```

> The numbers above demonstrate the arithmetic only. Real coordinates come from the imported dataset and are locked by golden fixtures (§7).

---

## 4. Precision, rounding, and the "250%" case

Stored value: `amount_pages numeric(8,4)` — four decimals, e.g. `1.2000`, `2.4667`.
`amount_lines` is stored too, so a `CALCULATED` record is always reproducible from its coordinates.

Rounding is **never** applied to the stored value. It is applied at three named boundaries:

| Boundary | Rule |
|---|---|
| Display | `round_half_up(value, 2)` → `1.20` |
| Evaluation input | configurable: `exact` (default) or `nearest 0.25 / 0.5` **[OD-004]** |
| Report aggregation | sum of exact values, rounded once at the end — never sum of rounded values |

**The 250% case (§10, §11).** Required = 1 page, actual = 2.5 pages:

```text
achievement_pct = 2.5 / 1.0 × 100 = 250.00
```

- `actual_pages = 2.5` (the real achievement, recorded as-is)
- `required_pages = 1.0` (unchanged — the baseline is not renegotiated)
- The student is flagged outstanding; the baseline for other students is untouched.

This is enforced structurally: `required_pages` is copied from the *profile version*, `actual_pages` from the *records*. No code path can write one from the other (BR-EVL-04).

---

## 5. Range rules

| Rule | Detail |
|---|---|
| Inclusive bounds | Both start and end ayah belong to the range |
| Order | `to_ayah_id >= from_ayah_id` in global order; reversed input is rejected, not silently swapped |
| Cross-surah | Allowed — a student finishing Al-Baqarah and starting Al-Imran records one range. `surah_id` stores the **start** surah; the end surah is derived **[OD-010]** |
| Single ayah | Valid (1 line minimum) |
| Whole surah | Valid; amount is computed, never assumed |
| Manual amount | Range optional; `amount_source = MANUAL`; never overwritten by a later calculation (BR-MEM-02) |

---

## 6. Dataset lifecycle

```text
Licensed source file
        ↓  import (artisan quran:import --version=…)
  validation gate  (counts, checksums, monotonic coordinates)
        ↓
  quran_* tables  +  quran_dataset_versions row
        ↓  export
  mobile asset (SQLite) + golden fixtures (JSON)
```

Validation gate — the import **fails loudly** unless all hold:

1. 114 surahs, 6236 ayahs, 604 pages.
2. `surah.ayah_count` equals the actual count of its ayahs.
3. Global ayah ids form a gap-free `1…6236` sequence in mushaf order.
4. Every `(page, line)` coordinate is within `1…lines_count(page)`.
5. `line_offset(p+1) = line_offset(p) + lines_count(p)` for all `p`.
6. Surah `page_start` ≤ first ayah `page_start`, and `page_end` ≥ last ayah `page_end`.
7. Text is non-empty and contains no Latin characters (guards against a wrong column being imported).
8. Dataset checksum matches the manifest.

A failed gate is a **blocking issue**, not a warning. A corrupted Quran dataset silently invalidates every grade in the system.

---

## 7. Golden fixtures (cross-platform contract)

The measurement algorithm exists in two places: Dart (instant offline preview) and PHP (authoritative). Divergence between them is the most likely source of "the app says 2.4 but the report says 2.47" bugs.

Contract:

- `docs/fixtures/page-measurement.json` holds ≥ 300 vectors: `{from_ayah, to_ayah, expected_lines, expected_pages}`.
- The vectors are produced by the PHP implementation, then **manually verified against the printed mushaf** for a sample of at least 20 cases spanning: short surah, long surah, mid-page start, mid-page end, page-break-spanning ayah, cross-surah range.
- Both test suites load the same file. A change to either implementation that breaks a vector fails CI.
- The file is versioned with the dataset version.

---

## 8. Client-side availability

- The full Quran dataset ships as a compressed SQLite asset (~ a few MB) so measurement works with zero connectivity.
- On first launch the client records its `dataset_version`; the server exposes the current version and a delta/full-replacement endpoint.
- If versions differ, the client keeps working with its local copy but marks affected records `measurement_dataset: <version>`, and the server re-measures on ingest. **Server value wins** (BR-QRN-05).
- Text rendering uses a bundled Uthmani font; font licensing is **[OD-005]**.

---

## 9. Explicitly out of scope

- Tafsir, translations, word-by-word morphology.
- Multiple qiraʾat / other mushaf prints.
- Audio, recitation timing, tajweed annotation.
- Any user-supplied Quran text.

These are Future Features and must not leak into the MVP schema.
