# Tahfeez — Evaluation Architecture

Status: **Draft for review** · Phase 0
Implements §11, §12, §13, §14, §15 (configurable attendance effect), §21 (determinism).

---

## 1. Two layers, one record

The specification describes a **baseline** weekly rule (§11) and an **advanced** multi-criteria system (§12). They are not alternatives — the baseline is a permanent invariant that survives even when an advanced template is in use.

```text
weekly_evaluations
├── achievement_pct   ← ALWAYS computed, baseline §11, never null
├── actual_pages      ← measured from records
├── required_pages    ← from profile version (default 1.00)
└── weighted_score    ← advanced §12, nullable (null when no template)
    └── components[]  ← per-criterion breakdown, in `components` jsonb
```

Rationale: if a center later disables an advanced template, the historical baseline numbers are still there and comparable. If the baseline were derived on the fly from the template, it would vanish with it.

**The two values are never mixed (BR-EVL-15).**

| | Basic achievement | Advanced score |
|---|---|---|
| Column | `achievement_pct` | `weighted_score` |
| Range | **0 … unbounded** (250%, 300%) | **0 … 100**, normalised |
| Formula | `actual / required × 100` | `Σ (normalized_i × weight_i) / Σ weight_i` |
| Present when | always | an advanced template is bound to the profile |
| Meaning | how much was memorised vs. the requirement | weighted quality across configured criteria |

They are stored in separate columns, rendered in separate places, and never averaged, combined or substituted. A report that shows "85%" must state which of the two it is showing.

---

## 2. Baseline formula (frozen)

```text
achievement_pct = round_half_up(actual_pages / required_pages × 100, 2)
```

| actual | required | achievement |
|---|---|---|
| 0.5 | 1.0 | 50.00 |
| 1.0 | 1.0 | 100.00 |
| 1.5 | 1.0 | 150.00 |
| 2.0 | 1.0 | 200.00 |
| 2.5 | 1.0 | 250.00 |
| 3.0 | 1.0 | 300.00 |

These six rows are a **mandatory test fixture**. If they fail, nothing else matters.

Constraints:
- `required_pages > 0` — a zero or null requirement is a validation error, never a division guard that silently returns 0.
- Decimal arithmetic (`bcmath`, scale 6) — not floats. `2.5 / 1 * 100` must be exactly `250.00`, not `249.99999`.
- **Not capped (OD-008 resolved).** 300% is a valid achievement and is stored as `300.00`. A display cap is a presentation choice only; the stored value is never clamped, and `achievement_pct` is never written into `weighted_score`.

---

## 3. Advanced engine

### 3.1 Pipeline

```text
WeeklyEvaluationRequest
   ├── student, week_start, week_end, center timezone
   ├── profile_version  (immutable, effective at week start)
   ├── records          (memorizations, reviews, recitations, attendances in range)
        ↓
  ResolveSnapshot       → template_version + criteria + params + calculator versions
        ↓
  For each criterion:   Calculator->calculate(input) → CriterionResult
        ↓               { key, raw_value, normalized (0..100), weight, weighted, explanation }
  Aggregate             → Σ weighted / Σ weights_used × 100
        ↓
  Persist weekly_evaluations (components + rules_snapshot)
```

### 3.2 Criterion catalogue (closed set)

Only these five calculators exist in MVP. Adding one requires an ADR — the system must not invent mandatory criteria (§12).

| Key | Calculator | Raw input | Normalisation | Params |
|---|---|---|---|---|
| `memorization_pages` | `MemorizationPagesCalculator` | Σ `amount_pages` of memorizations in week | `raw / required × 100` (uncapped unless `cap` set) | `required_pages_source: profile\|fixed`, `cap` |
| `recitation_quality` | `RecitationQualityCalculator` | recitation scores from rule set | `mean` / `min` / `latest` (configurable) | `aggregate`, `missing` |
| `error_rate` | `ErrorRateCalculator` | Σ hifz/tajweed/reading errors | band table: `≤n → score` | `bands[]`, `weights_by_type` |
| `attendance` | `AttendanceCalculator` | counts by status | status → score map | `status_scores{}`, `count_absences_in_denominator` |
| `review_completion` | `ReviewCompletionCalculator` | Σ review `amount_pages` | `raw / target × 100` | `target_pages`, `cap` |

Every calculator:
- is a pure function of `(input, params)` — no DB access inside `calculate()`, inputs are pre-fetched by the caller;
- returns an `explanation` string/array stored in `components` (so a manager can see *why* a score is 87.5);
- declares its own `version` string, recorded in `rules_snapshot`.

