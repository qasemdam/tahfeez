# Tahfeez — Offline-First & Sync Design

Status: **Draft for review** · Phase 0
Implements §18, §19, BR-SYN-01…08.

---

## 1. Operating assumption

A muhaffiz records 20 students in a room with no signal, closes the app, and goes home. Nothing may be lost, nothing may be duplicated, and nothing may be silently overwritten. Every design choice below follows from that sentence.

---

## 2. Local data model (client)

The client mirrors the server schema, with an added sync envelope on every syncable table:

| Column | Purpose |
|---|---|
| `local_id` | integer PK, local only |
| `server_id` | bigint null — null until first successful push |
| `client_uuid` | uuid — generated on device, stable for the record's life |
| `version` | integer — last known server version |
| `sync_state` | `synced` \| `pending` \| `conflict` \| `failed` |
| `server_updated_at` | timestamptz null |
| `deleted_at` | timestamptz null (tombstone) |

Read-only mirrors (no envelope): `quran_*`, reference lookups.

### Outbox

```text
sync_operations
  op_id        uuid PK        (idempotency key, sent to server)
  entity_type  text
  client_uuid  uuid
  operation    'create' | 'update' | 'delete'
  payload      jsonb          (full row snapshot, not a diff)
  base_version integer null   (null for create)
  depends_on   uuid null      (parent op, for offline-created relations)
  attempts     integer
  next_retry_at timestamptz
  status       'queued' | 'inflight' | 'done' | 'conflict' | 'failed'
  last_error   jsonb null
  created_at   timestamptz
```

Rules:
- Writes go to the local table **and** append to the outbox **in one SQLite transaction**. There is no window where a record exists locally but is not queued.
- The UI never blocks on the network. Sync is a background concern with visible status.
- Full-row payloads (not diffs) keep replay simple and make conflict detection field-accurate.

---

## 3. Push protocol

```http
POST /api/v1/sync/push
Idempotency-Key: <batch uuid>
{
  "device_id": "…",
  "client_dataset_version": "1.0.0",
  "operations": [
    {
      "op_id": "0192f0c4-…",
      "entity": "memorization",
      "operation": "create",
      "client_uuid": "0192f0c4-…",
      "base_version": null,
      "payload": { "student_id": 41, "session_date": "2026-10-26", "from_ayah_id": 8, "to_ayah_id": 20, "amount_source": "CALCULATED", "grade": "good" }
    },
    {
      "op_id": "0192f0c5-…",
      "entity": "attendance",
      "operation": "update",
      "client_uuid": "0192f0a1-…",
      "base_version": 3,
      "payload": { "status": "late", "late_minutes": 10 }
    }
  ]
}
```

Response is **per-operation**, never all-or-nothing:

```json
{
  "results": [
    { "op_id": "0192f0c4-…", "status": "applied", "server_id": 912, "version": 1,
      "server_row": { "amount_pages": "1.2000", "amount_lines": 18 } },
    { "op_id": "0192f0c5-…", "status": "conflict",
      "conflict": { "type": "version_mismatch", "server_version": 5,
                    "server_row": { "status": "present" },
                    "client_base": 3,
                    "fields_in_conflict": ["status"] } }
  ]
}
```

Statuses: `applied` · `duplicate` (already applied, returns original result) · `conflict` · `rejected` (validation, with field errors) · `locked` (record is in an approved/locked week).

**Why per-operation:** a batch of 40 attendance records must not fail because one student was reassigned by the manager. One rejection is surfaced; the other 39 land.

### Idempotency

`op_id` is a UUIDv7 generated on the device. `sync_operations.op_id` is a primary key on the server, written in the same transaction as the mutation. A retried operation returns `duplicate` with the original result — never a second record.

This is what makes "retry on timeout" safe: the client cannot know whether the request that timed out actually succeeded.

---

## 4. Pull protocol

```http
GET /api/v1/sync/pull?cursor=10482&limit=500
```

```json
{
  "changes": [
    { "seq": 10483, "entity": "student", "id": 41, "operation": "update",
      "version": 7, "payload": { … }, "changed_at": "2026-10-28T19:02:11Z" },
    { "seq": 10484, "entity": "memorization", "id": 900, "operation": "delete",
      "version": 3, "payload": null }
  ],
  "next_cursor": 10484,
  "has_more": false,
  "server_dataset_version": "1.0.0"
}
```

- The cursor is the server `change_log.seq`, monotonic per center. Client clocks are never used for ordering (BR-SYN-03).
- Deletes arrive as tombstones; the client soft-deletes locally so the user sees the removal rather than a resurrected row on next edit.
- Pull is applied in `seq` order inside one transaction; the cursor advances only after commit.
- If `cursor` is older than the retention window, the server responds `410 gone` with `{ "full_resync_required": true }`.

---

## 5. Conflict resolution (§19)

Blanket last-write-wins is forbidden. The policy is **per entity**, and each policy has a stated justification.

| Entity | Concurrent create | Concurrent update (different fields) | Concurrent update (same field) | Delete vs edit |
|---|---|---|---|---|
| `memorization` / `review` / `recitation` | Two records, both kept (both are real events) | Field-level merge, auto-applied | **Conflict** → teacher resolves | Edit rejected, tombstone wins, message shown |
| `attendance` | Unique `(student, date)` → **merge if identical**, else **conflict** | Field-level merge | **Conflict** → manager resolves | Edit rejected |
| `student` | Duplicate `client_uuid` impossible; same person twice → manual merge later | Field-level merge | **Conflict** → manager resolves | Soft delete wins, edit rejected |
| `evaluation` (approved) | n/a | **Server-only**, client edit → `409` | n/a | Server-only |
| `template` / `profile` | n/a | **Immutable**, new version required | n/a | n/a |

