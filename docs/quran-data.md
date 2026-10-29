# Tahfeez — Quran Master Data & Page Measurement

Status: **Revised per Phase 0 review decisions (2026-10-28)** · Phase 0
This document is the single source of truth for "how much did the student memorize".

---

## 1. Scope and authority

The system measures memorization ranges against the **Madinah Mushaf, Hafs ʿan ʿAsim, Uthmani script** (§8).

**Source of truth:** the **King Fahd Quran Printing Complex (KFGQPC) official developer dataset** — <https://qurancomplex.gov.sa/quran-dev/>. The platform publishes Quran data for developers in CSV, JSON, SQL, XML, TXT and Excel formats, including Madinah-Mushaf Hafs/Uthmanic data.

The application **never** edits Quran master data (§8, BR-QRN-01). There is no write path, no admin UI and no client mutation route.

Three concerns are deliberately separated:

| Concern | Where it lives | Mutable? |
|---|---|---|
| Quran text, ayah identity, page/line coordinates | `quran_*` tables, one immutable snapshot per dataset version | No |
| Measurement (range → pages) | `PageMeasurement` domain service | No (pure function) |
| Presentation rounding | Center/student policy | Yes, policy |

---

## 2. Source priority

| Priority | Source | Role |
|---|---|---|
| **1** | **KFGQPC official developer dataset** (`qurancomplex.gov.sa/quran-dev/`) | **Primary and authoritative.** All production master data comes from here. |
| 2 | Tanzil | **Secondary cross-validation only.** May be used to detect transcription drift. Never imported as master data. |
| 3 | Any other source | **Not authoritative.** Requires an explicit ADR before use. |

Replacing the primary source with a third-party dataset is an architectural change and MUST go through an ADR (ADR-0008). It is not a configuration toggle.

### 2.1 Layout projects are references, not sources

`quran-madina-layout` and `quran-madina-html` were studied in depth (see [`quran-data-sources-comparison.md`](quran-data-sources-comparison.md)). They are **rendering** datasets — they answer "how does this line look", not "which page and line does this ayah occupy". They also:

- hardcode 15 lines per page (`line_1to15`, `range(1, 16)`), which BR-QRN-09 forbids;
- cannot represent an ayah that crosses a page break (one `page` field, lines 1…15);
- carry a licence conflict (Waqf GPL 2.0 upstream vs an MIT claim downstream — BLOCKER-01);
- are edition-locked (1405 AH, or 1441H/1405H/IndoPak in the successor).

They are therefore **reference implementations to learn from**, never data sources for measurement (ADR-0009, BR-QRN-10).

### 2.2 Canonical edition (OD-026, resolved 2026-10-29)

| Attribute | Value |
|---|---|
| Mushaf | مصحف المدينة النبوية |
| Edition | **1405 AH** |
| Riwayah | Hafs ʿan ʿAsim |
| Script | Uthmani |
| Pages | 604 |
| Lines per page | 15 — *descriptive property of the print*, never a formula |

Identifier: **`madinah_1405h_hafs_uthmani`**.

The 15-line fact must never appear as a divisor in code. `lines_count` is read per page from the dataset (BR-QRN-09); on this edition it is 15 everywhere, which is a *finding*, not an assumption.

**Verification requirement (VR-EDITION).** Being "the official KFGQPC data" does not prove the edition. If an artifact carries no metadata identifying the print, the gap is recorded and the artifact is not activated on the strength of its name — see `quran-validation-plan.md` S-04.

**Current status (2026-10-29).** The only official artifact reachable from the platform (`kfgqpc_hafs_v30`) **does not match this edition**: its line breaks match the 1441 AH layout 100% on juz 30 and the 1405 AH layout only 20.9%. It passed all 12 structural checks and still cannot be activated. No snapshot exists; `validation_status` stays `failed`. Resolution is **OD-029**; evidence in `quran-validation-report.md`.

---

## 3. Dataset versioning model

Every import produces an **immutable snapshot**, not an in-place update:

```text
quran_dataset_versions
  id                bigint PK
  source            varchar      e.g. 'kfgqpc'
  source_url        varchar
  source_version    varchar      as published by the source
  edition           varchar      madinah_1405h | madinah_1441h | …   ← required, see OD-026
  source_format     varchar      csv | json | sql | xml | txt | xlsx
  downloaded_at     timestamptz
  imported_at       timestamptz
  checksum_sha256   char(64)     hash of the raw downloaded artifact
  validation_status varchar      pending | passed | failed
  validation_report jsonb        per-check results (V-01…V-12)
  is_active         boolean      exactly one active version
  notes             text
```

Snapshot tables are keyed by version:

