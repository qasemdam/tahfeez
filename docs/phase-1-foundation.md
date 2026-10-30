# Phase 1 — Foundation (first vertical slice)

Branch: `feature/phase-1-foundation`

This slice proves the stack end to end for the smallest useful path:
**sign in → see the students you are allowed to see → enroll a student and assign a teacher.**
Nothing from Phase 0 was reopened; the Quran master-data gate (OD-029) stays
Open/Blocked and is isolated behind `QuranDataProvider`.

## 1. Scope

| Area | Included in this slice |
| --- | --- |
| Authentication | Email + password, device-scoped bearer token, `me`, `logout` |
| Users & roles | `center_manager` and `teacher`, role defaults + per-user grants |
| Students | List (scoped), search, enroll, assign teacher, show |
| Evaluation | Uncapped achievement-percentage formula (pure, unit-tested) |
| Quran data | Interface only (`QuranDataProvider`); no dataset, no fixtures committed |
| Out of scope | Groups, attendance, memorization, review, weekly snapshot, reports |

## 2. Backend (`backend/`, Laravel 12.70.2 / PHP 8.2)

### Endpoints

| Method | Path | Auth | Notes |
| --- | --- | --- | --- |
| `POST` | `/api/v1/auth/login` | public | Returns `data.token`, `data.expires_at`, `data.user` |
| `GET` | `/api/v1/auth/me` | bearer | Current user + center |
| `POST` | `/api/v1/auth/logout` | bearer | Deletes the current token row |
| `GET` | `/api/v1/students` | bearer | Scoped list, `?search=` |
| `POST` | `/api/v1/students` | manager | Enroll, optional `teacher_id` |
| `GET` | `/api/v1/students/{id}` | bearer | Out-of-scope returns 404, not 403 |

### Invariants preserved

- **BR-SEC-03** — only a SHA-256 hash of the token is stored; the plaintext is
  returned once and never persisted. Asserted in `AuthTest`.
- **BR-AUT-02** — a teacher sees only actively assigned students; the scope is
  applied in `Student::scopeVisibleTo()`, not in the controller.
- **BR-AUT-04** — manager holds `*`; a teacher holds role defaults plus explicit
  grants. Asserted in `UserPermissionTest`.
- **BR-STU-04** — assignment is history (`student_teacher_assignments`), closed
  inside a transaction by `AssignStudentToTeacher`. PostgreSQL adds a partial
  unique index; SQLite relies on the action + tests.
- **BR-TEN-01/02** — every query is anchored to `center_id`; cross-tenant access
  yields 404.
- **Achievement %** — `actual / required × 100`, uncapped. One page = 100%,
  two pages = 200%. Never clamped, never substituted with "top student = 100%".

### Quran isolation

`app/Domain/Quran/Contracts/QuranDataProvider.php` and
`app/Domain/Quran/Support/PageMeasurement.php` exist so later slices can depend
on an interface. No 1405H dataset, no QUL import, and no licensing decision is
made here (OD-029 remains Open/Blocked).

## 3. Mobile (`mobile/`, Flutter)

- Arabic-first, RTL forced at the app root; Material 3 green theme.
- Riverpod for state/DI, go_router for navigation, Dio for HTTP.
- Screens: splash (session restore) → login → students list.
- `SessionStore` uses SharedPreferences as an **MVP placeholder**, explicitly
  marked for replacement by secure storage in the offline/sync slice.
- API base URL is injected at build time:
  `flutter run --dart-define=API_BASE_URL=http://<host>:8000/api/v1`.

## 4. Test evidence

Backend (executed):

```
php artisan test
Tests: 25 passed (68 assertions)

vendor/bin/pint --test
PASS — 51 files
```

Coverage: login success/failure/inactive, token hashing, `me`, logout
revocation, manager vs teacher visibility, enroll + assign, search, 404 scoping,
permission resolution, and the uncapped achievement formula
(0.5→50%, 1→100%, 1.5→150%, 2→200%).

Mobile (executed): `dart format` parsed all 23 Dart files and left them
formatted (0 changes on re-check).

Mobile tests are written but **not executed locally** — the Flutter SDK is not
installed on this machine (Dart 3.13.5 is). Run them with:

```
cd mobile
flutter pub get
flutter analyze
flutter test
```

## 5. Environment notes

- No PostgreSQL server is installed. Tests run on SQLite in-memory
  (`phpunit.xml`), which is why CHECK constraints are guarded by
  `DB::getDriverName() === 'pgsql'`.
- `composer install` initially left no autoloader; re-running it regenerated
  `vendor/autoload.php` and `php artisan` works (Laravel 12.70.2).
- CI (`.github/workflows/`) runs the backend suite on PHP 8.2 + SQLite and the
  mobile suite on the stable Flutter channel.

## 6. Next slice (not started)

Groups → attendance → memorization → review → weekly evaluation snapshot →
reports. Each is its own branch/commit, and none reopens the Quran gate.
