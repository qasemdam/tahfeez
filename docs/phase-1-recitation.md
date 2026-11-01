# Phase 1 — Recitation (Tasmiʿ) Vertical Slice

Branch: `feature/phase-1-recitation` · follows `46ed460` (review).

Recitation is a **standalone performance event**: it records what the student
recited, the grade and the error breakdown. It does not create or change a
memorization, review or attendance row, and it does not compute a score or a
weekly value. OD-029 stays untouched; the Quran provider remains a development
fixture.

## 1. Scope

| Included | Not included |
| --- | --- |
| Standalone recitation records | Rule set / computed score (BR-REC-02/03/04) |
| Historical group and author | Attachment to memorization/review (OD-006) |
| Quran range validation via the provider | Weekly evaluation / reports |
| Grade + three error counters + notes | Amount pages / lines |
| Soft delete, client_uuid idempotency | Offline sync |

## 2. Data model

```text
recitations
  center_id · student_id · group_id · teacher_id
  recitation_date (date)
  surah_id · from_ayah_id · to_ayah_id
  from_ayah_number · to_ayah_number
  from_page · to_page
  grade · hifz_errors · tajweed_errors · reading_errors · notes
  quran_dataset_version_id (no FK yet) · quran_dataset_version
  client_uuid · version · timestamps · deleted_at

  unique (center_id, client_uuid)
  index (center_id, student_id, recitation_date)
  index (center_id, teacher_id, recitation_date)
```

- **No `amount_pages`/`amount_lines`.** The ayah/page range is the record; a
  page amount would turn recitation into a memorization copy.
- **No rule set or computed score.** The grade and error counters are raw data;
  scoring belongs to the evaluation slice (BR-REC-02 forbids hard-coding it).
- `group_id` is the historical group at recording time; `teacher_id` is the
  author. A later transfer does not rewrite the record.
- The dataset version is stored because the derived pages are edition-specific
  (BR-QRN-10); it is provenance, not a score.
- Multiple recitations per student per day are allowed; idempotency is by
  `client_uuid`.

## 3. Quran validation

`RecordRecitation` uses `QuranDataProvider::ayahLocation()` for the start and
end ayah:

- unknown surah/ayah → clear **422**;
- `from_ayah > to_ayah` → **422**;
- the page span is derived from the provider;
- a client-supplied `from_page`/`to_page` must match the derived span,
  otherwise **422**;
- no `/15`, no direct KFGQPC/QUL dependency, no fallback or guessing.

No amount is measured, so `ResolveQuranAmount` is not used here.

## 4. API

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/recitations` | List/filter (`student_id`, `group_id`, `date`) |
| `POST` | `/api/v1/recitations` | Create (201) or idempotent replay (200) |
| `GET` | `/api/v1/recitations/{id}` | Show |
| `PATCH` | `/api/v1/recitations/{id}` | Grade / errors / notes |
| `DELETE` | `/api/v1/recitations/{id}` | Soft delete |

The resource exposes the range, pages, grade, three error counters and notes.
It has **no** `amount_pages`, `amount_lines`, `achievement_pct` or
`weighted_score`.

## 5. Permissions

| Case | Expected |
| --- | --- |
| Manager, own center, any date | `200/201` |
| Teacher, own group, record/read | `200/201` |
| Teacher, another teacher's student/record | **404** |
| Teacher, update today's own record | `200` |
| Teacher, update an older record | **403** |
| Teacher, delete | **403** (`recitation.delete` is manager-only) |
| Cross-center | **404** |

`recitation.record` and `recitation.update` are teacher defaults.
`Recitation::visibleTo()` = center for a manager; records the teacher authored
or whose historical group they own (including soft-deleted groups) for a
teacher.

## 6. Flutter

- `RecitationPage` opens from a student row in `Group Details` via
  `/students/:id/recitation`.
- Inputs: surah (`/quran/surahs`), from/to ayah, optional from/to page, grade
  chips, three error counters, notes.
- Arabic, RTL, Material 3; no amount field; nothing is written before save.
- The student row actions (memorization, review, recitation, remove) are now in
  a single popup menu, so four actions do not overflow a phone-width row.

## 7. Business rules enforced

- **BR-REC-01** — four grades + hifz/tajweed/reading error breakdown.
- **BR-REC-02/03/04** — no formula is hard-coded and no score is computed; the
  rule-set engine is deferred.
- **BR-REC-05** — first-class standalone record; attachment remains OD-006.
- **BR-MEM-11 logic** — no future date; no date before enrollment.
- **BR-TEN-01/02** — `center_id` from the actor; cross-center = 404.
- **BR-AUT-02/05** — teacher scope; history keeps the original author/group.
- **No coupling** — a recitation never creates or changes memorization, review
  or attendance.

## 8. Testing

Backend (executed):

```
php artisan test
Tests: 176 passed (553 assertions)

vendor/bin/pint --test
PASS — 136 files
```

Recitation-specific: **29** tests — 24 feature (CRUD, manager/teacher scopes,
cross-center 404, filters, historical group/author, multiple per day,
idempotency, unknown surah/ayah, start>end, page contradiction, page order,
invalid grade, negative errors, provider failure 422, teacher same-day update,
delete policy, separation from memorization/review/attendance) + 5 policy unit
tests.

Flutter: `dart format` parsed all 65 Dart files (0 changes on re-check). Model,
repository and screen tests were written. The Flutter SDK is not installed
here, so `flutter analyze` / `flutter test` were **not** executed locally; CI
runs them.

## 9. Known limitations

- The fixture is partial; a recitation outside its mapped surahs/ayahs fails
  with 422. The UI only lists the fixture's surahs.
- No computed score or rule set yet (BR-REC-02/03/04 are deferred).
- No attachment to a memorization/review (OD-006 remains open).
- Editing the range/student/date/group is not supported; only grade, errors and
  notes can be corrected.
- No offline queue yet; `client_uuid` and `version` keep the door open.
