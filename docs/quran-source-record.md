# Quran Dataset — Source & Provenance Record

Status: **Artifact inspected — validation failed on edition; no official 1405H dataset published; licence unresolved**
Required by: `quran-data.md` §12 · BR-QRN-08 · OD-005 (licensing gate)
Full results: [`quran-validation-report.md`](quran-validation-report.md)

No Quran artifact may be imported into a production environment until this record is complete for that artifact.

---

## 1. Recorded artifact — KFGQPC Hafs v3.0

| Field | Value |
|---|---|
| Source | King Fahd Glorious Quran Printing Complex — developer platform |
| Page | https://qurancomplex.gov.sa/quran-dev/ |
| Artifact URL | `https://download.qurancomplex.gov.sa/resources_dev/kfgqpc_hafs_v30.zip` |
| Dataset / version | `kfgqpc_hafs_v30` (v3.0); SQL dump dated 2026-09-30 14:21 |
| Format selected | JSON (`kfgqpc_hafs_v30-data/kfgqpc_hafs_v30.json`) — CSV/SQL/TXT/XLSX/XML/HTML also present |
| Download date | 2026-10-29 |
| Size | 10,155,774 bytes |
| SHA-256 | `227e6b1564d980f2bd09c2c35ebfb0330ac268c79a7c247cd1ab665bc635f245` |
| Archive integrity | OK |
| Ayah count / page count | 6,236 / 604 — verified |
| **Edition (declared)** | **none — no edition field in any of the seven formats** |
| **Edition (determined)** | **1441 AH layout** (juz-30 line-break comparison: 100% vs 1441H, 20.9% vs 1405H) |
| Riwayah | Hafs ʿan ʿAsim (declared, and consistent) |
| Script | Uthmani (declared, and consistent) |
| Attribution requirements | **not stated by the source** |
| License / usage terms | **none published**; site footer asserts "جميع الحقوق محفوظة © 2026" |
| Font (`.ttf`) terms | **not stated** — separate asset, separate question |
| Reviewed by | Phase 0 verification run |
| Review date | 2026-10-29 |

**Status: BLOCKED on two independent grounds.**

1. **Edition mismatch.** The canonical edition is `madinah_1405h_hafs_uthmani` (OD-026). This artifact encodes the 1441H layout. It cannot be activated as the canonical dataset.
2. **Licence.** No terms are published, and the site asserts all rights reserved. "Official" is not a licence, and "can be used in application development" is not a redistribution grant. The artifact must not be committed to the repository or bundled into a shipped build.

The artifact is held locally, outside the repository (`.gitignore` excludes `data/quran/*`).

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

---

## 5. 1405H availability (investigated 2026-10-29)

**Result: no official 1405H page/line dataset is published.** Full evidence in [`quran-1405h-sourcing.md`](quran-1405h-sourcing.md).

| Checked | Outcome |
|---|---|
| Developer platform (`/quran-dev/`) | One Hafs dataset, no edition selector, no "1405"/"1441" string |
| Artifact metadata (7 formats) | None — no edition, no manifest, no readme |
| Download host directory | 403 — cannot discover unpublished files |
| Official site search / productions / sales / publications | Only the **founding year** 1405 AH, not a dataset |
| Digital publishing app (`nashr`) | Desktop application v3.16, not a dataset; EULA unreviewed |
| National open data portal (`od.data.gov.sa`) | **Unreachable from this environment** — manual check required |

**Consequence:** the canonical edition (OD-026) cannot be sourced from public channels. A written request to KFGQPC is required — template and technical specification prepared in `quran-1405h-sourcing.md` §8. No message has been sent.

**Not accepted as substitutes:** `quran-madina-layout`, `quran-madina-html`, `quran-qcf4`, Tanzil — none is an official KFGQPC 1405H source (ADR-0008, ADR-0009).
