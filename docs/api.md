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

`POST /auth/login` is the only unauthenticated write path, so it is rate-limited:
5 attempts per minute per (email + address) and 30 per minute per address, then
`429`. The limit is keyed by the credential pair, so many users behind one NAT
are unaffected. Tokens are stored as SHA-256 hashes only; logout deletes the
device's token row, and a revoked or expired token is `401`.

---

## 3. Reference data

| Method | Path | Notes |
|---|---|---|
| GET | `/quran/meta` | Active dataset: `dataset_version_id`, source, source_version, imported_at, checksum, validation_status, surah/ayah/page counts |
| GET | `/quran/surahs` | 114 rows, cacheable, `ETag` |
| GET | `/quran/surahs/{id}/ayahs` | Ayah list with page/line coordinates |
| GET | `/quran/pages/{n}` | Page metadata (`lines_count`, juz where available) |
| POST | `/quran/measure` | `{from_ayah_id, to_ayah_id}` → `{amount_lines, amount_pages, from_page, to_page, dataset_version_id}` |

`/quran/measure` is a **read-only computation endpoint**: it exists so the client can verify its local algorithm against the server. It writes nothing. `dataset_version_id` is returned so the caller knows exactly which snapshot produced the number.

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
| GET | `/dashboard` | `?month=YYYY-MM`, plus `group_id` — implemented, see §7.3 |
| GET | `/reports/weekly` | `?week_key=` or `?week_start_date=`, plus `group_id` / `student_id` — implemented, see §7.1 |
| GET | `/reports/monthly` | `?month=YYYY-MM`, plus `group_id` / `student_id` — implemented, see §7.2 |
| GET | `/dashboard/export` | same filters + `format` — implemented, see §7.4 |
| GET | `/reports/weekly/export` | same filters + `format` — implemented, see §7.4 |
| GET | `/reports/monthly/export` | same filters + `format` — implemented, see §7.4 |
| GET | `/reports/students/{id}` | Student report |
| GET | `/reports/attendance` | `?from=&to=&student_id=` |
| GET | `/reports/memorization` | `?from=&to=` |
| GET | `/reports/review` | |
| GET | `/reports/recitation` | |
| GET | `/reports/rankings` | `?week=&scope=` |
| POST | `/exports` | `{type, format, params}` → `202` + job id — not implemented |
| GET | `/exports/{id}` | Status + signed download URL — not implemented |

The three `/export` endpoints are synchronous and return the file itself; they
cover a single week or month, which is a bounded row count. The asynchronous
job endpoints (`POST /exports`, `GET /exports/{id}`) that BR-RPT-03 reserves for
large ranges are **not** implemented in this phase — see §7.4 for the limits.

### 7.1 Weekly report

`GET /reports/weekly` · permission `reports.view` · read-only.

| Parameter | Type | Notes |
|---|---|---|
| `week_key` | `Y-m-d` | a date inside the week; the server resolves the boundaries from the center's start day and timezone |
| `week_start_date` | `Y-m-d` | used **verbatim** as the week start (end = start + 6 days). This is what a historical report needs once the center's start day has changed |
| `group_id` | int | optional; out-of-scope ids simply return no rows |
| `student_id` | int | optional |

Exactly one of `week_key` / `week_start_date` is required: neither is `422`
with `errors.week_key`, and sending both is `422` with
`errors.week_start_date` — no silent precedence.

The response is one row per student with an active membership in the actor's
scope. Scope is structural: a manager sees the center, a teacher sees only their
own groups, and a cross-center filter yields an empty list.

