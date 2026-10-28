# Tahfeez — تحفيظ

**نظام إدارة مراكز تحفيظ القرآن الكريم** · Quran memorization center management system

A multi-tenant platform for managing students, teachers, memorization, review, recitation, attendance, weekly evaluation and reporting — working **offline-first** on Android, iOS and Web.

---

## Status

**Phase 0 — Architecture & Project Foundation: complete, awaiting review.**
No application code exists yet. See [`docs/README.md`](docs/README.md) for the review package.

| Phase | Scope | Status |
|---|---|---|
| 0 | Architecture, data model, API, sync, security, testing, roadmap | ✅ delivered |
| 1 | Project foundation (Laravel + Flutter skeletons, auth, CI) | ⏸ blocked on environment |
| 2 | Quran dataset & page measurement | ⏸ blocked on OD-005 |
| 3–10 | Students → Records → Attendance → Evaluation → Reports → Sync → Release | planned |

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
- **Page measurement by mushaf line coordinates** — a surah is never assumed to be a page ([ADR-0003](docs/decisions/ADR-0003-page-measurement.md)).
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

Directories are created in Phase 1; Phase 0 ships documentation only.

---

## Documentation

| Document | Contents |
|---|---|
| [docs/business-rules.md](docs/business-rules.md) | All enforced rules with IDs |
| [docs/architecture.md](docs/architecture.md) | Layers, modules, folder structure |
| [docs/database.md](docs/database.md) | ERD, tables, indexes |
| [docs/quran-data.md](docs/quran-data.md) | Dataset & page measurement |
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

Not available yet — Phase 1 sets up the toolchain and `make` targets. Prerequisites are listed in [docs/roadmap.md](docs/roadmap.md) §0.

---

## License

Proprietary. All rights reserved. Quran text and fonts are subject to their own licences — see OD-005.
