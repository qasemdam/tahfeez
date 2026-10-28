# ADR-0004 — Versioned declarative rule engine for evaluation

**Status:** Proposed · **Date:** 2026-10-28 · **Phase:** 0

## Context
§12 requires weighted multi-criteria evaluation with configurable weights. §14 requires recitation scoring through a configurable rule engine rather than a hard-coded formula. §13 requires an immutable profile per student and a snapshot per weekly evaluation. §21 forbids AI or non-deterministic logic.

## Decision
Two artefacts, both data:

1. **Criteria** on an immutable `evaluation_template_version`: `{key, calculator, weight, params}`. Calculators are a **closed set of five** registered classes; `params` carries thresholds, caps and missing-data policy.
2. **Rule sets** for recitation scoring: a declarative JSON document with whitelisted operators (`eq, in, lte, gte, between, always`) evaluated top-down over ordered bands. No expressions, no `eval`, no user code.

Every `weekly_evaluation` row stores `components` (per-criterion raw, normalised, weighted, explanation) and `rules_snapshot` (template version, weights, calculator versions, params, input checksum).

## Consequences
- Historical grades remain explainable after templates change or are archived.
- Administrators can change policy without a deployment; developers cannot accidentally change grading by editing a formula.
- The closed calculator set is a deliberate limit: a new criterion needs an ADR, which is exactly the §12 constraint.
- Validation burden shifts to save/publish time (weights = 100, band totality, bounded complexity).

## Alternatives rejected
- **Formula strings evaluated at runtime** — code injection surface, untestable, non-deterministic in practice.
- **Hard-coded weights in PHP** — violates §12 and §14 directly.
- **JSONLogic library** — expressive, but its operator surface is larger than needed and harder to bound; a whitelisted subset is safer.
- **Storing only the final score** — destroys auditability (§13).