```json
{
  "data": {
    "week": {
      "week_key": "2026-10-24",
      "start_date": "2026-10-24",
      "end_date": "2026-10-30",
      "start_day": "saturday"
    },
    "students": [
      {
        "student": { "id": 1, "name": "..." },
        "group": { "id": 2, "name": "..." },
        "teacher": { "id": 3, "name": "..." },
        "attendance": { "present": 4, "late": 1, "excused_absence": 1, "unexcused_absence": 0 },
        "memorization": {
          "required_pages": "1.00",
          "actual_pages": "2.0000",
          "achievement_percentage": "200.00",
          "sessions": 3
        },
        "review": { "pages": "0.5000", "sessions": 2 },
        "recitation": {
          "sessions": 1,
          "grades": { "good": 1 },
          "errors": { "hifz": 2, "tajweed": 0, "reading": 1 }
        },
        "evaluation": {
          "id": 9,
          "weighted_score": "87.50",
          "achievement_percentage": "200.00",
          "is_complete": true,
          "status": "computed"
        }
      }
    ],
    "totals": { "students": 1 }
  }
}
```

Notes:

- `week.start_day` always describes the boundary that was returned. With
  `week_start_date` it is the weekday that date actually falls on, not the
  center's current setting — a historical week keeps saying what it was.
- `group` / `teacher` describe the week being reported: when a stored
  evaluation exists, they are the group and teacher that evaluation froze, even
  if the student has since moved. Without an evaluation they are the student's
  current group.
- `memorization.achievement_percentage` is **uncapped** and computed from the
  week's records; it is never normalised against the top student.
- `review` is a separate figure, never folded into memorization (BR-EVL-15).
- `recitation` reports the stored sessions, grade counts and error totals. No
  score is derived from a grade — that mapping is rule-set data.
- `evaluation` is the **stored** weekly evaluation (`null` when none exists).
  The report never computes one and never reinterprets an old one with the
  current profile.
- No attendance rate is returned: no definition of it exists in the business
  rules, so the counts are reported as-is.


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

### 7.2 Monthly report

`GET /reports/monthly` · permission `reports.view` · read-only.

| Parameter | Type | Notes |
|---|---|---|
| `month` | `YYYY-MM` | **required**. A calendar month, always the 1st to the last day. It is never derived from `weekly_evaluation_start_day`, never defaulted to the current month, and its boundaries never depend on today's date |
| `group_id` | int | optional; out-of-scope ids simply return no rows |
| `student_id` | int | optional |

A missing or malformed `month` (`2026-1`, `2026-13`, `invalid`) is `422` with
`errors.month`.

```json
{
  "data": {
    "month": {
      "month_key": "2026-10",
      "start_date": "2026-10-01",
      "end_date": "2026-10-31"
    },
    "students": [
      {
        "student": { "id": 1, "name": "..." },
        "group": { "id": 2, "name": "..." },
        "teacher": { "id": 3, "name": "..." },
        "attendance": { "present": 15, "late": 2, "excused_absence": 1, "unexcused_absence": 0 },
        "memorization": {
          "required_pages": "4.00",
          "actual_pages": "6.0000",
          "achievement_percentage": "150.00",
          "sessions": 8
        },
        "review": { "pages": "3.5000", "sessions": 6 },
        "recitation": {
          "sessions": 5,
          "grades": { "good": 4, "excellent": 1 },
          "errors": { "hifz": 3, "tajweed": 1, "reading": 2 }
        },
        "weekly_evaluations": { "count": 4, "completed": 4, "approved": 3, "computed": 1 }
      }
    ],
    "totals": { "students": 1 }
  }
}
```

Notes:

- **`required_pages` is the sum of the requirements frozen in the stored weekly
  evaluations of the month.** A week without an evaluation contributes nothing;
  it is never replaced by `weeks × default` or by the current profile.
- **`achievement_percentage` is null when that sum is zero** — the ratio is
  undefined, and an undefined ratio is not reported as `0%`. Otherwise it is
  uncapped (`6.0000` over `4.00` → `150.00`).
- `weekly_evaluations` counts the stored rows whose `week_start_date` falls
  inside the month. `computed` is never promoted to `approved`.
- `group` / `teacher` come from the latest stored evaluation of the month (the
  group that graded it), falling back to the student's current membership.
  Rows themselves are the student's current membership, exactly like §7.1.
