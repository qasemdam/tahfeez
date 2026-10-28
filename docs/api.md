# Tahfeez — API Design

Status: **Draft for review** · Phase 0
Base URL: `/api/v1` · Content type: `application/json` · Auth: Bearer (Sanctum)

---

## 1. Conventions

| Concern | Rule |
|---|---|
| Versioning | URL prefix `/api/v1`. Breaking changes → `/v2`; additive changes stay in `v1`. |
| Resources | Plural nouns, kebab-case paths, no verbs in paths except explicit commands (`/publish`, `/recompute`). |
| Envelope | Single resource: `{ "data": {…} }`. Collection: `{ "data": [...], "meta": {…}, "links": {…} }`. |
| Errors | RFC 7807 `application/problem+json`. |
| Pagination | Cursor-based: `?cursor=…&limit=…` (default 25, max 100). Offset pagination is not offered on record tables. |
| Filtering | Explicit whitelisted params per endpoint, e.g. `?status=active&teacher_id=7&from=2026-01-01`. Unknown filters → `422`, not ignored. |
| Sorting | `?sort=-session_date,id`. Default sort is always deterministic (tie-break by `id`). |
| Concurrency | `PATCH` accepts `If-Match: <version>`; mismatch → `409` with the current row. |
| Idempotency | All sync mutations and all `POST` that create records accept `Idempotency-Key`. |
| Dates | Business dates: `YYYY-MM-DD`. Timestamps: ISO-8601 UTC (`…Z`). |
| Decimals | Serialised as **strings** (`"2.5000"`) to avoid float precision loss in clients. |
| Localisation | `Accept-Language: ar|en` selects error messages and labels; numbers stay Latin digits. |
| Rate limits | 60 req/min per user, 10/min on `/auth/login`. `429` + `Retry-After`. |

### Error shape

```json
{
  "type": "https://tahfeez.app/errors/validation",
  "title": "Validation failed",
  "status": 422,
  "detail": "The to ayah must be greater than or equal to the from ayah.",
  "instance": "/api/v1/memorizations",
  "errors": { "to_ayah_id": ["The to ayah must be greater than or equal to the from ayah."] },
  "request_id": "0192f0c4-8c1e-7c3a-9b2e-1f6a2d4c8e11"
}
```

Status codes used: `200` `201` `204` `400` `401` `403` `404` `409` `410` `422` `423` `429` `500`.

---

## 2. Authentication

| Method | Path | Purpose |
|---|---|---|
| POST | `/auth/login` | Email + password + device info → token |
| POST | `/auth/refresh` | Rotate token |
| POST | `/auth/logout` | Revoke current device token |
| GET | `/auth/me` | User, center, role, permission list |
| GET | `/auth/devices` | List devices |
| DELETE | `/auth/devices/{id}` | Revoke a device |

Token payload never contains authorization decisions — only identity and device. Permissions are resolved per request so a revoked permission takes effect immediately (BR-AUT-04).

---

## 3. Reference data

| Method | Path | Notes |
|---|---|---|
| GET | `/quran/meta` | Dataset version, checksum, counts |
| GET | `/quran/surahs` | 114 rows, cacheable, `ETag` |
| GET | `/quran/surahs/{id}/ayahs` | Ayah list with page/line coordinates |
| GET | `/quran/pages/{n}` | Page metadata (lines, juz, hizb) |
| POST | `/quran/measure` | `{from_ayah_id, to_ayah_id}` → `{amount_lines, amount_pages, from_page, to_page}` |

`/quran/measure` is a **read-only computation endpoint**: it exists so the client can verify its local algorithm against the server. It writes nothing.

---

## 4. Students & teachers

| Method | Path | Permission |
|---|---|---|
| GET | `/students` | `students.view` (scoped) |
| POST | `/students` | `students.create` |
| GET | `/students/{id}` | `students.view` |
| PATCH | `/students/{id}` | `students.update` |
| DELETE | `/students/{id}` | `students.delete` |
| POST | `/students/{id}/assign-teacher` | `students.assign` |
| GET | `/students/{id}/timeline` | `students.view` — merged record feed |
| GET | `/students/{id}/progress` | `reports.view` |
| GET | `/teachers` | `users.view` |
| POST | `/teachers` | `users.create` |
| PATCH | `/teachers/{id}` | `users.update` |
| PUT | `/teachers/{id}/permissions` | `users.permissions` |
| GET | `/teachers/{id}/performance` | `reports.view` |

`GET /students` for a `muhaffiz` is implicitly filtered to active assignments — the scope is applied in the query, not in the controller (BR-AUT-02).

---

## 5. Records

| Method | Path | Permission |
|---|---|---|
| GET/POST | `/memorizations` | `memorization.view` / `memorization.record` |
| GET/PATCH/DELETE | `/memorizations/{id}` | `…view` / `…update` / `…delete` |
| GET/POST | `/reviews` | `review.view` / `review.record` |
| GET/PATCH/DELETE | `/reviews/{id}` | `…view` / `…update` / `…delete` |
| GET/POST | `/recitations` | `recitation.view` / `recitation.record` |
| GET/PATCH/DELETE | `/recitations/{id}` | `…view` / `…update` / `…delete` |
| GET | `/attendance` | `attendance.view` |
| POST | `/attendance/bulk` | `attendance.record` — idempotent day upsert |
| PATCH | `/attendance/{id}` | `attendance.update` |

