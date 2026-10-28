# Tahfeez — Quran Dataset Validation Plan

Status: **Phase 0 plan, for review** · 2026-10-28
Implements the gate defined in `quran-data.md` §11. A dataset that fails any check is **not** activatable.

---

## 1. Philosophy

**Fail closed.** A corrupted Quran dataset does not produce an error message — it produces plausible, wrong page counts for every student, in every report, forever. The gate therefore treats "cannot verify" as "failed", never as "assume fine".

Three principles:

1. **Evidence, not assertions.** Every check writes a machine-readable result into `validation_report`. "It looked right" is not a result.
2. **No partial imports.** The import runs in one transaction; a failure leaves no rows.
3. **Activation is separate.** Passing the gate makes a dataset *eligible*, not *live*. Activation is an explicit audited command.

---

## 2. Stage 0 — before parsing

| Step | Action | Evidence |
|---|---|---|
| S-01 | Record the exact artifact URL, retrieval timestamp, byte size | `quran-source-record.md` |
| S-02 | Compute SHA-256 of the raw bytes and store it | `checksum_sha256` |
| S-03 | Retain the raw artifact unchanged (path + hash), outside git | artifact store |
| S-04 | Identify the **edition** (1405 AH / 1441 AH / other) from the source's own metadata | `edition` field (**OD-026**) |
| S-05 | Record the licence/usage terms verbatim | provenance record |
| S-06 | Confirm the format matches a supported parser | import log |

**S-04 is not optional.** The 1405 AH and 1441 AH prints break lines differently on ~37% of lines. A dataset without a recorded edition cannot be compared with anything.

---

## 3. Stage 1 — structural checks (V-01 … V-12)

Each check is implemented as a named assertion with a stable ID, so a failing import names the rule rather than dumping a stack trace.

### V-01 — 114 surahs
Count distinct `sura_no`. Expected exactly 114, numbered 1…114 with no gaps.
**Fail:** abort. **Evidence:** count + missing numbers.

### V-02 — ayah counts
Per-surah count equals the canonical Hafs count; total 6,236. The canonical table is a checked-in fixture, not computed from the file under test (otherwise the check is circular).
**Fail:** abort. **Evidence:** per-surah diff.

### V-03 — continuous pagination
Page numbers form `1…N` with no gaps or duplicates; `N` equals the edition's page count (604 for both Madinah prints).
**Fail:** abort. **Evidence:** first/last page, missing list.

### V-04 — valid page ranges
`1 ≤ page ≤ N`; page is monotonic non-decreasing in mushaf order; no ayah jumps backwards.
**Fail:** abort. **Evidence:** first offending row.

### V-05 — valid coordinates
After normalisation: `1 ≤ line_start ≤ line_end ≤ lines_count(page)`; `lines_count(page) > 0`.
**Fail:** abort. **Evidence:** offending `(surah, ayah, page, lines)`.

### V-06 — no missing ayahs
Global ids form a gap-free `1…6236`.
**Fail:** abort. **Evidence:** gap ranges.

### V-07 — no duplicates
`unique(sura_no, aya_no)` and `unique(id)`.
**Fail:** abort. **Evidence:** duplicate keys.

### V-08 — ordering
Mushaf order of `(sura_no, aya_no)` matches the assigned global id order; surah start pages are non-decreasing.
**Fail:** abort. **Evidence:** first inversion.

### V-09 — Hafs/Uthmani metadata
Text field present and non-empty; contains no Latin letters; expected columns all mapped; no column silently ignored.
**Fail:** abort. **Evidence:** column map + sample of rejected rows.

### V-10 — deterministic sample comparison
A fixed set of known ayahs (text + page + line) is compared byte-for-byte against values read directly from the official source. The sample is chosen to include: a short surah, a long surah, a mid-page start, a page-final ayah, and a page-break candidate.
**Fail:** abort. **Evidence:** per-sample expected/actual.

### V-11 — checksum verification
Recompute SHA-256 and compare with S-02. Detects an artifact swapped between download and import.
**Fail:** abort.

### V-12 — golden boundary fixtures
A checked-in fixture file of known page/ayah boundaries must reproduce exactly. Catches regressions in the importer, not in the data.
**Fail:** abort.

### Non-blocking assertions (recorded, reviewed)

| ID | Assertion | Why it matters |
|---|---|---|
| N-01 | Count of page-break ayahs after normalisation | If > 0, sample verification is required (§4) |
| N-02 | Distribution of `lines_count` across pages | A sudden uniform value may be a parser default, not real data |
| N-03 | Per-surah page span consistency (`page_start ≤ first ayah page`, `page_end ≥ last ayah page`) | Catches truncated surah metadata |
| N-04 | Empty/whitespace-only text count | Should be zero |

