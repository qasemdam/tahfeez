# ADR-0007 — Server-authoritative derived values, no AI in business logic

**Status:** Proposed · **Date:** 2026-10-28 · **Phase:** 0

## Context
§21 forbids AI in any business decision (percentages, pages, scores, attendance, ranking). §20 forbids trusting the client. The client must nevertheless show instant feedback offline, which means it computes the same values locally.

## Decision
1. **The server recomputes every derived value** on write: page amounts, achievement percentages, weighted scores, week keys. Client-supplied derived values are ignored (never merged, never "corrected").
2. **The client computes for preview only.** Its values are displayed as provisional until the row is synced and confirmed.
3. **Both implementations share one fixture file.** Divergence fails CI on both sides.
4. **No AI, no ML, no probabilistic service** participates in grading, ranking, measurement or authorization. AI may later be added as a read-only assistant that drafts narrative text, clearly labelled, never writing to evaluation tables.

## Consequences
- A tampered client cannot inflate a grade.
- Slight duplication of logic (PHP + Dart) is accepted and bounded by fixtures.
- Offline UX stays instant; the user sees a "pending confirmation" state rather than a lie.
- Any future AI feature must go through an ADR and must not write to `weekly_evaluations`.

## Alternatives rejected
- **Client-authoritative** — trivially exploitable, violates §20.
- **Server-only computation with no local preview** — the form cannot show "1.2 pages" until online, which breaks the core offline workflow.
- **LLM-assisted grading** — explicitly forbidden by §21.
