# Tahfeez — Development Roadmap

Status: **Draft for review** · Phase 0
Each phase is independently runnable, testable and reviewable (§1). No phase starts before the previous one's exit criteria are met.

---

## 0. Environment readiness (blocking finding)

Phase 1 cannot begin until the toolchain exists. Current machine audit:

| Requirement | Status | Action |
|---|---|---|
| PHP 8.2.12 (XAMPP) | ✅ present | OK for Laravel 11 |
| Composer 2.10 | ✅ present | OK |
| `pdo_pgsql` extension | ❌ missing | Enable in `php.ini` (Laravel needs Postgres, not MySQL) |
| PostgreSQL 16 server | ❌ not installed | Install + create `tahfeez` dev DB |
| Docker / docker-compose | ❌ not installed | Recommended for Postgres + queue worker parity |
| Flutter SDK + Dart | ❌ not installed | Install, `flutter doctor`, Android toolchain |
| Android SDK / emulator | ❓ unknown | Verify before Phase 3 UI work |
| `gh` CLI | ❌ not installed | Optional; needed for scripted issues/PRs |

Note: XAMPP's PHP is built for MySQL. Either enable `pdo_pgsql` there or use the Docker PHP image — decide in Phase 1 (**OD-018**).

---

## Phase 1 — Project foundation

**Branch:** `feature/project-foundation`
**Goal:** a running, tested, empty-but-real skeleton. Nothing domain-specific.

Deliverables
- Monorepo scaffolding: `backend/`, `mobile/`, `.github/workflows/`, `Makefile`, `docker-compose.yml`, `.gitignore`, `.env.example`.
- Laravel app: `/api/v1` routing, Sanctum, `ResolveCenter` middleware, problem+json error handler, `/health`.
- Flutter app: bootstrap, theme (light/dark, RTL-aware), go_router, Riverpod, Dio client, Drift skeleton, `ar`/`en` l10n.
- Auth vertical slice: login → token → `/auth/me`, device registration, logout.
- CI: pint, phpstan, pest, `flutter analyze`, `flutter test`, migration check.
- Architecture test: module boundaries enforced.

Tests: login/logout/refresh, throttle, tenant middleware, health, one widget test per platform shell.
Exit criteria: `make up && make test` green from a clean clone; CI green on PR.

---

## Phase 2 — Quran dataset & measurement engine

**Branch:** `feature/quran-data`
**Depends on:** **OD-005** (dataset + font licence)
**Goal:** the measurement engine, proven before anything depends on it.

Deliverables
- Migrations + importer (`quran:import`) with the 8-point validation gate (`quran-data.md` §6).
- `PageMeasurement` service (PHP) + Dart mirror.
- `POST /quran/measure`, `GET /quran/meta|surahs|ayahs|pages`.
- `docs/fixtures/page-measurement.json` (≥300 vectors, 20 hand-verified against the printed mushaf).
- Mobile: bundled dataset asset + local measurement.

Tests: golden vectors in both languages, additivity/monotonicity properties, importer rejection cases, boundary pages (1, 2, 604), page-spanning ayah, cross-surah range.
Exit criteria: both implementations pass the identical fixture file; importer fails loudly on a corrupted dataset.

**This phase is the correctness backbone.** A measurement bug discovered in Phase 6 invalidates every stored record.

---

## Phase 3 — Students & teachers

**Branch:** `feature/student-management`
**Depends on:** OD-001, OD-016, OD-021
**Goal:** the people layer, with assignment history and scope enforcement.

Deliverables
- `students`, `student_teacher_assignments`, factories, seeders.
- CRUD endpoints + policies + assignment endpoint + timeline.
- Teacher management + permission matrix endpoints.
- Mobile: student list (scoped), search, create/edit, assignment UI.
- Local-first reads with the outbox envelope already in place.

Tests: one-active-assignment invariant, history preserved on reassignment, scope (teacher sees only assigned), 404 vs 403, permission matrix.
Exit criteria: a teacher account can only ever see their own students — verified by test, not by inspection.

---

## Phase 4 — Memorization, review, recitation

**Branch:** `feature/memorization` → `feature/review` → `feature/recitation` (separate PRs)
**Depends on:** OD-006, OD-010
**Goal:** the daily-use recording flow, usable offline from the first commit.

Deliverables
- Three record types with shared measurement block; `amount_source` enforced.
- Server recomputation of `CALCULATED` amounts; MANUAL validation.
- Recitation rule sets + scoring (`rule_sets`).
- Mobile: record forms with local measurement preview, offline queue, conflict states.
- `GET /students/{id}/timeline`.