### 3.3 Aggregation

```text
weighted_score = Σ (normalized_i × weight_i) / Σ weight_i
```

When all criteria are present, `Σ weight_i = 100` and the division is a no-op. When a criterion is excluded by the missing-data policy, weights are renormalised by the denominator — **but only if** the template's policy is `exclude_and_renormalize`. The policy is stored in the snapshot so the historical number stays explainable.

**Normalisation (BR-EVL-15).** `weighted_score` is clamped to `0…100` after aggregation — it is a percentage-of-quality scale, not a measure of volume. Individual criteria may produce raw values above 100 (memorization volume), but the aggregate is normalised. `achievement_pct` is a *different* number and is never clamped, never renormalised and never written into `weighted_score`.

### 3.4 Missing-data policy (per criterion, explicit)

| Policy | Behaviour | Use when |
|---|---|---|
| `treat_as_zero` | Criterion contributes 0 | Absence of work should hurt |
| `exclude_and_renormalize` | Criterion dropped, remaining weights rescaled | Data was not expected this week |
| `block` | Evaluation not generated; flagged `incomplete` | Grade would be misleading |

Default: `block` for `recitation_quality`, `treat_as_zero` for `memorization_pages`. **[OD-009]** — the default is a decision, not an assumption, and it is visible in the snapshot.

---

## 4. Rule sets for recitation (§14)

The recitation score must come from configuration, not from a formula in the source code.

### 4.1 Declarative rule document

```json
{
  "version": 1,
  "mode": "banded",
  "inputs": ["grade", "hifz_errors", "tajweed_errors", "reading_errors"],
  "bands": [
    { "id": "excellent",  "when": { "all": [ {"field":"hifz_errors","op":"lte","value":1},
                                            {"field":"tajweed_errors","op":"lte","value":2} ] },
      "score": 100 },
    { "id": "very_good",  "when": { "all": [ {"field":"hifz_errors","op":"lte","value":3} ] },
      "score": 85 },
    { "id": "good",       "when": { "all": [ {"field":"hifz_errors","op":"lte","value":6} ] },
      "score": 70 },
    { "id": "needs_review", "when": { "always": true }, "score": 50 }
  ]
}
```

### 4.2 Safety constraints

- **Whitelisted operators only**: `eq`, `in`, `lte`, `gte`, `between`, `always`. No arithmetic expressions, no string interpolation, no `eval`, no user-supplied code paths (§20, §21).
- **Total order required**: bands are evaluated top-down; the last band must be unconditional, so every input yields exactly one score. Validated on save.
- **Bounded complexity**: max 20 bands, max 10 conditions per band. Prevents pathological rule sets.
- **Immutable after publish**: editing creates a new `rule_sets` version; records keep `rule_set_id`.
- **Default supplied by the system, authored by the center**: the platform ships a default rule set, but the stored copy is center-owned and editable.

### 4.3 What is deliberately *not* configurable

- The baseline achievement formula (§11) — it is a product invariant, not a preference.
- The direction of the score (0 = worst, 100 = best).
- The rounding of stored values.

---

## 5. Versioning and snapshots (§13)

```text
Template ──< TemplateVersion ──< Criteria
                                   ▲
Profile ──< ProfileVersion ────────┘   (binds a template version + required_pages)
                │
                └──< WeeklyEvaluation (holds rules_snapshot + components)
```

Rules:

| Rule | Enforcement |
|---|---|
| Published template version is immutable | DB trigger + `PublishTemplateVersion` action |
| Profile change creates a new version with `effective_from` | No `UPDATE` path on profile versions exists |
| Week is evaluated with the profile version effective **at week start** | `ResolveProfileVersionForWeek` is the only resolver; tested across boundary dates |
| Past weeks are never recomputed with new rules | Recompute always re-reads the *stored* snapshot, not the current template |
| Approved evaluation is immutable | `status` transition guard; corrections create `revision + 1` with mandatory `revision_reason` |

**Worked boundary case.** Profile v1 effective 2026-01-01, v2 effective 2026-02-15 (a Sunday). Week of 2026-02-13…2026-02-19: the week *starts* on v1's side, so the whole week uses **v1**. Half-week switching is not supported and is explicitly rejected — a week is the atomic unit of grading.

### 5.1 Period boundaries

The first day of the week is **center configuration**, not a business constant (BR-TEN-04):