```text
quran_surahs (dataset_version_id, id)
quran_pages  (dataset_version_id, page_number)
quran_ayahs  (dataset_version_id, id)
```

Consequences:

- Importing a new version **adds rows**, it never rewrites old ones.
- Historical memorization/review/recitation rows keep pointing at the version they were measured with, so their amounts never change silently (BR-MEM-14).
- Storage cost is trivial (≈6.2k ayah rows per version); correctness is worth it.
- Exactly one version is `is_active`; all application reads resolve through a single `QuranContext` bound per request. No query may join an unversioned Quran table, because none exists.
- **Edition is part of the identity.** The 1405 AH and 1441 AH Madinah prints break lines differently on roughly 37% of lines, so two datasets are comparable only when they share an edition. Mixing them changes measured amounts without a single line of code changing (**OD-026**).

**Activation** is an explicit, audited action (`quran:activate {version}`). Importing a dataset does not make it live.

---

## 4. Import pipeline

```text
KFGQPC official artifact (CSV / JSON / SQL / XML / TXT / XLSX)
        ↓  1. fetch + record provenance (URL, date, checksum)
        ↓  2. stage raw file into a versioned import package
        ↓  3. parse + normalise into the internal shape
        ↓  4. derive page structure (lines per page) — pass 1
        ↓  5. resolve ayah coordinates incl. page-break carry-over — pass 2
        ↓  6. validation gate V-01…V-12   ← fail = abort, no partial import
        ↓  7. insert immutable snapshot (validation_status = passed)
        ↓  8. manual activation (audited) → application reads it
```

Rules:
- The import runs in a single transaction. A failed gate leaves **no** partial snapshot.
- Pass 1 must precede pass 2 because a page's line count is derived from the data, and a page-break ayah can only be resolved once the heights of the following pages are known.
- The raw artifact is retained alongside its checksum so any snapshot can be reproduced byte-for-byte.
- Import is a CLI command (`quran:import`), never an HTTP endpoint.

---

## 5. Official field mapping

The Hafs dataset exposes (at minimum) the fields below. Mapping to the internal model:

| Official field | Internal column | Notes |
|---|---|---|
| `sura_no` | `quran_surahs.id`, `quran_ayahs.surah_id` | 1…114 |
| `sura_name_ar` | `quran_surahs.name_ar` | Repeated per ayah in flat files → normalised once |
| `sura_name_en` | `quran_surahs.name_en` | Same |
| `aya_no` | `quran_ayahs.number_in_surah` | |
| `aya_text_unicode` | `quran_ayahs.text_uthmani` | Uthmani script, stored verbatim |
| `page` | `quran_ayahs.page_start` | Page where the ayah begins |
| `line_start` | `quran_ayahs.line_start` | 1-based within the page |
| `line_end` | `quran_ayahs.line_end` | 1-based; may exceed the page height on page-break ayahs (§6) |
| `jozz` | `quran_ayahs.juz_number` | Juz 1…30 |

Derived internally (not present in the source):
- `id` — global ayah id `1…6236` in mushaf order, assigned deterministically during import.
- `page_end`, normalised `line_end` across page breaks (§6).
- `quran_pages.lines_count`, `quran_pages.line_offset` — derived from the data (§7).
- `hizb_number`, `rub_number` — **nullable**; populated only if a future source revision provides them. They are not required by any rule and must not be guessed.

Validation of the mapping is part of the gate (V-09): no unmapped required field, no silently ignored column.

---

## 6. Page-break ayahs (explicit handling)

The official row carries **one** `page` plus `line_start`/`line_end`. A long ayah may continue past the bottom of its page. Two encodings are possible and must be verified against the actual artifact (**OD-025**):

- **Encoding A** — `line_end` exceeds `lines_count(page)`: the overflow continues onto the following page(s).
- **Encoding B** — the dataset already splits or clamps such ayahs, and `line_end ≤ lines_count(page)` always holds.

Import normalisation, applied identically in both cases:

```text
if line_end <= lines_count(page):
    page_end = page ; line_end_normalised = line_end
else:
    carry = line_end − lines_count(page)
    walk forward pages until carry is absorbed
    page_end = resulting page ; line_end_normalised = position on that page
```

The import **reports the count** of page-break ayahs in `validation_report`. If the count is non-zero, a sample (minimum 5) must be visually verified against the printed mushaf before activation. If the count is zero, that fact is recorded explicitly — silence is not evidence.