Tests: CALCULATED vs MANUAL, manual never overwritten, range validation, cross-surah, rule-set totality, offline create → sync, duplicate op id.
Exit criteria: 20 records created in airplane mode sync exactly once.

---

## Phase 5 — Attendance

**Branch:** `feature/attendance`
**Depends on:** OD-007
**Goal:** fast daily attendance without duplicates.

Deliverables
- `attendances` with the unique `(center, student, date)` constraint.
- Bulk day upsert (idempotent), late minutes, notes.
- Mobile: class roster with one-tap statuses, offline.
- Attendance report data endpoint.

Tests: bulk idempotency, duplicate day conflict, no attendance before enrollment, offline bulk sync.
Exit criteria: submitting the same day twice creates zero duplicates.

---

## Phase 6 — Evaluation engine

**Branch:** `feature/evaluation`
**Depends on:** **OD-002, OD-003, OD-004, OD-008, OD-009, OD-011, OD-015, OD-022** — the heaviest decision gate
**Goal:** baseline + advanced evaluation with snapshots and versioning.

Deliverables
- Templates → versions → criteria, with publish validation (weights = 100).
- Profiles → versions with `effective_from`, `required_pages`.
- Five calculators + aggregation + missing-data policies.
- `weekly_evaluations` with `components` + `rules_snapshot`, revision policy.
- Compute (queued), approve, recompute endpoints.
- Mobile: student week view, manager approval screen.

Tests: the six §11 rows, 250% case, decimal exactness, weight validation, profile boundary week, no retroactive change, idempotent recompute, attendance opt-in, missing-data policies, determinism property test.
Exit criteria: two runs on the same week produce byte-identical components; an approved week is provably immutable.

---

## Phase 7 — Reports & exports

**Branch:** `feature/reports`
**Depends on:** OD-014, OD-024
**Goal:** the artifacts a center actually hands to parents.

Deliverables
- Dashboard aggregates, weekly/monthly/student/attendance/memorization/review/recitation reports, rankings, teacher performance.
- Async export jobs: PDF (RTL, Arabic font), Excel, print view.
- Signed, expiring download URLs; export audit.

Tests: scope in reports, ranking determinism, export TTL, PDF renders Arabic correctly (visual fixture), large-range job completes.
Exit criteria: a parent-ready weekly PDF with correct Arabic shaping and correct numbers.

---

## Phase 8 — Sync hardening

**Branch:** `feature/offline-sync` + `fix/*` for defects found
**Depends on:** OD-012, OD-019, OD-023
**Goal:** turn the working outbox into a provably correct sync engine.

Deliverables
- `change_log` + `sync_operations` + pull cursor + retention job.
- Per-entity conflict policies (`offline-sync.md` §5) + conflict UI.
- Field-level merge, dependency ordering, backoff, crash recovery.
- Local DB encryption.

Tests: two-device simulation per entity, partial batch failure, `410` full resync, retry storm, token expiry mid-queue, conflict resolution paths.
Exit criteria: the fake-device harness passes the full conflict matrix; no data loss in any scenario.

---

## Phase 9 — Hardening

**Branch:** `refactor/*` and `fix/*` only
**Goal:** production readiness.

- Performance: index review, N+1 sweep, dashboard timing, weekly compute at 500 students.
- Security: IDOR sweep, tenant-boundary sweep, dependency audit, penetration checklist.
- Observability: structured logs, error tracking, queue monitoring, backup + restore drill.
- Documentation: final pass on all `docs/`.

Exit criteria: performance thresholds in `testing.md` §6 met; restore drill passed.

---

## Phase 10 — Release

**Branch:** `develop` → PR → `main`
**Goal:** first production release for a pilot center.

- UAT with one real center, one week of parallel paper+app operation.
- Migration/seed runbook, rollback plan, on-call notes.
- Tag `v0.1.0`, release notes, branch protection verified on `main`.

---

## Feature workflow (applies to every phase)

```text
Requirement → GitHub Issue → feature/* branch → Implementation
   → Tests → Review → PR (Closes #n) → merge to develop
```

Per-feature pre-flight and post-flight reports follow §14 / §15 of the GitHub workflow instruction. No feature is "done" on code alone.

---

## Explicitly deferred (Future Features)

Region management · multi-qiraʾat · audio recitation · push notifications · parent portal · billing · AI-assisted analysis · 2FA · multi-center teachers · per-center branding · offline permission management.

These are recorded so they are not implemented by accident.
