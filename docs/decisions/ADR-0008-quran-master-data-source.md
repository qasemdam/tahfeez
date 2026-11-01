# ADR-0008 — Quran master data: KFGQPC source, immutable versioned snapshots

**Status:** **Accepted** (product decision, 2026-10-28) · **Phase:** 0
Supersedes the open sourcing question in OD-005 (data availability only; licensing remains a gate).

---

## Context

The system's correctness is anchored to one printed mushaf (§8). Every grade derives from a range of ayahs converted into pages, so an error or silent change in the underlying Quran data propagates into every report, ranking and parent-facing PDF.

Three problems had to be solved together:

1. **Which dataset is authoritative.** Several community datasets exist (Tanzil, others), with different page/line mappings, different orthography and different numbering conventions. Mixing them — even accidentally — makes measurements incomparable.
2. **Immutability.** If a new import overwrites coordinates in place, historical records silently change meaning. That violates §13 and §19 in spirit and would be undetectable after the fact.
3. **Legal usability.** Quran text and Uthmani fonts carry their own terms. Bundling an artifact without documented permission is a distribution risk, not a technical detail.

## Decision

**1. Source of truth.** The **King Fahd Quran Printing Complex (KFGQPC) official developer dataset** (`https://qurancomplex.gov.sa/quran-dev/`) is the primary and authoritative source. It publishes Madinah-Mushaf Hafs/Uthmanic data in CSV, JSON, SQL, XML, TXT and Excel, exposing `page`, `line_start`, `line_end`, `sura_no`, `aya_no`, `aya_text_unicode`, `sura_name_ar`, `sura_name_en`, `jozz`.

**2. Source priority.**
1. KFGQPC official — primary, authoritative.
2. Tanzil — secondary **cross-validation only**; never imported as master data.
3. Any other source — not authoritative without an ADR.

**3. Immutable, versioned snapshots.** Every import creates a `quran_dataset_versions` row (source, source_version, downloaded_at, imported_at, checksum_sha256, validation_status, validation_report) and a complete, version-keyed snapshot of `quran_surahs` / `quran_pages` / `quran_ayahs`. Imports **add** rows; they never update existing ones. Exactly one version is active, resolved through a single `QuranContext` per request.

**4. Validation gate.** An import is rejected unless checks V-01…V-12 pass (114 surahs, ayah counts, continuous pagination, valid coordinates, no missing/duplicate ayahs, correct ordering, Hafs/Uthmani metadata, deterministic sample comparison against the source, checksum, golden boundary fixtures). No partial imports.

**5. Traceability.** Every `CALCULATED` memorization/review/recitation row stores `quran_dataset_version_id`. Activating a newer dataset never rewrites a stored amount; re-measuring is an explicit, audited operation.

**6. Licensing gate.** No permission is assumed. Before the dataset is committed to the repository or bundled into a shipped application, `quran-source-record.md` must document source URL, version, download date, checksum, attribution requirements and the licence terms **as provided by the source**. Fonts are a separate line item. Until sign-off, development uses a locally fetched artifact referenced by path and hash.

**7. No user edits.** There is no write endpoint, no admin UI and no client mutation path for Quran data, in any role.

## Consequences

- Historical grades stay reproducible: given the dataset version, coordinates and formula, any amount can be recomputed and compared.
- Upgrading the dataset is a deliberate, auditable event rather than an invisible drift.
- Storage grows by ~6.2k ayah rows per version — negligible, and worth the correctness.
- Queries must resolve the active version; an unversioned Quran table does not exist, so this cannot be forgotten silently.
- The licensing gate can block release while leaving development unblocked. That separation is intentional.
- **Residual risk:** the official row shape carries a single `page` plus `line_start`/`line_end`, so page-break ayahs need normalisation and empirical verification (**OD-025**).

## Alternatives rejected

- **Tanzil or another community dataset as primary** — no authoritative page/line mapping guarantee; would require us to own the mapping.
- **In-place replacement of Quran tables** — silently changes historical meaning; violates the immutability requirement.
- **Storing only derived amounts without a dataset reference** — makes every number unverifiable.
- **Letting an admin paste/edit Quran text** — direct violation of §8 and an obvious corruption path.
