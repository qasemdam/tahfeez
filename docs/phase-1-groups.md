# Phase 1 — Groups + Teacher Assignment

Branch: `feature/phase-1-foundation` · follows `5c9e939` (auth + students).

This slice adds the missing middle of the hierarchy:

```text
Center → Teachers → Groups (حلقات) → Students
```

Phase 0 did not define `groups`; the schema and the coupling to teacher
assignments are recorded in `open-decisions.md` **OD-030**. OD-029 stays
**Open / Blocked** — no Quran data, no QUL, no 1405H work in this slice.

## 1. Data model

```text
groups
  id · center_id · teacher_id (required) · name · status(active|inactive)
  notes · version · timestamps · deleted_at

group_memberships
  id · center_id · group_id · student_id · joined_at · left_at(null=active)
  assigned_by · reason · timestamps
  partial unique index (student_id) where left_at is null

student_teacher_assignments
  + group_id nullable   -- which group produced this assignment
```

- A student has at most one **active** group. Moving closes the old row; history
  is never deleted.
- The group's teacher is the student's teacher while the student is in it:
  join → reassign; leave → close only that group's assignment; change the
  teacher → reassign every active member; delete the group → close memberships
  and group-driven assignments.
- `group_id` is intentionally not a FK, so assignment history survives a purge.

## 2. API

| Method | Path | Who |
| --- | --- | --- |
| `GET` | `/api/v1/groups` | manager: all in center · teacher: own only |
| `POST` | `/api/v1/groups` | manager (`groups.create`) |
| `GET` | `/api/v1/groups/{id}` | manager in center · owning teacher |
| `PATCH` | `/api/v1/groups/{id}` | manager · owning teacher with `groups.update` |
| `DELETE` | `/api/v1/groups/{id}` | manager (`groups.delete`) |
| `POST` | `/api/v1/groups/{id}/students` | manager (`groups.manage_students`) |
| `DELETE` | `/api/v1/groups/{id}/students/{studentId}` | manager |
| `GET` | `/api/v1/teachers` | manager (`users.view`) — assignment picker |

`GET /groups/{id}` embeds the active students (`students[]`) plus
`students_count`. Adding a student already in the group is idempotent.

## 3. Permissions & isolation

| Case | Expected |
| --- | --- |
| Manager, own center | `200/201/204` |
| Manager, another center (group, student, teacher) | **404** |
| Teacher, own group, read | `200` |
| Teacher, another teacher's group | **404** |
| Teacher, write on own group (no permission) | **403** |
| Teacher, `GET /teachers` | **403** |

Enforcement is layered: `Group::visibleTo()` scopes the query (404), then
`GroupPolicy` decides 403. The client hides buttons cosmetically only.

## 4. Test evidence (executed)

```
php artisan test
Tests: 49 passed (152 assertions)

vendor/bin/pint --test
PASS — 73 files
```

New coverage: create/list/rename/delete group, change teacher (active members
reassigned, old assignment kept), add student (membership + teacher assignment),
idempotent add, move between groups (history preserved, one active membership),
remove student, inactive group rejects students, teacher read-only, teacher
cannot see another teacher's group, cross-center 404 for group/student/teacher,
and the policy unit matrix.

Flutter: `dart format` parsed all 34 Dart files (0 changes on re-check). The
Flutter SDK is not installed on this machine, so `flutter analyze` / `flutter
test` run in CI (`.github/workflows/mobile.yml`).

## 5. Flutter

- `GroupsPage` — manager sees **الحلقات**, teacher sees **حلقاتي**; card shows
  name, teacher, student count, status; FAB (manager) creates a group with a
  teacher picked from `GET /teachers`.
- `GroupDetailsPage` — teacher, status, student list; manager can add a student
  (filtered to non-members) and remove one with confirmation.
- `AppUser` now carries the server's **effective permissions** and a `can()`
  helper; the UI uses it for cosmetic gating.
- Login and splash now land on `/groups`; `/students` remains reachable from the
  groups app bar.

## 6. Deferred (not in this slice)

- Editing a student's group from the student screen.
- Group-level schedule / room / capacity.
- Attendance and memorization scoped to a group (next slices).
- Teacher self-service group creation (permission is grantable but the UI does
  not expose it yet).
