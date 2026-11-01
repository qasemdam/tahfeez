# ADR-0009 — Layout projects are references, not data sources

**Status:** **Accepted** (engineering study, 2026-10-28) · **Phase:** 0
Related: ADR-0003 (measurement), ADR-0008 (KFGQPC source)

---

## Context

Two open-source projects implement a text-based Madinah-mushaf page renderer:

- `hadealahmad/quran-madina-layout` — 1405 AH, precomputed line segments with stretch factors, Qt/QML + web + Python reference engine, MIT claimed.
- `tarekeldeeb/quran-madina-html` — 1441H (default), 1405H and IndoPak, per-line widths in em, per-gap spaces, per-word scale, patched OFL fonts, Waqf GPL 2.0.

Both were proposed as possible sources for Tahfeez's Quran data. The question was whether to adopt them as the layout layer (option B/C) or keep KFGQPC as the sole source (option A).

A full inspection (structure, schemas, pipeline, sampled data, licences) is in `docs/quran-data-sources-comparison.md`.

## Decision

**Option A for master data and measurement: KFGQPC official dataset only.**

**Option B is permitted later, in a narrow form:** if a printed-page reader is funded, a layout layer may be added as an *isolated, separately licensed presentation asset*. It never enters the measurement tables and never becomes the source of page/line coordinates.

**Option C is rejected** — the layout project will not become the primary layout source.

Reasons, in order of severity:

1. **Licensing.** The upstream project licenses code *and assets* under Waqf GPL 2.0, whose clause seven requires modified redistributions to stay under the same licence. The downstream project claims MIT over data that appears to be the upstream v1 database (identical filenames; 1-byte size difference). Relying on that claim would place a copyleft obligation at the centre of our data model.
2. **Wrong shape.** Both are rendering recipes: line widths, gaps, stretch factors, font features. "Which line does ayah 7 start on" requires re-running layout logic, not a query.
3. **Wrong assumption.** Both hardcode 15 lines per page. Our rules forbid that constant (BR-QRN-09), and a future edition may not be uniform.
4. **Incomplete.** Neither can represent an ayah crossing a page break: one `page` field, lines `1…15`. That is precisely the ambiguity OD-025 exists to resolve.
5. **Edition-locked.** 1405 AH (or 1441H/1405H/IndoPak), while the official dataset describes a specific print that must be pinned anyway (OD-026).

## Consequences

- One source, one licence to clear, one validation gate. The measurement path has no rendering dependency.
- We forgo a ready-made pixel-faithful renderer. If a reader is ever required, it is a separate, funded workstream — and the study in `quran-data-sources-comparison.md` is the starting brief.
- The licence conflict becomes irrelevant to the MVP rather than something to negotiate.
- Any future adoption requires: an ADR, a licence clearance per asset class, and a guarantee that the layout asset is not joined into `quran_ayahs`.

## Alternatives rejected

- **C — layout project as the primary source.** Copyleft conflict, wrong data shape, hardcoded page height, no spanning representation, edition lock-in.
- **B now.** Adds a licence obligation and a font dependency for zero measurement value; the MVP has no reader requirement (§27).
- **Forking the layout project to strip its licence.** Not a technical decision, and not one this project can make.

## Note on what we *did* adopt

The study was not wasted: three findings changed our design.

1. **Edition is part of the dataset identity** (BR-QRN-10) — the two prints differ on ~37% of lines.
2. **Ayah-index traps are real** — third-party data uses `internal = ayah + 1` with two decoration slots; our importer must never map raw indices (documented in `quran-data-model.md` §6.2).
3. **Page-break ayahs are unrepresentable in every available dataset** — confirming OD-025 as a blocking verification rather than a theoretical concern.