**Empirical result (KFGQPC Hafs v3.0, 2026-10-29).** Zero rows with `line_end > 15`; zero ayahs split across pages; the longest ayah (2:282, 1,193 chars) exactly fills page 48, lines 1–15; and the chars-per-covered-line distribution has no outliers (median 52, p99 92, max 108). No ayah crosses a page break in this edition, and none is truncated. `page_end`/`line_end` stay in the model regardless: a single `page` field cannot *prove* absence, and a future edition may differ. Details in `quran-validation-report.md` §6.

---

## 7. Page structure is data-driven, not assumed

There is **no** hard-coded "1 page = 15 lines" rule anywhere in the system. Page height is data:

```text
lines_count(page) = physical number of text lines on that page (from the edition spec)
line_offset(page) = Σ lines_count(1 … page−1)     -- derived metadata, not a measurement basis
```

For `madinah_1405h_hafs_uthmani` the verified values are **15 on pages 3–604 and 8 on pages 1–2** (measured on the real artifact — see `quran-validation-report.md` §4).

**Do not derive `lines_count` from the lines that ayah records happen to cover.** Verified on the artifact: 41 pages carry a surah title + basmala on lines 1–2, and a further 16 start a surah mid-page, so coverage-based counting reports **13 instead of 15** on those pages and would overstate every measurement taken there by up to 15%. Page height is a property of the page, not of its contents.

`line_offset` exists for ordering, debugging and audit only. Measurement does **not** depend on a uniform page height.

### Measurement formula

For a range covering pages `p1 … pn`:

```text
covered(p)   = number of lines of page p that fall inside the range
amount_pages = Σ  covered(p) / lines_count(p)
amount_lines = Σ  covered(p)
```

This is a sum of **page fractions**, not a division by a constant. When every covered page happens to have the same height it reduces to `lines / lines_count` — a consequence, not an assumption.

### Worked example — uniform pages

Pages 3…6, 15 lines each. Range: page 3 line 10 → page 6 line 9.

```text
page 3: 6 / 15  = 0.4000
page 4: 15 / 15 = 1.0000
page 5: 15 / 15 = 1.0000
page 6: 9 / 15  = 0.6000
                  ------
amount_pages    = 3.0000
amount_lines    = 45
```

### Worked example — irregular pages

Page 1 has 8 lines, page 2 has 15. Range: page 1 line 5 → page 2 line 7.

```text
page 1: 4 / 8   = 0.5000
page 2: 7 / 15  = 0.4667
                  ------
amount_pages    = 0.9667
```

A fixed-15 model would report `11 / 15 = 0.7333` — a 24% understatement. This is exactly why page height must come from the data.

> Numeric examples above are illustrative. Real coordinates come from the imported dataset and are locked by golden fixtures (§13).

---

## 8. Precision, rounding, and the 250% case

- `amount_pages` is stored as `numeric(8,4)`; computation uses `bcmath` at scale 6, never floats.
- `amount_lines` (integer) is stored alongside for traceability.
- Rounding is **never** applied to the stored value. It is applied only at:
  - display — `round_half_up(value, 2)`;
  - evaluation input — configurable policy **[OD-004]**;
  - report aggregation — sum exact values, round once at the end.

**The 250% case (§10, §11).** Required = 1 page, actual = 2.5 pages:

```text
achievement_pct = 2.5 / 1.0 × 100 = 250.00
```

- `actual_pages = 2.5` — the real achievement, recorded as-is, **not capped**.
- `required_pages = 1.0` — unchanged; the baseline is not renegotiated.
- The student is flagged outstanding; the baseline for other students is untouched.

The advanced weighted score is a **different value** (0–100) stored in a different column. The two are never combined, never averaged and never substituted for one another (BR-EVL-15).

---

## 9. Range rules

| Rule | Detail |
|---|---|
| Inclusive bounds | Both start and end ayah belong to the range |
| Order | `to_ayah_id >= from_ayah_id` in global order; reversed input is rejected, never silently swapped |
| Cross-surah | Allowed — ayah ids are global, so the range is well-defined; `surah_id` stores the start surah and the end surah is derived **[OD-010]** |
| Single ayah | Valid (minimum one line) |
| Whole surah | Valid; the amount is computed, never assumed |
| Manual amount | Range optional; `amount_source = MANUAL`; never overwritten by a later calculation (BR-MEM-02) |
| Dataset version | Every calculated record stores the `quran_dataset_version_id` used |

---

## 10. Traceability

Each memorization, review and recitation row stores:

- `from_ayah_id`, `to_ayah_id` — identity, stable across dataset versions;
- `quran_dataset_version_id` — the exact snapshot used for measurement;
- `amount_lines`, `amount_pages` — the computed result;
- `amount_source` — `CALCULATED` or `MANUAL`.

A historical record is therefore reproducible: given the stored version, the coordinates and the formula, the number can be recomputed and compared. Importing a newer dataset never rewrites it (BR-MEM-14).

