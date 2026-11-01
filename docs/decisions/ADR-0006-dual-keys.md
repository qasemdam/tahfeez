# ADR-0006 — Dual keys: server `bigint` + client `uuid`

**Status:** Proposed · **Date:** 2026-10-28 · **Phase:** 0

## Context
Records are created offline, before the server can assign an identifier. Two devices may create different records at the same moment. References between offline-created rows (student → memorization) must survive until the server assigns real ids.

## Decision
Every syncable table has:
- `id bigint` — server identity, primary key, FK target, indexed.
- `client_uuid uuid NULL` — generated on the device (UUIDv7), unique per center.

The client uses `client_uuid` for local references. The server resolves `client_uuid → id` when applying dependent operations. `client_uuid` is immutable once set.

## Consequences
- Offline creation and dependent writes work without temporary negative ids or id remapping.
- Idempotency has a natural key: `unique (center_id, client_uuid)`.
- UUIDv7 is time-ordered, so index locality is acceptable (unlike UUIDv4).
- The client must never reuse a `client_uuid` across records; a factory test enforces uniqueness.
- Public APIs expose `id` (server) and `client_uuid` (sync) — clients must not treat `client_uuid` as a primary key for reads after sync.

## Alternatives rejected
- **UUID primary keys everywhere** — 16-byte FKs across every table and index; unnecessary for a single-database deployment.
- **Negative temporary ids** — collision-prone, leaks into reports, breaks on merge.
- **Server-assigned ids only** — impossible offline.
