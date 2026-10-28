# ADR-0002 — Shared-schema multi-tenancy with `center_id`

**Status:** Proposed · **Date:** 2026-10-28 · **Phase:** 0

## Context
§7 requires `Region → Center → Users → Students` to be extensible, with region management out of MVP. §20 requires tenant isolation. §25 requires scaling to many centers.

## Decision
Single database, shared schema. Every tenant table carries `center_id bigint NOT NULL`. Isolation is structural:
1. `center_id` is derived from the authenticated user, never from the request.
2. A global scope applies it to every query.
3. Policies check it per record; out-of-scope access returns `404`.
4. Every composite index leads with `center_id`.
5. Every unique constraint includes `center_id`.

## Consequences
- Cheap onboarding, one migration path, simple reporting across a center.
- A missing scope is a silent breach, so it is defended in depth (scope + policy + action re-check + tests that assert 404).
- Noisy-neighbour risk is handled by indexes and pagination, not by physical separation.
- Adding `Region` later = new table + nullable `centers.region_id` + a scope. No table reshaping.

## Alternatives rejected
- **Schema-per-tenant** — migration fan-out, connection management, cross-center analytics pain; overkill for MVP.
- **Database-per-tenant** — same, plus backup complexity.
- **Row-level security in Postgres** — strong, but duplicates the authorization model already expressed in policies and makes local debugging harder. Revisit if a contractual isolation requirement appears.
