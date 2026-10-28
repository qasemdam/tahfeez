# Quran Dataset — Source & Provenance Record

Status: **Incomplete — no artifact fetched yet**
Required by: `quran-data.md` §12 · BR-QRN-08 · OD-005 (licensing gate)

No Quran artifact may be imported into a production environment until this record is complete for that artifact.

---

## 1. Current status

| Field | Value |
|---|---|
| Source | King Fahd Quran Printing Complex — official developer platform |
| Source URL | https://qurancomplex.gov.sa/quran-dev/ |
| Dataset / version | **not yet recorded** |
| Format selected | **not yet decided** (CSV / JSON / SQL / XML / TXT / XLSX) |
| Download date | **not yet recorded** |
| SHA-256 checksum | **not yet recorded** |
| Ayah count / page count | **not yet verified** |
| Attribution requirements | **not yet documented** |
| License / usage terms | **not yet documented** |
| Font (Uthmani) source & licence | **not yet documented** |
| Reviewed by | — |
| Review date | — |

**Blocking:** the "License / usage terms" and "Attribution requirements" rows must be filled and reviewed before the dataset is committed to the repository or bundled into a shipped application. Until then, development uses a locally fetched copy referenced by path and hash only.

---

## 2. Per-import record

Copy this block for every artifact that enters the pipeline. It is the audit anchor for the corresponding `quran_dataset_versions` row.

```text
Dataset version id:      (assigned at import)
Source:                  KFGQPC official developer platform
Source URL:              https://qurancomplex.gov.sa/quran-dev/…
Dataset name/version:    …
Format:                  csv | json | sql | xml | txt | xlsx
Downloaded at:           YYYY-MM-DD HH:MM UTC
Downloaded by:           …
Artifact filename:       …
SHA-256:                 …
Size (bytes):            …
Validation status:       pending | passed | failed
Validation report:       (link or summary: V-01…V-12)
Page-break ayah count:   …
Distinct page heights:   …  (e.g. "15 lines × 602 pages, 8 lines × 2 pages")
Attribution (verbatim):  …
License/terms (verbatim or link): …
Bundling approved by:    …   (required before shipping)
Bundling approved on:    …
```

---

## 3. Rules

1. **One record per artifact.** Re-downloading the same URL on a later date produces a new record with its own checksum — files can change silently.
2. **Checksum is mandatory.** A record without a SHA-256 is not a record.
3. **Terms are quoted, not summarised.** Paraphrasing a licence is how organisations lose the right to use data.
4. **No permission is assumed.** Silence from the source is not permission.
5. **The repository stores metadata, not the dataset** — until bundling is approved in writing.
6. **Fonts are licensed separately** from the text data. An Uthmani font has its own terms and its own record line.

---

## 4. Cross-validation (optional, secondary)

Tanzil may be used **only** to detect transcription drift, never as master data (`quran-data.md` §2).

```text
Cross-validation run:    YYYY-MM-DD
Source compared:         Tanzil (version …)
Method:                  normalised text hash per ayah, count of mismatches
Mismatches found:        … (expected: 0 for identical Uthmani text)
Resolution:              … (investigate, do not auto-resolve)
```

A mismatch is a signal to investigate, not a licence to overwrite the primary source.
