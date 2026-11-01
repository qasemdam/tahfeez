# Tahfeez — تحفيظ

**نظام إدارة مراكز تحفيظ القرآن الكريم** · Quran memorization center management system

A multi-tenant platform for managing students, teachers, memorization, review, recitation, attendance, weekly evaluation and reporting — working **offline-first** on Android, iOS and Web.

---

## Status

**Phase 1 — vertical slices: delivered.** The API covers the recording path and
the read-only reporting path end to end. The Flutter client implements the
recording flows as screens; for the reports and the dashboard it has the models
and repositories only — no report or dashboard screens exist yet. Phase 2
(production Quran dataset) stays **blocked on OD-029**; the application runs
against a fixture provider.

| Phase | Scope | Status |
|---|---|---|
| 0 | Architecture, data model, API, sync, security, testing, roadmap | ✅ delivered |
| 1 | Vertical slices — auth → dashboard (see below) | ✅ delivered |
| 2 | Quran dataset & page measurement | ⛔ import blocked on OD-029; fixture provider in use |
| 3–10 | Offline-first, sync, exports, hardening, release | not started |

### Implemented

- Authentication (email + password, device-scoped bearer token)
- Students
- Groups + teacher assignment history
- Attendance
- Memorization
- Review
- Recitation
- Evaluation Engine (pure, deterministic, versioned rule sets)
- Weekly Evaluation (historical snapshots, approval)
- Weekly Report
- Monthly Report
- Dashboard Foundation

### Not implemented yet

- Offline-first (local database + outbox)
- Sync Engine
- Backup / Restore
- PDF Export · Excel Export · Print
- Formal security review · formal performance review
- Production Quran 1405H master dataset — the source and its licensing are
  still unresolved (**OD-005**, **OD-029**). This is a known gap of the data
  layer, not of the MVP flow: the app measures through `QuranDataProvider`,
  which is backed by a fixture in development and tests.

Current state, measured on this tree: **43 API routes**, **324 tests /
1203 assertions**, `pint --test` clean. `flutter analyze` / `flutter test` have
not been run here because the Flutter SDK is not installed in this environment,
so the mobile code is reviewed but not verified by a compiler in CI.

### Where the detail lives

- [`docs/roadmap.md`](docs/roadmap.md) §0.2 — current state, slice by slice.
- [`docs/open-decisions.md`](docs/open-decisions.md) — what is decided and what is not.
- [`docs/api.md`](docs/api.md) — the endpoint surface as implemented.
- [`docs/business-rules.md`](docs/business-rules.md) — every enforced rule.

---

## Architecture

```text
Flutter (Android / iOS / Web)
   │  local-first: Drift/SQLite + outbox
   ↓  REST /api/v1 + sync push/pull
Laravel (modular monolith, PHP 8.2+)
   ↓
PostgreSQL 16 (shared schema, center_id scoped)
```

Key design decisions:
- **Quran master data from KFGQPC, imported as immutable versioned snapshots** — every calculated amount is traceable to a dataset version ([ADR-0008](docs/decisions/ADR-0008-quran-master-data-source.md)).
- **Madinah-layout projects are references, not sources** — their data is a rendering recipe, hardcodes 15 lines/page, and carries a copyleft licence ([ADR-0009](docs/decisions/ADR-0009-layout-projects-as-references.md)).
- **Page measurement as a sum of page fractions** — page height comes from the data, a surah is never assumed to be a page ([ADR-0003](docs/decisions/ADR-0003-page-measurement.md)).
- **Versioned, declarative evaluation engine** — weights, criteria and recitation rules are data, not code ([ADR-0004](docs/decisions/ADR-0004-evaluation-rule-engine.md)).
- **Per-entity conflict resolution** — no blanket last-write-wins ([ADR-0005](docs/decisions/ADR-0005-sync-and-conflict.md)).
- **Server-authoritative derived values** — the client previews, the server decides ([ADR-0007](docs/decisions/ADR-0007-server-authoritative-logic.md)).

---

## Repository layout

```text
tahfeez/
├── mobile/          # Flutter app
├── backend/         # Laravel API (tahfeez-api)
├── docs/            # architecture, rules, ERD, API, sync, ADRs
├── README.md
└── .gitignore
```

Directories exist and carry the Phase 1 code; the Phase 0 documents remain the
specification they were written against.

---

## Documentation

| Document | Contents |
|---|---|
| [docs/business-rules.md](docs/business-rules.md) | All enforced rules with IDs |
| [docs/architecture.md](docs/architecture.md) | Layers, modules, folder structure |
| [docs/database.md](docs/database.md) | ERD, tables, indexes |
| [docs/quran-data.md](docs/quran-data.md) | Dataset source, versioning & page measurement |
| [docs/quran-data-model.md](docs/quran-data-model.md) | Minimum data model (level A+) |
| [docs/quran-data-sources-comparison.md](docs/quran-data-sources-comparison.md) | Study of the two Madinah-layout projects vs KFGQPC + licensing matrix |
| [docs/quran-validation-plan.md](docs/quran-validation-plan.md) | Import gate execution & sign-off |
| [docs/quran-validation-report.md](docs/quran-validation-report.md) | First run against the official artifact (structural PASS, edition FAIL) |
| [docs/quran-1405h-sourcing.md](docs/quran-1405h-sourcing.md) | Official request package to KFGQPC (letter, email, technical annex, licensing) |
| [docs/quran-1405h-qul-verification.md](docs/quran-1405h-qul-verification.md) | QUL V1 (1405) verification: edition corroborated, no ayah column, licensing blocked |
| [docs/quran-source-record.md](docs/quran-source-record.md) | Provenance & licensing record |
| [docs/evaluation.md](docs/evaluation.md) | Evaluation engine |
| [docs/offline-sync.md](docs/offline-sync.md) | Sync protocol & conflicts |
| [docs/api.md](docs/api.md) | API surface |
| [docs/permissions.md](docs/permissions.md) | Roles & scopes |
| [docs/security.md](docs/security.md) | Threat model & controls |
| [docs/testing.md](docs/testing.md) | Test strategy |
| [docs/roadmap.md](docs/roadmap.md) | Phases & exit criteria |
| [docs/open-decisions.md](docs/open-decisions.md) | Decisions awaiting input |

---

## Branching

```text
main        ← stable, protected, release-only
develop     ← integration
feature/*   ← one feature per branch
fix/*       ← one defect per branch
refactor/*  ← internal change, no behaviour change
```

Conventional Commits. No force push, no history rewriting, no direct commits to `main`.
A feature is done only after: code → tests → review → PR → merge → docs.

---

## Getting started

### Backend (Laravel 12 / PHP 8.2)

```bash
cd backend
composer install
cp .env.example .env          # if .env is absent
php artisan key:generate
php artisan migrate
php artisan serve             # http://127.0.0.1:8000
php artisan test              # 324 tests
vendor/bin/pint --test
```

The test suite runs on SQLite in memory; the production target is PostgreSQL 16.
The API is served under `/api/v1` and authenticated with a bearer token issued
by `POST /api/v1/auth/login`.

### Mobile (Flutter)

Requires a Flutter SDK and the Android toolchain — neither is installed in the
environment this repository was built in.

```bash
cd mobile
flutter pub get
flutter run
```

The API base URL lives in `mobile/lib/core/config/app_config.dart`.

---

## License

Proprietary. All rights reserved. Quran text and fonts are subject to their own licences — see OD-005.