---

## 4. Stage 2 — page-break verification (OD-025)

The official row carries one `page` plus `line_start`/`line_end`. Two encodings are possible:

- **A** — `line_end` exceeds `lines_count(page)`; the overflow continues on the next page.
- **B** — the dataset clamps/splits, so `line_end ≤ lines_count(page)` always.

Procedure:

1. Count rows with `line_end > lines_count(page)` **before** normalisation. Record the number.
2. If > 0 → encoding A confirmed; normalise (§3 of `quran-data-model.md`) and verify 5 normalised ayahs against the printed mushaf.
3. If 0 → do **not** conclude encoding B. Compare at least 5 known long ayahs against the print (2:282, 2:255, 4:176, 5:3, 24:31 are good candidates). If the print shows continuation that the data does not, the dataset is **incomplete** and the import is rejected.
4. Record the conclusion in `quran-data.md` §6 and in `validation_report`.

**Why this matters:** a clamped page-break ayah silently truncates every range that ends on it. The error is small, plausible, and permanent.

---

## 5. Stage 3 — golden fixtures

| Category | Minimum vectors | Purpose |
|---|---|---|
| Whole short surah (e.g. Al-Kawthar) | 5 | Smallest non-trivial range |
| Mid-page start | 20 | The most common teacher behaviour |
| Mid-page end | 20 | |
| Page-boundary exact (line 15 → line 1) | 20 | Off-by-one detection |
| Multi-page range | 30 | Fraction summation |
| Cross-surah range | 15 | Global ayah ordering |
| Long ayah / page-break candidate | 10 | OD-025 |
| Irregular-height page | 5 | Proves `lines_count` is data-driven |
| Full surah | 10 | Regression anchor |
| **Total** | **≥ 300** | |

Generation: produced by the PHP implementation, then **manually verified against the printed mushaf** for at least 20 vectors. Both the PHP and Dart suites load the same file; a divergence fails CI on both sides.

The fixture file records the `dataset_version_id` it was generated from. Regenerating fixtures requires a reason and a diff review — never a silent overwrite.

---

## 6. Stage 4 — cross-validation (secondary)

Tanzil may be used **only** to detect transcription drift, never as master data.

| Step | Action |
|---|---|
| C-01 | Normalise both texts (strip pause marks, unify hamza forms) |
| C-02 | Compare per-ayah hashes; count mismatches |
| C-03 | Investigate each mismatch; never auto-resolve |
| C-04 | Record result in the provenance record |

Expected: zero letter-level mismatches; pause-mark differences are possible and are recorded, not "fixed".

---

## 7. Sign-off checklist

```text
[ ] S-01…S-06 complete; provenance record filled
[ ] Edition identified and matches the print used by the pilot center
[ ] V-01…V-12 passed, report attached
[ ] N-01…N-04 reviewed; page-break decision documented
[ ] Golden fixtures regenerated and reviewed
[ ] Cross-validation result recorded
[ ] Licence/usage terms recorded; bundling decision explicit
[ ] Activation performed as a separate audited action
[ ] First weekly evaluation computed and spot-checked against paper
```

The last line matters: the gate proves the *data*, not the *product*. One real week checked against a paper register catches integration errors the fixture suite cannot.

---

## 8. Failure handling

| Situation | Action |
|---|---|
| Any V-check fails | Import aborts; dataset stays `failed`; no rows written |
| Artifact changes after download | V-11 fails; re-download and re-record |
| Edition unclear | Stop. Do not guess — a wrong edition shifts every measurement |
| Page-break evidence contradicts the print | Reject the dataset; raise a blocking issue |
| Bad dataset already activated | Roll back `is_active` to the previous version; records keep their own version ids, so history is intact; re-measure only via the audited re-measure operation |

Because records store `quran_dataset_version_id`, a bad activation is recoverable **without** rewriting history — the reason the versioned-snapshot model exists.

---

## 9. Test mapping

| Check | Automated test |
|---|---|
| V-01…V-09 | `Feature/Quran/DatasetImportTest` — one failing fixture per check |
| V-10, V-12 | `Feature/Quran/GoldenFixtureTest` |
| V-11 | `Feature/Quran/ChecksumTest` |
| N-01, N-02 | `Feature/Quran/DatasetProfileTest` (assertions on the report, not on the data) |
| Measurement | `Unit/Quran/PageMeasurementTest` + Dart mirror |
| Version isolation | `Feature/Quran/VersionIsolationTest` — import v2, assert v1 records unchanged |