- Review stays separate from memorization; recitation reports stored sessions,
  grade counts and error totals with no derived score; no attendance rate is
  invented.
- The report never creates a weekly evaluation and never runs the engine.

### 7.3 Dashboard

`GET /dashboard` · permission `reports.view` · read-only.

| Parameter | Type | Notes |
|---|---|---|
| `month` | `YYYY-MM` | **required**. A calendar month, resolved exactly like §7.2. There is no clock-based default |
| `group_id` | int | optional; an out-of-scope id yields an empty dashboard, not a 403 |

```json
{
  "data": {
    "period": { "month": "2026-10", "start_date": "2026-10-01", "end_date": "2026-10-31" },
    "students": { "total": 120, "active": 115 },
    "groups": { "total": 8 },
    "attendance": { "present": 820, "late": 35, "excused_absence": 42, "unexcused_absence": 18 },
    "memorization": { "pages": "245.5000", "sessions": 310 },
    "review": { "pages": "180.2500", "sessions": 270 },
    "recitation": { "sessions": 190 },
    "weekly_evaluations": { "count": 420, "approved": 380, "computed": 40 }
  }
}
```

Scope and meaning:

- **Every figure is scoped to the actor's visible groups.** A manager sees the
  centre; a teacher sees the aggregate of *all* groups they own, never another
  teacher's. There is no centre-wide figure for a teacher, because that would
  leak other groups' volume.
- **`students.total` counts students with an active membership in those
  groups** (current membership, the same rule as the reports); `active` is the
  subset whose status is `active`. Students with no active membership are not
  counted — see OD-042.
- `groups.total` counts the visible groups (after `group_id`).
- Page amounts are decimal strings summed with `BigDecimal`; nothing is
  re-aggregated on the client.
- `weekly_evaluations` counts stored rows whose `week_start_date` falls inside
  the month. `computed` is never promoted.
- **No attendance rate, no derived percentage** — the business rules define
  neither, so the dashboard reports raw counts only.
- The dashboard never writes, never creates an evaluation and never runs the
  engine.

### 7.4 Report exports — PDF, XLSX and print HTML

Three endpoints export a report that already exists as JSON. Each one takes
**exactly** the filters of its report plus a `format`; the period rules,
validation messages and scope are inherited, not restated.

| Method | Path | Permission | Report |
|---|---|---|---|
| GET | `/reports/weekly/export` | `reports.export` | §7.1 |
| GET | `/reports/monthly/export` | `reports.export` | §7.2 |
| GET | `/dashboard/export` | `reports.export` | §7.3 |

| Parameter | Type | Notes |
|---|---|---|
| `format` | `pdf` \| `xlsx` \| `html` | optional, defaults to `html` (the print view) |
| *(report filters)* | | identical to the report being exported: `week_key` / `week_start_date` / `month`, `group_id`, `student_id` |

An unsupported `format` is `422` with `errors.format`. A missing `week_key`,
both week parameters, or a malformed `month` fail exactly as in §7.1–§7.3.

A report that is too large for a synchronous export is also `422`, but with a
different body — it is a capacity limit, not a bad request:

```json
{
  "message": "التقرير أكبر من حد التصدير المتزامن (312 صفًا، الحد 200). ضيّق النطاق عبر group_id أو student_id.",
  "error": "export_too_large",
  "limits": { "reason": "rows", "actual": 312, "limit": 200 }
}
```

`limits.reason` is `rows` when the row ceiling was hit, or `html_bytes` when a
single PDF document exceeded the engine's HTML budget. Both are checked before
the file is built, so the request fails fast instead of exhausting the worker.
See §7.4.1 for the numbers behind the ceiling.

#### Response

| `format` | `Content-Type` | `Content-Disposition` |
|---|---|---|
| `pdf` | `application/pdf` | `attachment` |
| `xlsx` | `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` | `attachment` |
| `html` | `text/html; charset=UTF-8` | `inline` |

File names are ASCII (no Arabic in a header value) and carry the period:

