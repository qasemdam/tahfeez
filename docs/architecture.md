# Tahfeez — System Architecture

Status: **Draft for review** · Phase 0
Companion docs: `business-rules.md`, `database.md`, `api.md`, `offline-sync.md`, `evaluation.md`, `quran-data.md`, `security.md`, `testing.md`, `roadmap.md`

---

## 1. Architectural drivers

| Driver | Consequence |
|--------|-------------|
| Works offline in a classroom with no connectivity (§18) | Local-first client, outbox queue, server-side idempotency |
| Deterministic business logic (§21) | Pure calculators, versioned rules, no AI, no `eval()` |
| Correct page measurement (§9, §10) | Explicit line-coordinate model of the mushaf, not heuristics |
| Auditability of grades (§13, §20) | Immutable snapshots, revisioned evaluations, append-only audit log |
| Multi-center SaaS (§7, §25) | Shared-schema tenancy with `center_id`, structural isolation |
| MVP scope discipline (§27) | Modular monolith; no microservices, no event bus, no CQRS |

**Explicit non-goals for MVP:** microservices, multi-region deployment, real-time collaboration, push notifications, billing, region management, AI assistance.

---

## 2. System topology

```text
┌──────────────────────────────────────────────────────────────┐
│  Flutter client (Android / iOS / Web)                        │
│                                                              │
│  Presentation (Riverpod + go_router)                         │
│        ↓                                                     │
│  Domain (entities, use-cases, pure calculators)              │
│        ↓                                                     │
│  Data (repositories)                                         │
│     ├── Local: Drift/SQLite  ── outbox + change inbox        │
│     └── Remote: Dio → REST /api/v1                           │
│        ↓                                                     │
│  Sync Engine (push/pull, conflict handling, backoff)         │
└───────────────────────────┬──────────────────────────────────┘
                            │ HTTPS / JSON
┌───────────────────────────▼──────────────────────────────────┐
│  Laravel API (modular monolith)                              │
│                                                              │
│  HTTP layer: Routes → FormRequest → Controller → Resource    │
│        ↓                                                     │
│  Domain layer: Actions / Services / Calculators / Policies   │
│        ↓                                                     │
│  Persistence: Eloquent models + query scopes (tenant)        │
│                                                              │
│  Cross-cutting: Auth (Sanctum), Authorization (Policies),     │
│  Audit, Idempotency, Rate limiting, Queues (Horizon)         │
└───────────────────────────┬──────────────────────────────────┘
                            │
                  ┌─────────▼─────────┐
                  │   PostgreSQL 16   │
                  │  shared schema,   │
                  │  center_id scoped │
                  └───────────────────┘
```

One deployable backend. One database. The client is the only place where offline state exists.

---

## 3. Why a modular monolith (ADR-0001)

- The domain is deeply relational (student ↔ teacher ↔ week ↔ evaluation). Microservices would force distributed transactions across exactly the operations that must be atomic (record + evaluation + audit).
- Team size is small; operational cost of multiple services is unjustified.
- Module boundaries are enforced by directory + namespace, so extraction is possible later without a rewrite.

Rule: a module may not import another module's `Models` directly. Cross-module reads go through the owning module's **Query/Action** class. This keeps boundaries honest and is enforceable in review.

---

## 4. Backend module map

| Module | Owns | Key responsibilities |
|--------|------|----------------------|
| `Tenancy` | `centers`, settings | Center resolution, timezone, week configuration |
| `Identity` | `users`, roles, devices, tokens | Auth, roles/permissions, device registry |
| `Students` | `students`, `student_teacher_assignments` | Student lifecycle, assignment history |
| `Quran` | `quran_dataset_versions`, `quran_surahs`, `quran_ayahs`, `quran_pages` | KFGQPC import + validation gate, immutable versioned snapshots, active-version context, range resolution, measurement |
| `Memorization` | `memorizations` | New hifz records |
| `Review` | `reviews` | Murajaʿah records |
| `Recitation` | `recitations` | Tasmiʿ records + scoring |
| `Attendance` | `attendances` | Daily attendance, bulk upsert |
| `Evaluation` | templates, criteria, profiles, weekly evaluations | Rule engine, snapshots, computation |
| `Reporting` | read models, exports | Aggregations, PDF/Excel jobs |
| `Audit` | `audit_logs` | Append-only trail |
| `Sync` | `change_log`, `sync_operations` | Push/pull protocol, idempotency |