Re-measuring an old record under a new dataset version is an explicit, audited operation with a reason — never an automatic side effect of activation.

---

## 11. Validation gate

The import is rejected unless every check passes. `validation_status` stays `failed` and activation is impossible.

Execution procedure, evidence requirements, page-break verification and the sign-off checklist are in [`quran-validation-plan.md`](quran-validation-plan.md).

| ID | Check | Failure mode |
|---|---|---|
| V-01 | Exactly **114** surahs | Abort |
| V-02 | Ayah count per surah matches the canonical Hafs count; total = **6236** | Abort |
| V-03 | Page numbers form a **continuous** sequence `1…N` with no gaps or duplicates | Abort |
| V-04 | Every page range valid: `1 ≤ page ≤ N`, page monotonic with ayah order | Abort |
| V-05 | Every coordinate valid: `1 ≤ line_start ≤ line_end ≤ lines_count(page)` after normalisation | Abort |
| V-06 | **No missing ayahs** — global ids form a gap-free `1…6236` sequence | Abort |
| V-07 | **No duplicate** ayah identifiers — `unique(sura_no, aya_no)` and `unique(id)` | Abort |
| V-08 | Correct **surah/ayah ordering** — mushaf order matches id order | Abort |
| V-09 | Valid **Hafs/Uthmani metadata** — script field present, no Latin characters in `aya_text_unicode`, every expected column mapped | Abort |
| V-10 | **Deterministic sample comparison** against the official source — N known ayahs (text + page + line) compared byte-for-byte | Abort |
| V-11 | **Checksum verification** — `checksum_sha256` matches the recorded manifest | Abort |
| V-12 | **Golden boundary fixtures** — known page/ayah boundaries reproduce expected values (§13) | Abort |

Additional non-blocking assertions recorded in `validation_report`:
- count of page-break ayahs (§6);
- distribution of `lines_count` across pages (a sudden uniform value may indicate a parser default rather than real data);
- per-surah page span consistency (`page_start ≤ first ayah page`, `page_end ≥ last ayah page`).

A failed gate is a **blocking issue**, not a warning. A corrupted dataset silently invalidates every grade in the system.

---

## 12. Provenance, attribution and licensing

Every import must be accompanied by a provenance record — see [`quran-source-record.md`](quran-source-record.md). Minimum fields:

| Field | Purpose |
|---|---|
| Source URL | Exact page/endpoint the artifact came from |
| Dataset / version | As published by KFGQPC |
| Download date | Timestamp of retrieval |
| SHA-256 checksum | Ties the record to the exact bytes |
| Attribution requirements | As stated by the source |
| License / usage terms | Verbatim or linked, as provided by the source |

**Licensing gate (blocking).** No permission is assumed. Until the KFGQPC terms for redistribution and mobile bundling are documented and reviewed:

- the dataset MUST NOT be committed to the public repository;
- the dataset MUST NOT be shipped inside a distributed app binary;
- development may use a locally fetched copy referenced by path/hash only.

This is the remaining part of **OD-005**. It blocks production bundling, not architecture work or Phase 2 development.

---

## 13. Golden fixtures (cross-platform contract)

The measurement algorithm exists twice — Dart (instant offline preview) and PHP (authoritative). Divergence is the most likely source of "the app says 2.4 but the report says 2.47".

- `docs/fixtures/page-measurement.json` holds ≥ 300 vectors: `{from_ayah_id, to_ayah_id, expected_lines, expected_pages, dataset_version}`.
- Vectors are generated by the PHP implementation, then **manually verified against the printed mushaf** for at least 20 cases spanning: short surah, long surah, mid-page start, mid-page end, page-break ayah, cross-surah range, irregular-height page.
- Both test suites load the same file. A change to either implementation that breaks a vector fails CI.
- The file is versioned with the dataset version; vectors are never regenerated silently.

---

## 14. Client availability

- The active dataset ships as a compressed SQLite asset so measurement works with zero connectivity.
- The client records its `dataset_version` on first launch; the server exposes the active version and a delta/full-replacement endpoint.
- If the versions differ, the client keeps working locally but tags records with its version, and the server re-measures on ingest. **Server value wins** (BR-QRN-05).
- Rendering uses a bundled Uthmani font; font licensing is tracked in the provenance record.

---

## 15. Explicitly out of scope

- Tafsir, translations, word-by-word morphology.
- Other qiraʾat or other mushaf prints.
- Audio, recitation timing, tajweed annotation.
- User-supplied Quran text.
- Editing Quran data through the application, in any role.

These are Future Features and must not leak into the MVP schema.