| Endpoint | File name |
|---|---|
| weekly | `tahfeez-weekly-2026-10-24_2026-10-30.pdf` |
| monthly | `tahfeez-monthly-2026-10.pdf` |
| dashboard | `tahfeez-dashboard-2026-10.pdf` |

The extension follows `format` (`.pdf`, `.xlsx`, `.html`).

```bash
# Weekly report as PDF
curl -H "Authorization: Bearer $TOKEN" \
  "$API/v1/reports/weekly/export?week_key=2026-10-26&format=pdf" -o weekly.pdf

# Monthly report for one group as Excel
curl -H "Authorization: Bearer $TOKEN" \
  "$API/v1/reports/monthly/export?month=2026-10&group_id=12&format=xlsx" -o monthly.xlsx

# Print view (default format), ready for the browser's print dialog
curl -H "Authorization: Bearer $TOKEN" \
  "$API/v1/reports/weekly/export?week_start_date=2026-10-24" -o weekly.html
```

#### What the files contain

The figures are produced by the same query objects as §7.1–§7.3, so an export
cannot disagree with the report it came from. Nothing is recomputed.

The weekly and monthly exports carry three tables, one per activity type:

1. **الحضور والحفظ والمراجعة** — attendance counts, `required_pages`,
   `actual_pages`, achievement percentage, memorization sessions, review pages
   and review sessions.
2. **التسميع** — recitation sessions, the three error counters and the stored
   grade counts.
3. **التقييم الأسبوعي (لقطة محفوظة)** — the stored evaluation: weighted score,
   its achievement percentage, completeness and status. The monthly export
   replaces this table with the count of weekly evaluations per student.

The dashboard export is a set of label/value tables (الملخص العام, الحضور,
النشاط والتقييم) — no per-student rows are rebuilt.

Rules that hold in every format:

- **Achievement percentage is uncapped.** `2.5000` pages against a requirement
  of `1.00` is exported as `250.00%`, never `100%`.
- **The weighted score is never mixed with achievement.** It is a 0–100 index
  and lives in its own table, in its own column, without a percent sign.
- **No aggregate achievement is added to a totals row.** Averaging uncapped
  ratios would be a new figure that no business rule defines, so that cell is
  left empty.
- **A missing value is `—`, never `0`.** A monthly achievement whose requirement
  sum is zero is exported as `—`.
- **Memorization, review and recitation stay separate.** No blended total.
- **Decimals keep their source scale.** Pages are exported at 4 dp and required
  pages at 2 dp; the XLSX cell is numeric with an explicit number format
  (`0.0000`, `0.00`, `0.00"%"`), so `1.5000` never becomes `1.5`.

#### PDF and Arabic

The PDF is produced by mPDF from the same Blade view the print page uses. The
document is written RTL, uses a font that covers Arabic, and shapes the script
contextually. Every numeric run (dates, amounts, percentages) is wrapped in
`dir="ltr"` so the bidi algorithm cannot reorder it: `2026-10-24` is drawn
`2026-10-24`, not `24-10-2026`. Weekly and monthly PDFs are A4 landscape
(thirteen columns do not fit a portrait page); the dashboard is A4 portrait.

The print view hides its toolbar under `@media print`, sets `@page` to A4 and
repeats table headers across pages. `@page` is emitted for the browser only:
mPDF mis-paginates when it sees an `@page` size and would break the document
into one page per block.

**The face is pinned to DejaVu Sans.** mPDF's `autoLangToFont` would otherwise
switch Arabic runs to *XB Riyaz*, a font bundled with the library whose own name
table declares the GNU General Public License — not a licence this project can
rely on for embedding. DejaVu ships with the library under the Bitstream Vera
licence, which explicitly permits embedding and redistribution, and it carries
the Arabic GSUB tables mPDF needs. `autoScriptToLang` stays enabled: script
detection still drives shaping, it just no longer swaps the font. The embedded
subset (`MPDFAA+DejaVuSans`, `Type0`/`Identity-H`, with a `ToUnicode` map) is
produced from `vendor/mpdf/mpdf/ttfonts`, so a clean deployment that ran
`composer install` needs nothing from the host operating system.