`Quran` is the only module with **no** `center_id` — it is global reference data.

---

## 5. Request pipeline (backend)

```text
Route (v1, auth:sanctum, throttle)
  → ResolveCenter (middleware: binds center from token/user)
  → FormRequest (validation, normalization, authorization hook)
  → Policy (record-level: same center? assigned teacher? permission?)
  → Action (single-purpose class, DB transaction, domain rules)
  → Domain event (in-process, for audit + change_log)
  → API Resource (explicit field whitelist, no model leakage)
```

Design constraints:
- **Controllers are thin.** No business logic, no queries beyond `Action` calls.
- **Actions are the unit of business logic** — one public `execute()` method, transaction boundary, fully testable without HTTP.
- **No fat models.** Eloquent models hold relations, casts, scopes; not rules.
- **Money-like decimals** (pages, percentages) use `numeric` columns and `bcmath`/string math — never PHP floats.

---

## 6. Flutter architecture

Layered, feature-first, with a shared sync core.

```text
lib/
├── main.dart
├── bootstrap.dart              # env, DI overrides, error handlers, sync bootstrap
├── app/
│   ├── app.dart                # MaterialApp.router
│   ├── router/                 # go_router config, guards, deep links
│   ├── theme/                  # design tokens, light/dark, RTL-aware
│   └── l10n/                   # ar/en ARB files
├── core/
│   ├── config/                 # env, feature flags, constants
│   ├── database/               # Drift schema, migrations, DAOs
│   ├── network/                # Dio client, interceptors, error mapping
│   ├── sync/                   # outbox, pusher, puller, conflict store
│   ├── auth/                   # session, token storage (secure storage)
│   ├── error/                  # Failure types, result types
│   ├── logging/                # structured logs, crash reporting hooks
│   └── utils/                  # date/hijri, decimals, validators
├── features/
│   ├── auth/
│   ├── students/
│   ├── memorization/
│   ├── review/
│   ├── recitation/
│   ├── attendance/
│   ├── evaluation/
│   ├── reports/
│   └── settings/
│       ├── data/               # dto, local dao, remote api, repository impl
│       ├── domain/             # entities, use-cases, repository contract
│       └── presentation/       # pages, widgets, controllers (Riverpod)
└── shared/                     # reusable widgets, formatters, extensions
```

Rules:
- `presentation` never touches Drift or Dio directly.
- `domain` has **no** Flutter imports (pure Dart) — this is what makes unit tests fast.
- Every repository is an interface in `domain` with two implementations: local-first and remote.
- The client **previews** page calculations locally but always displays server-confirmed values once synced.

---

## 7. Technology choices

| Layer | Choice | Rationale | Alternative rejected |
|-------|--------|-----------|----------------------|
| Client framework | Flutter (Dart 3) | Single codebase for Android/iOS/Web, strong RTL | — |
| State | Riverpod (with codegen) | Compile-safe DI + testable async state | Provider (weak async), Bloc (verbose for CRUD) |
| Local DB | Drift (SQLite) | Typed queries, migrations, reactive streams, works on Web via WASM | sqflite (no codegen), Hive (no relational queries) |
| HTTP | Dio + interceptors | Retry, auth refresh, logging | http (too thin) |
| Routing | go_router | Declarative, guards, web URLs | Navigator 1.0 |
| Models | freezed + json_serializable | Immutability, unions for sync states | manual |
| Backend | Laravel (PHP 8.2+) | Team familiarity, batteries included, policies/queues | Symfony (more boilerplate) |
| DB | PostgreSQL 16 | `jsonb`, partial indexes, window functions, `numeric` | MySQL (weaker json/partial index) |
| Auth | Sanctum (device tokens) | First-party, simple, revocable | Passport/OAuth (overkill) |
| Permissions | spatie/laravel-permission (center-scoped) | Mature, policy-friendly | custom (reinventing) |
| Queue | Laravel Queue + Horizon | Exports, sync fan-out, report aggregation | — |
| PDF | Blade + Arabic-capable font engine | Full control over RTL layout | client-side PDF (font/RTL risk) |
| Excel | PhpSpreadsheet (Laravel Excel wrapper) | Standard | CSV only (insufficient) |
| Tests | Pest (backend), flutter_test + integration_test | Concise, expressive | PHPUnit raw |