```text
centers.weekly_evaluation_start_day                    enum WeekStartDay { saturday … friday }
centers.weekly_evaluation_start_day_effective_from     date
```

- The current center is configured with `saturday`. That is a setting, not an assumption baked into code; another center may run `sunday` or `friday`.
- The value is represented by an enum in both codebases. No `if (day == 6)` and no `'Saturday'` literal may appear in evaluation logic.
- Each `weekly_evaluations` row stores `week_start`, `week_end` and `week_start_day` as **snapshot data** at the moment the period is opened.
- Changing the setting affects only periods opening on/after `…_effective_from`. It never recalculates, re-buckets or mutates an existing evaluation (BR-TEN-05).
- Recomputing a historical week reads the row's stored boundaries. It does not consult the center's current setting (BR-EVL-16).
- Aggregation uses `session_date BETWEEN week_start AND week_end`. The denormalised `week_key` on records is an index hint only and must never be grouped by.

**Overlap guard.** A setting change that would produce a period overlapping an already-computed period is rejected with a clear error rather than silently creating two overlapping weeks. The operator must choose an `effective_from` that lands after the last computed period.

**Calendar.** Stored dates are Gregorian (BR-TEN-06). Hijri dates are rendered alongside at display time — useful for parents and for matching paper registers — but never stored as a key and never used to compute a boundary.

---

## 6. Recompute policy

| Trigger | Behaviour |
|---|---|
| New record added to an **open** week | Recompute draft silently (idempotent) |
| Record edited/deleted in an **approved** week | Create a *recompute proposal*; requires `evaluation.recompute` permission |
| Template/profile changed | **No** retroactive effect on approved weeks |
| Manual recompute by manager | Allowed with reason; produces `revision + 1` |
| Recompute with identical inputs | Detected via checksum of `(inputs, snapshot)`; **no new revision** (BR-EVL-14) |

The checksum is stored in `components.meta.input_checksum`. This is what makes "recompute" safe to expose as a button.

---

## 7. Attendance effect (§15)

Attendance is **opt-in**. By default, `weekly_evaluations.achievement_pct` is unaffected by absences, and `weighted_score` is null unless a template explicitly includes the `attendance` criterion.

If included, the mapping is data:

```json
{ "present": 100, "late": 80, "excused_absence": 100, "unexcused_absence": 0,
  "count_absences_in_denominator": false }
```

`excused_absence = 100` by default is deliberate: an excused absence is not a quality signal. This default is visible and editable, not hidden in code.

---

## 8. Determinism checklist (§21)

The engine is rejected in review if any of these appear:

- [ ] `now()`, `today()`, `time()` inside a calculator
- [ ] `rand()`, `shuffle()`, `uuid()` inside a calculation
- [ ] float arithmetic on pages, weights or percentages
- [ ] ordering that depends on DB row order without an `ORDER BY`
- [ ] locale-dependent number formatting inside computation
- [ ] any network call, any LLM call, any AI inference
- [ ] a hard-coded formula that the spec says must be configurable

A property test asserts: same `(records, snapshot)` ⇒ byte-identical `components` JSON across 100 runs and across a shuffled input order.

---

## 9. Test fixtures (mandatory)

| Fixture | Expectation |
|---|---|
| `achievement_baseline` | the six rows of §2 |
| `overachiever_250` | actual 2.5, required 1 → 250.00, required unchanged |
| `exact_decimal` | 0.1 + 0.2 style inputs produce exact 2-decimal output |
| `weights_must_total_100` | publishing a 95% template throws |
| `weights_renormalise` | excluded criterion rescales correctly |
| `profile_boundary_week` | week starting on the boundary uses the old version |
| `no_retroactive_change` | publishing a new template does not alter approved weeks |
| `missing_recitation_block` | policy `block` yields `incomplete`, not a fake score |
| `idempotent_recompute` | second recompute with same inputs → same revision |
| `attendance_opt_in` | absence does not change `achievement_pct` |
| `two_scores_separate` | achievement 250.00 stored uncapped; `weighted_score` within 0–100; neither value derived from the other |
| `period_setting_change` | changing `weekly_evaluation_start_day` leaves every existing `week_start`/`week_end` untouched |
| `period_overlap_guard` | an `effective_from` that would overlap a computed period is rejected |

---

## 10. Evaluation engine (Phase 1 implementation)

Status: implemented in `App\Domain\Evaluation`; **no persistence**, no weekly
evaluation, no reports, no UI. The weekly-evaluation slice is the consumer.

### 10.1 Contract

```text
EvaluationEngine::evaluate(EvaluationInput): EvaluationResult
```