### Create memorization

```http
POST /api/v1/memorizations
Idempotency-Key: 0192f0c4-…
```
```json
{
  "student_id": 41,
  "session_date": "2026-10-26",
  "from_ayah_id": 8,
  "to_ayah_id": 20,
  "amount_source": "CALCULATED",
  "grade": "very_good",
  "hifz_errors": 1,
  "tajweed_errors": 2,
  "notes": "…"
}
```

Response `201`:

```json
{
  "data": {
    "id": 912, "client_uuid": "0192f0c4-…", "version": 1,
    "student": { "id": 41, "full_name": "…" },
    "surah": { "id": 2, "name_ar": "البقرة" },
    "from_ayah_id": 8, "to_ayah_id": 20,
    "from_page": 3, "to_page": 4,
    "amount_lines": 18, "amount_pages": "1.2000", "amount_source": "CALCULATED",
    "grade": "very_good", "session_date": "2026-10-26",
    "created_at": "2026-10-26T17:04:11Z"
  }
}
```

Note: the server **recomputes** `amount_pages` for `CALCULATED` records; a client-supplied value is ignored, never trusted (§20).

For `amount_source: "MANUAL"`, the client sends `amount_pages` and may omit the ayah range; the server validates `0 < amount_pages ≤ 604` and records `amount_source = MANUAL` permanently.

---

## 6. Evaluation

| Method | Path | Permission |
|---|---|---|
| GET/POST | `/evaluation-templates` | `evaluation.template.view` / `.create` |
| GET | `/evaluation-templates/{id}` | `.view` |
| POST | `/evaluation-templates/{id}/versions` | `.create` |
| PATCH | `/evaluation-template-versions/{id}` | `.update` — draft only |
| POST | `/evaluation-template-versions/{id}/publish` | `.publish` |
| POST | `/evaluation-template-versions/{id}/archive` | `.publish` |
| GET | `/students/{id}/evaluation-profile` | `evaluation.profile.view` |
| POST | `/students/{id}/evaluation-profile/versions` | `evaluation.profile.change` |
| GET | `/weekly-evaluations` | `evaluation.view` (`?week=2026-W43` or `?from=&to=`) |
| GET | `/weekly-evaluations/{id}` | `evaluation.view` |
| POST | `/weekly-evaluations/compute` | `evaluation.compute` — batch for a week |
| POST | `/weekly-evaluations/{id}/approve` | `evaluation.approve` |
| POST | `/weekly-evaluations/{id}/recompute` | `evaluation.recompute` — requires `reason` |

Publishing an invalid template (weights ≠ 100) returns `422` with `errors.weights`. Approving a week with unresolved conflicts returns `409`.

---

## 7. Reports & exports

| Method | Path | Notes |
|---|---|---|
| GET | `/dashboard` | Center KPIs for a period |
| GET | `/reports/weekly` | `?week=` |
| GET | `/reports/monthly` | `?month=YYYY-MM` |
| GET | `/reports/students/{id}` | Student report |
| GET | `/reports/attendance` | `?from=&to=&student_id=` |
| GET | `/reports/memorization` | `?from=&to=` |
| GET | `/reports/review` | |
| GET | `/reports/recitation` | |
| GET | `/reports/rankings` | `?week=&scope=` |
| POST | `/exports` | `{type, format, params}` → `202` + job id |
| GET | `/exports/{id}` | Status + signed download URL |

Exports are asynchronous, scoped to the requester's permissions, and expire (BR-RPT-03).

---

## 8. Sync

| Method | Path | Notes |
|---|---|---|
| POST | `/sync/push` | Batch of operations, per-op results |
| GET | `/sync/pull` | `?cursor=&limit=` |
| GET | `/sync/state` | Server dataset version, change-log head, retention floor |

Detailed contract: `offline-sync.md`.

---

## 9. Administration

| Method | Path | Permission |
|---|---|---|
| GET/PATCH | `/settings` | `settings.view` / `settings.update` |
| GET | `/audit-logs` | `audit.view` |
| GET | `/roles` | `users.permissions` |
| PUT | `/roles/{id}/permissions` | `users.permissions` |

---

## 10. Cross-cutting implementation rules

1. **Every list endpoint declares its index.** If a filter has no supporting index, the endpoint is rejected in review (see `database.md` §10).
2. **Resources are explicit.** No `$model->toArray()`; each field is listed, so a new DB column never leaks automatically.
3. **N+1 is a bug.** `whenLoaded` + a query-count assertion in feature tests on `/students`, `/memorizations`, `/weekly-evaluations`.
4. **Server recomputes every derived value** (amounts, percentages, scores). Client values are hints for UX only.
5. **Authorization is a policy call per record**, not a middleware role check alone.
6. **OpenAPI spec is generated** from routes + annotations in Phase 1 and committed to `docs/api/openapi.yaml`; drift fails CI.
