# ADR-0005 — Op-log push, change-log pull, per-entity conflict policy

**Status:** Proposed · **Date:** 2026-10-28 · **Phase:** 0

## Context
§18 requires offline operation with a sync queue, retry and conflict handling. §19 forbids naive last-write-wins without analysis. Records are created in classrooms with intermittent connectivity, sometimes by two teachers for the same student.

## Decision

**Push** is an operation log: the client sends ordered operations, each with a device-generated `op_id` and a `base_version`. The server applies each operation independently and returns a per-operation result. `op_id` is a primary key in `sync_operations`, making retries idempotent.

**Pull** is a server-authoritative change log: a monotonic `bigserial` sequence per center, pulled by cursor, including tombstones. Client clocks never order anything.

**Conflicts** are resolved per entity, with a documented justification for each policy:

| Entity | Policy | Why |
|---|---|---|
| Records (memorization/review/recitation) | Keep both on create; field-merge; human on same-field | They are events, not state; merging would fabricate history |
| Attendance | Merge if identical, human if contradictory | It is state with a natural key; the system must not guess |
| Student | Field-merge; human on same field | Wrong assignment changes who sees the student |
| Approved evaluation | Server-only, client edit rejected | Derived, audited, authoritative |
| Template / profile | Immutable, new version only | Versioning is the conflict resolution |

Field-level merge is permitted only when changed-field sets are disjoint, computed against the `base_snapshot` stored with each queued update.

## Consequences
- No data loss on partial batch failure; one bad operation cannot roll back 39 good ones.
- Conflicts are visible and decidable rather than silently resolved.
- Requires the client to persist `base_version` + original values per queued op (storage cost is trivial).
- `change_log` must be pruned and a full-resync path must exist (OD-019).

## Alternatives rejected
- **Last-write-wins** — explicitly forbidden by §19; silently destroys a teacher's work.
- **Server-wins** — loses offline edits without telling anyone.
- **Full-document merge (CRDT)** — attractive for text, but grades and attendance need explicit human decisions, not automatic convergence.
- **Whole-batch transaction** — one invalid row would block a day's attendance.