The engine is pure and deterministic:

- always computes `achievement_percentage` (uncapped) from
  `actual_pages / required_pages x 100`;
- computes `weighted_score` only when a rule set is supplied, otherwise null;
- never writes to the database, never reads the clock, never calls a network
  or an LLM, and never measures Quran amounts (the caller supplies
  `actual_pages`).

### 10.2 DTOs

| DTO | Fields |
|---|---|
| `EvaluationInput` | `required_pages`, `actual_pages`, `review_pages?`, `recitation?`, `attendance?`, `profile?`, `rule_set?` |
| `EvaluationResult` | `required_pages`, `actual_pages`, `achievement_percentage`, `weighted_score?`, `is_complete`, `components[]`, `blocked_criteria[]`, `profile_version?`, `profile_effective_from?`, `template_version_id?`, `rule_set_version?` |
| `RecitationInput` | pre-computed `scores` (0..100, ordered), `hifz_errors`, `tajweed_errors`, `reading_errors` |
| `AttendanceInput` | `present`, `late`, `excused_absence`, `unexcused_absence` |
| `EvaluationProfile` | `version`, `effective_from`, `required_pages`, `template_version_id?` |

The result keeps the two numbers separate (BR-EVL-15): achievement is
uncapped, the weighted score is 0..100 or null.

### 10.3 Rule set

```text
EvaluationRuleSet(version, criteria[])
EvaluationCriterion(key, weight, calculator, missing_data_policy, params)
```

- `key` is one of the closed catalogue in §3.2.
- `weight` is a decimal string, `> 0` and `<= 100`; the sum must be exactly
  `100.00` (BR-EVL-06), otherwise the engine throws.
- `missing_data_policy` is `treat_as_zero`, `exclude_and_renormalize` or
  `block` (BR-EVL-12).
- **No default weights are shipped.** The rule set must supply them; a missing
  or partial rule set is a configuration error, not a silent default.

Aggregation:

```text
weighted_i = normalized_i x weight_i / 100
weighted_score = Σ weighted_i / Σ included_weight_i x 100
```

`exclude_and_renormalize` drops a criterion and renormalises the denominator;
`treat_as_zero` keeps its weight with a zero contribution; `block` makes the
result incomplete (`weighted_score = null`, `is_complete = false`). The
aggregate is clamped to 0..100; the achievement is never clamped.

### 10.4 Calculators

| Key | Raw input | Normalisation | Params |
|---|---|---|---|
| `memorization_pages` | `actual_pages` | `raw / required x 100`, uncapped unless `cap` | `required_pages` optional (profile/input fallback), `cap` optional |
| `review_completion` | `review_pages` | `raw / target x 100` | `target_pages` required, `cap` optional |
| `recitation_quality` | pre-computed scores | `mean` / `min` / `latest` | `aggregate` required |
| `error_rate` | weighted error counts | band table `<= max → score` | `weights_by_type`, `bands` (sorted by max; a catch-all is required) |
| `attendance` | counts by status | weighted average of `status_scores` | `status_scores`, `count_absences_in_denominator` |

- **Recitation scores are pre-computed.** The banded recitation rule document
  (§4) is a separate rule-set engine; this engine aggregates scores and does
  not invent a grade-to-score formula.
- **Attendance is opt-in** (BR-ATT-04). The denominator is all records when
  `count_absences_in_denominator` is true, and records excluding unexcused
  absences when false. The default mapping in §7 is configuration, not a
  hidden constant.
- **Error bands** are sorted by `max` before matching, so the result does not
  depend on the rule set's array order.

### 10.5 Deferred

- Template/profile publishing (a profile version carries its rule set inline
  until the template tables ship — see §11.4).
- The banded recitation rule-set scorer (§4).
- Recompute/revision flow for approved weeks (BR-EVL-10) — see §11.5.
- Reports, dashboards, notifications and any UI beyond the weekly screen.

The engine's tests cover the baseline rows, no-normalisation, component
separation, missing-data policies, weight validation, profile version
carry-through, determinism and the two-scores separation.

---

## 11. Weekly evaluation (Phase 1 implementation)

The weekly slice is **orchestration only**: it resolves the period, gathers the
week's records, calls the engine once, freezes the inputs and the result, and
persists one row. It never re-runs the engine for an existing week.

### 11.1 Pipeline

