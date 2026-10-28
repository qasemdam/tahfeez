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
- Uncapped by default: 300% is a valid achievement, not clamped to 100. Clamping is a *per-criterion* display/evaluation option **[OD-008]**, never a global rule.

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
