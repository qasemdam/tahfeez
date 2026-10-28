# Tahfeez — Documentation Index

**Phase 0 — Architecture & Project Foundation** · Status: *awaiting review* · 2026-10-28

No application code has been written. This is intentional: the master instruction (§28) requires Phase 0 to end with a review gate, and §22 forbids inventing undefined business rules.

---

## Reading order for review

| # | Document | What it answers |
|---|---|---|
| 1 | [`business-rules.md`](business-rules.md) | Every rule the system enforces, with IDs (`BR-*`) and source section |
| 2 | [`architecture.md`](architecture.md) | Layers, modules, folder structure, technology choices |
| 3 | [`database.md`](database.md) | ERD, tables, columns, constraints, indexes, migration order |
| 4 | [`quran-data.md`](quran-data.md) | How "how much did the student memorize" is actually computed |
| 5 | [`evaluation.md`](evaluation.md) | Baseline + advanced evaluation, versioning, snapshots, determinism |
| 6 | [`offline-sync.md`](offline-sync.md) | Outbox, push/pull protocol, conflict matrix |
| 7 | [`api.md`](api.md) | Endpoint surface, conventions, error contract |
| 8 | [`permissions.md`](permissions.md) | Permission catalogue, default matrix, scope rules |
| 9 | [`security.md`](security.md) | Threat model, controls, privacy/retention |
| 10 | [`testing.md`](testing.md) | Test strategy, CI gates, Definition of Done |
| 11 | [`roadmap.md`](roadmap.md) | 10 phases, branches, exit criteria, environment gaps |
| 12 | [`open-decisions.md`](open-decisions.md) | **24 decisions that need your input** |
| 13 | [`decisions/`](decisions/) | 7 ADRs for the non-obvious choices |

---

## The three things that most need your attention

**1. Page measurement is line-based, not page-counted (ADR-0003).**
A range is measured in mushaf lines: `amount_lines = global_end − global_start + 1`, `pages = lines / 15`. This is the only approach that satisfies §10 (a surah is not a page) and produces exact values like 2.5 pages. It requires a dataset with page **and** line coordinates for both ends of every ayah — a stricter requirement than most public Quran datasets. **OD-005 blocks Phase 2.**

**2. The week boundary is undefined in the spec (OD-002 / OD-003).**
Weekly evaluation cannot be built until "which day starts the week" and "which calendar" are decided. Building first and deciding later means migrating historical grades.

**3. Conflicts are resolved per entity, never globally (ADR-0005).**
Records are events (keep both), attendance is state (human decides), approved evaluations are server-only. A blanket last-write-wins would silently destroy a teacher's work.

---

## Decisions that block work

| ID | Decision | Blocks |
|---|---|---|
| OD-005 | Quran dataset + font licence | Phase 2 |
| OD-002 | Week start day | Phase 6 |
| OD-003 | Hijri vs Gregorian | Phase 6 |
| OD-004 | Rounding of measured pages | Phase 6 |
| OD-018 | Dependency versions | Phase 1 |
| OD-001 | Age representation | Phase 3 |

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
