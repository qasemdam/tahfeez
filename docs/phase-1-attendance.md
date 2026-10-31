# Phase 1 — Attendance Vertical Slice

Branch: `feature/phase-1-attendance` · follows `62447bf` (groups + teacher assignment).

Records daily attendance inside a group, with historical group capture,
permissions, a fast daily sheet, and tests. **Attendance is not evaluation**:
nothing here derives a grade or a penalty (BR-ATT-04). OD-029 stays untouched.

## 1. Scope

| Included | Not included |
| --- | --- |
| One record per student per day | Sessions / morning-evening shifts (OD-007) |
| Four statuses + optional late minutes | Attendance-based evaluation weights |
| Historical group on each record | Full offline sync / conflict resolution |
| Group daily sheet + bulk save | Reports / exports |
| Manager and teacher scopes | Notifications |

## 2. Data model

```text
attendances
  id · center_id · student_id · group_id · teacher_id
  attendance_date (date) · status (varchar 20) · late_minutes (smallint null)
  notes (text null) · client_uuid (uuid null) · version (int)
  timestamps · deleted_at

  unique (center_id, student_id, attendance_date)   -- one row per student/day
  unique (center_id, client_uuid)                   -- offline idempotency
  index (center_id, attendance_date, status)
  index (center_id, group_id, attendance_date)
```

- **`group_id` is historical.** It is set when the record is created and never
  changed when the student moves groups. `RESTRICT` protects it from a hard
  group purge; groups are soft-deleted, so the normal delete path is unaffected.
- **One row per student per day** (OD-007, OD-032). Re-submitting updates the
  existing row and keeps the original group and recorder.
- `student`, `group` and `teacher` relations include soft-deleted rows, so a
  deleted group or student does not erase the meaning of an old record.

## 3. API

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/attendance` | List/filter (`group_id`, `student_id`, `date`) |
| `POST` | `/api/v1/attendance` | Record one student (upsert; 201 new / 200 updated) |
| `GET` | `/api/v1/attendance/{id}` | Show one record |
| `PATCH` | `/api/v1/attendance/{id}` | Correct status / late minutes / notes |
| `GET` | `/api/v1/groups/{group}/attendance?date=` | Daily sheet for a group |
| `POST` | `/api/v1/groups/{group}/attendance` | Save the whole sheet (idempotent) |

Sheet response:

```json
{
  "data": {
    "group_id": 1,
    "group_name": "حلقة النور",
    "teacher_name": "أستاذ خالد",
    "date": "2026-10-31",
    "students": [
      { "student_id": 5, "full_name": "أحمد", "attendance_id": 12,
        "status": "present", "late_minutes": null, "notes": null },
      { "student_id": 6, "full_name": "خالد", "attendance_id": null,
        "status": null, "late_minutes": null, "notes": null }
    ]
  }
}
```

`status: null` means "not recorded yet"; the UI defaults it to present but
writes nothing until save.

## 4. Permissions

| Case | Expected |
| --- | --- |
| Manager, own center, any date | `200/201` |
| Teacher, own group, record/read | `200/201` |
| Teacher, another teacher's group/student | **404** |
| Teacher, update today's own record | `200` |
| Teacher, update an older record | **403** |
| Teacher, cross-center | **404** |
| Unauthorized write (missing permission) | **403** |

`attendance.view` and `attendance.record` are teacher defaults;
`attendance.update` is also a teacher default but limited to the center's
current business day (OD-033). Managers hold `*`. Scope is structural:
`Attendance::visibleTo()` (manager = center, teacher = historical group owner)
and `Group::visibleTo()` for the sheet.

## 5. UX

```text
Groups → group → الحضور
  ↓
date (defaults to today, no future dates)
  ↓
every active student in the group, default = حاضر
  ↓
tap an exception chip (معذور / غائب / متأخر), late shows a minutes field
  ↓
حفظ الحضور → one POST for the whole sheet
```

- Default present, so a normal day is one tap (save).
- Nothing is written before save.
- Arabic labels: حاضر، معذور، غائب، متأخر. Wire values stay English.

## 6. Business rules enforced

- **BR-ATT-01** — exactly four statuses.
- **BR-ATT-02** — `late_minutes` only with `late`, `>= 0`; cleared otherwise.
- **BR-ATT-03** — one record per student per day; re-submit updates.
- **BR-ATT-04** — no evaluation coupling, no automatic penalty.
- **BR-ATT-05** — no date before `enrolled_at`; no future date; inactive
  students cannot be recorded; a status change never rewrites history.
- **BR-ATT-06** — bulk sheet save is idempotent.
- **BR-TEN-01/02** — `center_id` comes from the actor; cross-center = 404.
- **BR-AUT-02/05** — teacher scope is the historical group; assignment changes
  do not erase old records.

## 7. Testing

Backend (executed):

```
php artisan test
Tests: 76 passed (237 assertions)

vendor/bin/pint --test
PASS — 88 files
```

Attendance-specific: 23 feature tests + 4 policy unit tests, covering manager
and teacher recording, out-of-scope 404, cross-center 404, all four statuses,
late minutes (stored / rejected for non-late / negative), duplicate upsert,
historical group preservation, group deletion not breaking history, same-day
teacher edit vs old-record 403, manager any-date edit, sheet defaults, bulk
idempotency, before-enrollment and future-date rejection, student without a
group, cross-center bulk, and a student outside the group.

Flutter: `dart format` parsed all 42 Dart files (0 changes on re-check). Tests
were written for status mapping, models, repository and the attendance screen.
The Flutter SDK is not installed in this environment, so `flutter analyze` /
`flutter test` were **not** executed locally; CI runs them.

## 8. Known limitations

- The sheet's default date is the device's date; the server defaults to the
  center's timezone when the date is omitted. A device in another timezone
  should send an explicit date. The UI currently always sends one.
- No offline queue yet. The schema (`client_uuid`, `version`) and the upsert
  are ready for one; the local database and sync loop are later slices.
- No delete endpoint; soft-delete columns exist for a future audited removal.
- A teacher cannot see attendance of a group they never owned, even after a
  transfer; the historical group decides visibility.
- Attendance does not affect any score in this slice, by design.