### Why these choices

- **Records are events, not state.** If two teachers both recorded a memorization session for the same student, both sessions probably happened (or one is a duplicate the manager can delete). Dropping one silently destroys real work; merging them would fabricate an event. Keep both, flag for review.
- **Attendance is state, not an event.** One student, one day, one status. Two devices disagreeing is a genuine contradiction — the system must not guess. Identical values merge silently because there is nothing to decide.
- **Student profiles are state with high blast radius.** A wrong phone number is cheap; a wrong teacher assignment changes who can see the student. Conflicts go to a human.
- **Approved evaluations are derived, authoritative and audited.** A client must never be able to push a grade.
- **Delete vs edit:** the delete is intentional and already committed; re-applying an edit would resurrect a row the author deliberately removed. The editor is told, and can re-create if the deletion was wrong.

### Field-level merge rules

Auto-merge is allowed only when the changed-field sets are **disjoint**. The server computes:

```text
changed_server = fields where server_row ≠ base_snapshot
changed_client = fields where payload ≠ base_snapshot
if changed_server ∩ changed_client = ∅  → merge, bump version, return applied
else                                    → conflict, return both rows + intersecting fields
```

`base_snapshot` is stored client-side with each queued update. Without it, "disjoint" cannot be determined — this is why the outbox keeps `base_version` *and* the payload's original values.

### Conflict UX

Conflicts land in a `sync_conflicts` table and are surfaced in a dedicated screen with three actions: **keep mine**, **keep theirs**, **merge manually** (field picker). Nothing is discarded until the user decides. A conflict on a locked record is escalated to the manager.

---

## 6. Dependency ordering

Offline creation of a student followed by a memorization record for that student must not fail because the student has no `server_id` yet.

- The payload references the parent by `client_uuid`, and the operation carries `depends_on: <parent op_id>`.
- The server resolves `client_uuid → server_id` from `sync_operations` results before applying the child; if the parent is not yet applied, the child is queued server-side for retry (bounded, then `rejected` with a clear error).
- Client sends operations in dependency order; the server does not assume it.

---

## 7. Retry, backoff, and failure taxonomy

| Server response | Client action |
|---|---|
| `applied` / `duplicate` | Mark done, clear outbox row |
| `conflict` | Move to conflicts, stop retrying |
| `rejected` (422) | Mark failed, show field errors, keep local row for correction |
| `locked` (423) | Mark failed with explanation; no retry |
| `401` | Refresh token once, then require re-login (queued ops survive) |
| `429` | Honour `Retry-After` |
| `5xx` / network | Exponential backoff with jitter: 2s, 8s, 30s, 2m, 10m, capped at 30m |

Guarantees:
- Queued operations survive app restart, logout and crash (persisted in SQLite, not memory).
- Logout with pending work warns the user and offers "sync now".
- A permanently failing operation is visible in a "needs attention" badge — never silently dropped.

---

## 8. Clock skew

- Server timestamps are authoritative for `updated_at`, `version` and `change_log.seq`.
- `session_date` is business data typed by the teacher; a future date beyond `+1 day` (center timezone) is rejected as a validation error, not a conflict.
- Week boundaries are computed **server-side** from `session_date` and center config; the client's week key is a hint that the server overwrites.

---

## 9. What is *not* offline-capable (MVP)

| Action | Offline behaviour |
|---|---|
| Login / token refresh | Requires network (session persists offline after first login) |
| Manage users & permissions | Read-only cache; mutations require connectivity |
| Publish evaluation templates | Requires connectivity |
| Approve weekly evaluations | Requires connectivity |
| Generate PDF/Excel exports | Requires connectivity (queued server-side) |
| Record memorization / review / recitation / attendance | **Fully offline** |

This boundary is a decision, not an oversight: authorization changes and grade approvals must be server-observed at the moment they happen.

---

## 10. Local storage & security

- SQLite database encrypted at rest (SQLCipher or platform keychain-wrapped key) **[OD-012]**.
- Tokens in `flutter_secure_storage` (Keychain / Keystore), never in the SQLite DB or logs.
- Cached data is scoped to the logged-in user and wiped on logout (except unsynced outbox, which is preserved behind a confirmation).
- Web build: local persistence via IndexedDB/WASM SQLite; the same "requires connectivity" table above applies, and multi-tab writes are serialised by a lock.

---

## 11. Testing the sync engine

| Test | Method |
|---|---|
| Idempotency | Same `op_id` sent 3× → one row, three `duplicate` results |
| Offline create → sync | Disable network in integration test, create 20 records, restore, assert 20 rows server-side |
| Two-device conflict | Simulate two clients editing the same attendance row → assert `conflict`, both states preserved |
| Field-level merge | Device A edits `notes`, device B edits `status` → auto-merged, version bumped |
| Pull ordering | Changes applied in `seq` order; cursor never regresses |
| Retention window | Cursor older than retention → `410` + `full_resync_required` |
| Partial batch failure | 40 ops, 1 invalid → 39 applied, 1 `rejected`, batch not rolled back |
| Crash recovery | Kill app mid-push → on restart, no duplicate records |

The backend suite includes a **fake-device harness** that replays operation logs, so conflict behaviour is tested without a real phone.