Version pinning happens in Phase 1 after `composer create-project` / `flutter create` reports what is actually installable in this environment. **[OD-018]**

---

## 8. Repository layout (monorepo)

```text
tahfeez/
├── .github/
│   └── workflows/          # backend-ci, mobile-ci, docs-lint
├── backend/                # Laravel app (tahfeez-api)
│   ├── app/
│   │   ├── Domain/<Module>/{Actions,Models,Data,Enums,Policies,Rules,Calculators,Services}
│   │   ├── Http/{Controllers/Api/V1,Middleware,Requests,Resources}
│   │   ├── Providers/
│   │   └── Support/        # decimals, week resolver, tenant scope
│   ├── database/{migrations,seeders,factories}
│   ├── routes/{api.php,console.php}
│   ├── tests/{Unit,Feature,Architecture}
│   ├── config/
│   └── composer.json
├── mobile/                 # Flutter app
│   ├── lib/                # see §6
│   ├── test/
│   ├── integration_test/
│   ├── assets/{quran/,fonts/,l10n/}
│   └── pubspec.yaml
├── docs/
│   ├── README.md
│   ├── architecture.md
│   ├── business-rules.md
│   ├── database.md
│   ├── api.md
│   ├── offline-sync.md
│   ├── evaluation.md
│   ├── quran-data.md
│   ├── permissions.md
│   ├── security.md
│   ├── testing.md
│   ├── roadmap.md
│   ├── open-decisions.md
│   └── decisions/ADR-*.md
├── docker-compose.yml      # postgres + api + horizon (Phase 1)
├── Makefile                # make up / test / lint / migrate
├── .env.example
├── .gitignore
└── README.md
```

---

## 9. Environments

| Env | Purpose | Data |
|-----|---------|------|
| `local` | Developer machine, docker-compose Postgres | Seeders + fake students |
| `staging` | Review builds, sync soak tests | Anonymised fixture set |
| `production` | Live centers | Real data, backups, monitoring |

Config is environment-driven; no environment is hard-coded. Secrets come from environment variables / secret store, never from the repo (§20).

---

## 10. Cross-cutting concerns

**Audit.** Implemented as an in-process domain listener writing to `audit_logs` inside the same transaction as the change. If the audit write fails, the business transaction fails — the trail cannot have gaps.

**Change log for sync.** Same transaction as the write, monotonic `bigserial` sequence per center. This is what makes the pull cursor trustworthy.

**Idempotency.** Every mutating sync endpoint requires an `op_id`; duplicates return the original result instead of re-applying.

**Time.** All timestamps are stored as `timestamptz` in UTC. Business dates (`session_date`, week boundaries) are `date` in the center's timezone, and the canonical calendar is Gregorian. The weekly period start day is **center configuration** (`weekly_evaluation_start_day`, an enum), not a constant — the current center uses `saturday`. Conversion and period resolution happen once, in `WeekResolver` / `BusinessDate` support classes, never ad hoc in controllers, and each weekly evaluation stores the boundaries it was computed with so a later setting change cannot rewrite history.

**Quran context.** Quran tables are version-keyed; every request resolves the active dataset version once (`QuranContext`) and all reads go through it. There is no unversioned Quran table to query by accident.

**Decimals.** A single `Decimal` value object wraps `bcmath` with fixed scale; used for pages, percentages and weights. Prevents the classic `0.1 + 0.2` drift in evaluation math.

---

## 11. Scalability posture (§25)

- All list endpoints are paginated (cursor-based) with a hard max page size.
- Composite indexes always lead with `center_id`.
- Reference data (Quran, templates) cached in Redis with version keys.
- Report aggregation is precomputed into weekly evaluation rows; dashboard reads aggregates, not raw scans.
- API Resources use `whenLoaded` to prevent N+1; CI runs a query-count assertion on hot endpoints.
- Sync pull is bounded (`limit` + `cursor`), so a large center cannot request its entire history in one call.

Target envelope for MVP architecture: tens of thousands of students, hundreds of centers, without schema changes — only index tuning and read replicas if needed.

---

## 12. Extension path (documented, not built)

```text
Region (future)
   └── Center (MVP)
         ├── Users (MVP)
         └── Students (MVP)
```

Adding regions later = new `regions` table + nullable `centers.region_id` + a scope. No existing table changes shape, because `center_id` is already the tenant boundary everywhere.
