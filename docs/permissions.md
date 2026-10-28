# Tahfeez — Permissions Model

Status: **Draft for review** · Phase 0
Implements §6, §20, BR-AUT-01…06.

---

## 1. Model

```text
User ──(belongs to)── Center
  └── Role: center_manager | muhaffiz
        └── Permissions (granular, center-scoped)
              └── Scope: all_students | assigned_students
```

Three layers, all enforced server-side:

1. **Authentication** — valid token bound to a device.
2. **Role/permission** — does this user hold `memorization.record`?
3. **Record scope** — is this student assigned to this teacher, in this center?

A request passes only if all three pass. Hiding a button in Flutter is never a control (BR-AUT-06).

---

## 2. Permission catalogue

| Permission | Meaning |
|---|---|
| `students.view` | List/view students in scope |
| `students.create` | Enroll a student |
| `students.update` | Edit student profile |
| `students.delete` | Deactivate/delete (manager) |
| `students.assign` | Change teacher assignment |
| `memorization.record` / `.update` / `.delete` | New hifz records |
| `review.record` / `.update` / `.delete` | Review records |
| `recitation.record` / `.update` / `.delete` | Recitation records |
| `attendance.view` / `.record` / `.update` | Attendance |
| `evaluation.view` | See weekly evaluations |
| `evaluation.compute` | Generate evaluations for a week |
| `evaluation.approve` | Approve/lock a week |
| `evaluation.recompute` | Recompute an approved week |
| `evaluation.template.view` / `.create` / `.update` / `.publish` | Templates |
| `evaluation.profile.view` / `.change` | Per-student profile versions |
| `reports.view` / `reports.export` | Reports and exports |
| `users.view` / `.create` / `.update` / `.permissions` | Teacher management |
| `settings.view` / `.update` | Center settings |
| `audit.view` | Audit trail |

---

## 3. Default matrix

| Permission | center_manager | muhaffiz (default) | muhaffiz (grantable) |
|---|:--:|:--:|:--:|
| students.view | all | assigned | assigned |
| students.create | ✓ | — | — |
| students.update | ✓ | assigned, limited fields | ✓ |
| students.delete | ✓ | — | — |
| students.assign | ✓ | — | — |
| memorization.record | ✓ | ✓ | ✓ |
| memorization.update | ✓ | own records, same day | ✓ |
| memorization.delete | ✓ | — | ✓ |
| review.record | ✓ | ✓ | ✓ |
| recitation.record | ✓ | ✓ | ✓ |
| attendance.view | ✓ | assigned | ✓ |
| attendance.record | ✓ | ✓ | ✓ |
| evaluation.view | ✓ | assigned | ✓ |
| evaluation.compute | ✓ | — | ✓ |
| evaluation.approve | ✓ | — | — |
| evaluation.recompute | ✓ | — | — |
| evaluation.template.view | ✓ | ✓ | ✓ |
| evaluation.template.create/update | ✓ | — | ✓ |
| evaluation.template.publish | ✓ | — | — |
| evaluation.profile.change | ✓ | — | ✓ |
| reports.view | ✓ | assigned | ✓ |
| reports.export | ✓ | assigned | ✓ |
| users.* | ✓ | — | — |
| settings.* | ✓ | — | — |
| audit.view | ✓ | — | — |

"Grantable" means the manager may enable it per teacher; it is off by default (least privilege).

---

## 4. Scope rules (the part that is easy to get wrong)

| Rule | Enforcement |
|---|---|
| A teacher's student list is `students` joined to an **active** assignment to them | Global scope `AssignedStudentsScope` on the query builder |
| A teacher may not read a student by guessing an id | `StudentPolicy::view` → assignment check; failure returns **404** |
| A teacher may not record for an unassigned student, even with a valid payload | Policy on the *target student*, not on the record |
| A manager's scope is the center | `center_id` from the authenticated user, never from the request |
| Cross-center ids return 404, not 403 | Prevents enumeration (BR-TEN-02) |
| Historical records stay visible after reassignment | Read scope includes past assignments for records the teacher authored; write scope requires a current assignment |

---

## 5. Enforcement layers (defence in depth)

```text
Route middleware        → auth:sanctum, throttle, ResolveCenter
FormRequest::authorize  → coarse permission check
Policy                  → record + scope check (the real gate)
Action                  → re-asserts invariants (e.g. assignment still active inside the transaction)
Database                → center_id NOT NULL, composite FKs where practical
```

The Action re-check exists because a record can be reassigned between the policy check and the write. It is cheap and it closes a real race.

---

## 6. Permission changes and sync

- Permission changes are server-authoritative and require connectivity (§9 of `offline-sync.md`).
- The client caches the permission list for UI rendering only; every write is re-authorized server-side.
- A revoked permission takes effect on the next request; there is no long-lived authorization claim in the token.
- An offline client that lost a permission gets `403` on push; the operation moves to `failed` with an explanatory message, and the UI hides the action after the next `/auth/me`.

---

## 7. Test matrix (mandatory)

For each permission × role, three cases are tested:

| Case | Expected |
|---|---|
| Authorized actor, in scope | `200/201` |
| Authorized actor, **out of scope** (another teacher's student) | `404` |
| Unauthorized actor (permission missing) | `403` |

Plus these specific regressions:

- Teacher cannot escalate own permissions via `PATCH /teachers/{id}`.
- Manager cannot create a user in another center.
- Teacher cannot approve a weekly evaluation.
- Revoked device token is rejected on the next request.
- Deleting an assignment does not revoke read access to records the teacher authored (BR-AUT-05).
