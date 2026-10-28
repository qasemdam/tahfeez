# Tahfeez — Testing Strategy

Status: **Draft for review** · Phase 0
Implements §24. A feature is not done without tests that fail when the rule is removed.

---

## 1. Principle

Coverage percentage is not the goal. **Rule coverage** is: every `BR-*` id in `business-rules.md` maps to at least one test that names it. A test suite that covers 90% of lines but misses the 250% case is a failing suite.

Test naming convention makes the mapping mechanical:

```php
it('computes 250% for 2.5 pages against a 1 page requirement [BR-EVL-04]', …);
```

```dart
test('computes 250% for 2.5 pages against 1 page requirement [BR-EVL-04]', …);
```

---

## 2. Pyramid

```text
        ┌──────────────────────┐
        │  E2E / integration   │  few, slow, high confidence
        ├──────────────────────┤
        │  Feature / API tests │  most backend rules live here
        ├──────────────────────┤
        │  Unit tests          │  calculators, decimals, measurement
        └──────────────────────┘
```

| Layer | Backend | Mobile |
|---|---|---|
| Unit | Pest `tests/Unit` | `flutter test` on `domain/` and `core/` |
| Feature/API | Pest `tests/Feature` with DB | repository tests against a fake API + in-memory Drift |
| Integration | HTTP-level flows | `integration_test` on device/emulator |
| Architecture | Pest arch tests (module boundaries) | custom lint: `domain` must not import Flutter |

---

## 3. Backend test suites

| Suite | Covers |
|---|---|
| `Unit/Quran/PageMeasurementTest` | page-fraction arithmetic, irregular page heights, boundaries, cross-surah, page-break ayah, golden fixtures |
| `Feature/Quran/DatasetImportTest` | V-01…V-12 rejection cases, no partial import on failure, checksum mismatch, page-break normalisation, version isolation (import v2 → v1 records unchanged) |
| `Unit/Evaluation/BaselineAchievementTest` | the six §11 rows, 250% case, decimal exactness |
| `Unit/Evaluation/CriterionCalculatorTest` | each of the five calculators, params, missing-data policies |
| `Unit/Evaluation/RuleSetEvaluatorTest` | banded rules, totality, invalid rule rejection |
| `Feature/Evaluation/PublishTemplateTest` | weights must total 100, immutability after publish |
| `Feature/Evaluation/WeeklyEvaluationTest` | snapshot content, profile boundary week, idempotent recompute, no retroactive change |
| `Feature/Evaluation/PeriodConfigTest` | `weekly_evaluation_start_day` is configuration not a constant; changing it does not mutate stored periods; overlap guard rejects an unsafe `effective_from`; aggregation ignores `week_key` |
| `Feature/Evaluation/TwoScoresTest` | `achievement_pct` uncapped (250/300%), `weighted_score` clamped 0–100, the two never derived from each other |
| `Feature/Students/AssignmentTest` | one active assignment, history preserved, scope |
| `Feature/Records/MemorizationTest` | CALCULATED vs MANUAL, server recompute, range validation |
| `Feature/Attendance/BulkTest` | idempotent day upsert, unique constraint behaviour |
| `Feature/Auth/*` | login, refresh, revoke, throttling |
| `Feature/Authorization/PermissionMatrixTest` | every permission × role × scope (see `permissions.md` §7) |
| `Feature/Sync/PushTest` | idempotency, partial batch failure, dependency ordering, version conflict |
| `Feature/Sync/PullTest` | cursor monotonicity, tombstones, retention `410` |
| `Feature/Sync/ConflictTest` | two-device simulation for each entity policy |
| `Feature/Reports/ExportTest` | async job, scope, signed URL expiry |
| `Architecture/ModuleBoundaryTest` | `Domain/A` must not reference `Domain/B/Models` |

---

## 4. Mobile test suites

| Suite | Covers |
|---|---|
| `test/core/sync/outbox_test.dart` | append-in-same-transaction, ordering, retry state machine |
| `test/core/sync/conflict_store_test.dart` | conflict persistence, no silent drop |
| `test/features/memorization/measurement_test.dart` | same golden fixtures as PHP |
| `test/features/*/repository_test.dart` | local-first reads, optimistic writes, rollback on failure |
| `test/features/*/controller_test.dart` | Riverpod state transitions, error surfaces |
| `test/widget/*` | RTL/LTR layout, form validation, empty states |
| `integration_test/offline_flow_test.dart` | airplane mode → 20 records → reconnect → all synced |

**Cross-platform contract:** `docs/fixtures/page-measurement.json` is loaded by both `PageMeasurementTest` (PHP) and `measurement_test.dart` (Dart). If the two implementations drift, both CI pipelines fail on the same fixture.

---

## 5. Property-based tests (high value, low cost)

| Property | Assertion |
|---|---|
| Additivity | `measure(A→C) = measure(A→B) + measure(B+1→C)` for random A ≤ B < C |
| Monotonicity | Extending the end ayah never decreases the amount |
| Bounds | `0 < amount ≤ 604` for any valid range |
| Determinism | Same input 100× ⇒ identical output; shuffled record order ⇒ identical components |
| Weight invariance | Sum of weights = 100 ⇒ weighted score ∈ [0, 100] when all criteria capped |

---

## 6. Performance tests

| Test | Threshold |
|---|---|
| `GET /students` (5k students, 100 pages) | p95 < 300 ms, ≤ 6 queries |
| `GET /memorizations?student_id=` (2k rows) | p95 < 200 ms, ≤ 4 queries |
| Weekly compute for 500 students | < 30 s in a queued job |
| Sync push of 100 operations | p95 < 2 s |
| Dashboard for 2k students | p95 < 500 ms (reads aggregates) |

Query-count assertions live in the feature tests, not only in a separate benchmark suite — N+1 regressions are caught on the PR that introduces them.

---

## 7. CI gates

```text
backend:  pint --test → phpstan (level 6) → pest (unit+feature+arch) → migration:fresh + seed
mobile:   dart format --set-exit-if-changed → flutter analyze → flutter test → build (debug)
docs:     markdown link check → OpenAPI drift check
```

A PR cannot merge to `develop` unless all gates pass. No `--no-verify`, no skipped tests, no `@group('skip')` without a linked issue.

---

## 8. Test data

- Factories for every model, with named states: `Student::factory()->assignedTo($teacher)`.
- A `QuranFixtureSeeder` loads a **small deterministic subset** for unit tests (e.g. 3 surahs) and the full dataset only for integration/import tests — keeping the unit suite fast.
- Time is frozen in tests (`Carbon::setTestNow`), never real `now()`.
- Randomized tests use a fixed seed and print it on failure so the case is reproducible.

---

## 9. Definition of Done (per feature)

```text
[ ] Business rules implemented server-side
[ ] Every BR id referenced by at least one test
[ ] Permission matrix cases pass (authorized / out-of-scope / unauthorized)
[ ] Offline behaviour verified (write works with network disabled)
[ ] Sync conflict path verified if the entity is syncable
[ ] Migrations run forward and backward
[ ] No N+1 on new list endpoints
[ ] Docs updated (business-rules, api, database as applicable)
[ ] PR reviewed and merged to develop
```