```text
POST /weekly-evaluations { student_id, week_date }
  1. resolve the student inside the actor's center (404 outside)
  2. resolve the active group membership → historical group/teacher snapshot
  3. WeekPeriodResolver(date, centers.weekly_evaluation_start_day, timezone)
  4. idempotency: client_uuid → then (center, student, week_key)
  5. resolve the profile version effective at week_start
  6. aggregate memorization / review / recitation / attendance for the period
  7. EvaluationEngine::evaluate(EvaluationInput)
  8. persist scalars + rules_snapshot + result_snapshot in one transaction
```

The controller holds no evaluation logic; `CreateWeeklyEvaluation` owns the
orchestration.

### 11.2 Week resolver

`WeekPeriodResolver::resolve(DateTimeImmutable $reference, WeekStartDay $start)`
returns `week_start`, `week_end` and `week_key` (= `week_start`).

- Deterministic: the reference date is always passed in, never `now()` inside
  the resolver.
- The reference is built in the **center timezone**, so a request at 23:30 in
  the center's local day does not land in the neighbouring week.
- `week_key` is the canonical Gregorian start date of the week — a date, not a
  denormalised `2026-W43` string. It is the uniqueness key only; aggregation
  always uses `date BETWEEN week_start AND week_end` (BR-EVL-16).
- No weekday literal appears in the logic; `WeekStartDay` is an enum and the
  center setting is the only source.

### 11.3 Snapshot

Two JSON columns, both written once:

| Column | Content |
|---|---|
| `rules_snapshot` | profile version + `effective_from`, required pages, week boundaries, `weekly_evaluation_start_day`, center timezone, the full rule set JSON, recitation grade scores, and the aggregated inputs (memorization pages, review pages, recitation scores/errors, attendance counts) |
| `result_snapshot` | the engine's `EvaluationResult::toArray()`: achievement, weighted score, completeness, blocked criteria, profile version, and every component with raw/normalized/weighted values and explanation |

Reading a stored evaluation never consults the current profile, rule set or
center setting. Changing any of them afterwards leaves the row untouched.

### 11.4 Profile versions without templates

`evaluation_profile_versions` carries `required_pages`, `effective_from` and
the rule set inline. The template tables (`evaluation_templates`,
`evaluation_template_versions`, `evaluation_criteria`) remain a Phase 6
concern; `template_version_id` is therefore stored as a nullable integer with
no FK. This is recorded as OD-038.

### 11.5 Immutability and duplicates

- Unique `(center_id, student_id, week_key)` — one **live** row per
  student-week.
- Unique `(center_id, client_uuid)` — also live rows only.
- Both indexes are **partial** (`WHERE deleted_at IS NULL`) on PostgreSQL and
  SQLite. A soft-deleted week is removed, not a tombstone: the same week can be
  computed again, and the deleted row stays retrievable for audit. With a plain
  unique index the second insert would violate the constraint and surface as a
  500 — the partial predicate is what makes "delete and recompute" work. MySQL
  has no partial indexes, so the migration falls back to the plain unique there
  and a deleted week cannot be recreated (a known, documented limitation).
- A duplicate create returns the stored row with **200** instead of 201. It is
  never a recomputation.
- A `client_uuid` that already belongs to a **different student** is rejected
  with 422 rather than returning that student's row — a retry must never leak a
  record across the actor's scope.
- `PATCH` accepts exactly one transition, `status: approved`. Computed values
  and both snapshots are immutable; the request validates only `status`, so a
  body carrying `achievement_percentage`, `rules_snapshot` or a boundary date is
  ignored. The revision flow of BR-EVL-10 is deferred (OD-038): a correction
  today means deleting and recomputing a draft week.

### 11.6 Required pages fallback

`CreateWeeklyEvaluation` resolves `required_pages` once:

```php
$requiredPages = $profileVersion?->required_pages ?? '1.00';
```

This is the single fallback in the codebase, and it exists because a student may
have no profile version yet. It is the documented baseline of BR-EVL-01, not a
hidden constant: when a profile version exists its `required_pages` always wins,
and the resolved value is copied into `rules_snapshot` so the historical row
explains itself. Swapping the source later (a center-level default, or a
template-derived requirement) means changing this one line plus the profile
resolver — the weekly row, the snapshot and the API do not change shape.

### 11.7 Flutter

`/students/:id/weekly-evaluation` shows the stored weeks of one student and
computes a new one. The client sends `week_date` (any day in the week) and
renders the boundaries the server returns; it never derives a week itself and
never hard-codes a start day. Achievement is displayed uncapped, and the
weighted score shows `غير محسوبة` when a criterion blocked the computation.