The engine is GPL-2.0-only and its adoption is tied to the centrally hosted
model; any future distribution of the backend needs a licence review first. The
record, the alternatives and the review triggers are in
[`dependencies.md`](dependencies.md) §3.

#### XLSX layout

A `ملخص` sheet holds the title, the period and the report totals, then one
worksheet per table (all right-to-left, header row frozen, totals row bold).
Sheet names are the Arabic table headings.

#### Permissions and scope

- `reports.export` — a separate grant from `reports.view`. A manager holds it
  through `*`; a teacher holds it only when the manager enables it
  (`permissions.md` §3). Without it the endpoint is `403`.
- Scope is structural, not a second implementation: the query objects resolve
  the actor's visible groups, so a teacher's file only contains their own
  students, and a foreign `group_id` or `student_id` yields an empty report
  rather than another center's rows.

#### Limits

- Synchronous only: a single week or month per request. There is no job id, no
  signed download URL and no TTL — BR-RPT-03's asynchronous path is not built.
- Only the weekly report, the monthly report and the dashboard are exportable.
  The remaining reports in the §7 table have no endpoint yet.
- Totals sum amounts and counts. They never sum scores, percentages or grades.

#### 7.4.1 Synchronous export ceiling

An export is built in memory, inside the request. Past a certain size that stops
being safe — mPDF refuses an HTML string larger than `pcre.backtrack_limit`, and
every format pays for the whole document in RAM — so the ceiling is enforced
before the file is generated and reported as `422 export_too_large`.

**The ceiling is 200 rows** (the longest table in the export, i.e. the number of
students). It is measured, not guessed:

| Rows | PDF | XLSX | HTML | Peak RAM (PDF) | HTML size |
|---|---|---|---|---|---|
| 1 | 0.4 s | 0.2 s | 0.05 s | 42 MB | 15 KB |
| 49 | 1.6 s | 1.0 s | 0.12 s | 50 MB | 183 KB |
| **200 (limit)** | **6.4 s** | **2.7 s** | **0.24 s** | **74 MB** | **712 KB** |
| 250 | rejected in 0.23 s | 0.25 s | 0.29 s | 28 MB | — |
| 300 | rejected in 0.34 s | 0.38 s | 0.35 s | 30 MB | — |
| 500 | rejected in 0.50 s | 0.54 s | 0.45 s | 32 MB | — |
| 1000 | rejected in 0.86 s | 1.0 s | 0.92 s | 42 MB | — |

Why 200:

- **The engine's hard limit.** `pcre.backtrack_limit` defaults to 1,000,000
  bytes of HTML; the weekly report reaches it at ~276 rows. At 200 the document
  is 712 KB — 71% of the budget, leaving room for longer names.
- **Memory.** 74 MB peak fits a 128 MB `memory_limit`, the common shared-host
  default. Without the ceiling the same export needs 132 MB at 500 rows and
  226 MB at 1000, and dies on a 128 MB worker.
- **Time.** ~6 s is the worst case a synchronous download should spend. The
  async path (BR-RPT-03) is what removes the ceiling, not a larger number here.

Rejections are cheap: the guard runs after the sixteen report queries and
before any rendering, so a 1000-row request costs under a second and never
allocates the document.

Both knobs live in `config/exports.php`:

| Key | Default | Meaning |
|---|---|---|
| `max_rows` (`EXPORT_MAX_ROWS`) | `200` | rows per export, all formats |
| `pdf_html_budget_ratio` (`EXPORT_PDF_HTML_BUDGET_RATIO`) | `0.8` | share of `pcre.backtrack_limit` one PDF may use |

Raise `max_rows` only together with `memory_limit`; the PDF budget scales with
the host's pcre limit automatically, so it never needs raising.
