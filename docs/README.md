# Tahfeez — Documentation Index

**Phase 0 — Architecture & Project Foundation** · Status: *review round 1 applied (2026-10-28), awaiting sign-off* · 2026-10-28

No application code has been written. This is intentional: the master instruction (§28) requires Phase 0 to end with a review gate, and §22 forbids inventing undefined business rules.

---

## Reading order for review

| # | Document | What it answers |
|---|---|---|
| 1 | [`business-rules.md`](business-rules.md) | Every rule the system enforces, with IDs (`BR-*`) and source section |
| 2 | [`architecture.md`](architecture.md) | Layers, modules, folder structure, technology choices |
| 3 | [`database.md`](database.md) | ERD, tables, columns, constraints, indexes, migration order |
| 4 | [`quran-data.md`](quran-data.md) | Source of truth, versioned import, and how "how much did the student memorize" is computed |
| 5 | [`quran-data-model.md`](quran-data-model.md) | The minimum data model (level A+), what is stored vs. what must not be |
| 6 | [`quran-data-sources-comparison.md`](quran-data-sources-comparison.md) | Engineering study of the two layout projects vs. KFGQPC, with the licensing matrix |
| 7 | [`quran-validation-plan.md`](quran-validation-plan.md) | How the import gate is executed, evidenced and signed off |
| 8 | [`quran-validation-report.md`](quran-validation-report.md) | **First real run against the official artifact — structural PASS, edition FAIL** |
| 9 | [`quran-1405h-sourcing.md`](quran-1405h-sourcing.md) | **Official request package to KFGQPC** — letter, email, technical annex, licensing questions |
| 10 | [`quran-1405h-qul-verification.md`](quran-1405h-qul-verification.md) | **QUL V1 (1405) verification** — edition corroborated, no ayah column, licensing blocked |
| 11 | [`quran-source-record.md`](quran-source-record.md) | Provenance, attribution and licensing record for each imported dataset |
| 12 | [`evaluation.md`](evaluation.md) | Baseline + advanced evaluation, period configuration, snapshots, determinism |
| 13 | [`offline-sync.md`](offline-sync.md) | Outbox, push/pull protocol, conflict matrix |
| 14 | [`api.md`](api.md) | Endpoint surface, conventions, error contract |
| 15 | [`permissions.md`](permissions.md) | Permission catalogue, default matrix, scope rules |
| 16 | [`security.md`](security.md) | Threat model, controls, privacy/retention |
| 17 | [`testing.md`](testing.md) | Test strategy, CI gates, Definition of Done |
| 18 | [`roadmap.md`](roadmap.md) | 10 phases, branches, exit criteria, environment gaps |
| 19 | [`open-decisions.md`](open-decisions.md) | **29 decisions — 5 resolved, 1 partial, 23 open** |
| 20 | [`decisions/`](decisions/) | 9 ADRs for the non-obvious choices |

---

## The five things that most need your attention

**1. Page measurement is data-driven, not a constant (ADR-0003, amended 2026-10-28).**
A range is measured as a sum of page fractions: `amount_pages = Σ covered_lines(p) / lines_count(p)`. There is no `1 page = 15 lines` anywhere in the system — `lines_count` comes from the imported dataset. This satisfies §10 (a surah is not a page) and stays correct on irregular pages, where a fixed-height model understates by ~24%. Every calculated record stores the `quran_dataset_version_id` that produced it, so importing a newer dataset never silently changes a historical amount.

**2. The Quran source is decided — but the official artifact does not match the chosen edition (ADR-0008, ADR-0009, OD-029).**
The first real validation run is complete: the KFGQPC Hafs v3.0 artifact passes **all 12 structural checks** (114 surahs, 6,236 ayahs, 604 pages, no gaps or duplicates) but **encodes the 1441 AH layout**, not the 1405 AH print chosen in OD-026 — 100% match against 1441H line breaks, 20.9% against 1405H. A full sweep of every official channel then found **no published 1405H dataset at all**; the only "1405" on the site is the complex's founding year. Obtaining it requires a written request (`quran-1405h-sourcing.md`). Licensing is also unresolved: no terms are published and the site asserts all rights reserved.

**3. Period boundaries belong to the evaluation, not to a global rule (OD-002 resolved).**
`weekly_evaluation_start_day` is a center-level enum setting — the current center uses `saturday`, but no code may hard-code a day. Each weekly evaluation stores its own `week_start`, `week_end` and `week_start_day`, so changing the setting later cannot move a student's week. Gregorian is canonical; Hijri is display-only (OD-003).

**4. The two evaluation scores never mix (OD-008 resolved).**
`achievement_pct` is uncapped (2.5 pages against 1 = 250%). `weighted_score` is normalised 0–100. Separate columns, separate rendering, no averaging, no substitution.

**5. Conflicts are resolved per entity, never globally (ADR-0005).**
Records are events (keep both), attendance is state (human decides), approved evaluations are server-only. A blanket last-write-wins would silently destroy a teacher's work.

---

## Decisions that block work

| ID | Decision | Blocks |
|---|---|---|
| OD-018 | Dependency versions | **Phase 1** |
| OD-029 | **Edition conflict** — no official artifact proven to represent the 1405H edition; written inquiry prepared | **Phase 2 import** |
| OD-001 | Age representation | Phase 3 |
| OD-004 | Rounding of measured pages | Phase 6 |
| OD-009 | Missing-data policy | Phase 6 |
| OD-011 | Where `required_pages` is configured | Phase 6 |
| OD-015 | Template publishing authority | Phase 6 |
| OD-022 | Deleting a record in an approved week | Phase 6 |
| OD-005 (remainder) | Dataset & font licensing | production bundling only |

Everything else in `open-decisions.md` can be decided in parallel with development.

---

## Environment gap (found during Phase 0 audit)

| Missing | Needed for |
|---|---|
| Flutter SDK / Dart | all mobile work |
| PostgreSQL 16 + `pdo_pgsql` | all backend work (XAMPP PHP is MySQL-only) |
| Docker (optional) | reproducible Postgres + queue worker |
| `gh` CLI (optional) | scripted issues/PRs |

Phase 1 cannot start until the first two are installed. Details in `roadmap.md` §0.

---

## Conventions used in these documents

- `BR-*` — business rule id, referenced by tests.
- `OD-*` — open decision, tracked in `open-decisions.md`.
- `ADR-*` — architecture decision record, in `decisions/`.
- `§n` — section number of the master instruction.
- Status `Proposed` on an ADR means it is not yet ratified by review.
