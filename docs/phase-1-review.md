# Phase 1 — Review (Murajaʿah) Vertical Slice

Branch: `feature/phase-1-review` · follows `9f478dc` (memorization).

Review is an **independent entity** from new memorization (BR-REV-01). It has
its own table, model, endpoints and reports. It never contributes to the
memorization totals (BR-REV-04), and nothing here computes a weekly score or a
ranking. OD-029 stays untouched; the Quran provider remains a development
fixture.

## 1. Scope

| Included | Not included |
| --- | --- |
| Independent review records | Weekly evaluation / weighted score |
| Historical group and author | Reports / rankings / dashboards |
| Manual and calculated amounts | Memorization totals (never mixed) |
| Grade, three error counters, notes | Real Quran import (OD-029) |
| Soft delete, client_uuid idempotency | Full offline sync |

## 2. Data model

```text
reviews
  center_id · student_id · group_id · teacher_id
  review_date (date)
  surah_id · from_ayah_id · to_ayah_id            -- global Quran ids
  from_ayah_number · to_ayah_number              -- numbers within the surah
  from_page · to_page
  amount_pages numeric(8,4) · amount_lines
  amount_source CALCULATED | MANUAL
  quran_dataset_version_id (no FK yet) · quran_dataset_version
  grade · hifz_errors · tajweed_errors · reading_errors · notes
  client_uuid · version · timestamps · deleted_at

  unique (center_id, client_uuid)
  index (center_id, student_id, review_date)
  index (center_id, teacher_id, review_date)
```

- `group_id` is captured at recording time and never rewritten by a transfer.
- `teacher_id` is the author of the record.
- Multiple reviews per student per day are allowed (two sessions); idempotency
  is by `client_uuid`.
- `quran_dataset_version_id` has no FK until the dataset table exists; the
  label (`fixture-dev-v1`) explains what measured the record.

## 3. Amount pipeline

Review uses the **same** shared resolver as memorization:
`App\Domain\Quran\Actions\ResolveQuranAmount`.

```text
MANUAL      → the caller supplies amount_pages; the range is optional
CALCULATED  → the range is required; QuranDataProvider measures it
```

- The measurement formula lives only in `QuranDataProvider`
  (`Σ covered_lines(p) / lines_count(p)`), never in Review.
- No `/15` anywhere. The fixture uses an **8-line page 1** and a **15-line
  page 2**; a test records the same 8 lines on both pages and asserts
  `1.0000` vs `0.5333`.
- A provider failure is a clear **422**, never a guessed amount.

## 4. API

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/reviews` | List/filter (`student_id`, `group_id`, `date`) |
| `POST` | `/api/v1/reviews` | Create (201) or idempotent replay (200) |
| `GET` | `/api/v1/reviews/{id}` | Show |
| `PATCH` | `/api/v1/reviews/{id}` | Grade / errors / notes / manual amount |
| `DELETE` | `/api/v1/reviews/{id}` | Soft delete |

The resource exposes the range, pages, amount, source, grade, three error
counters and notes. It has **no** `achievement_pct`, `weighted_score` or
`week_key`.

## 5. Permissions

| Case | Expected |
| --- | --- |
| Manager, own center, any date | `200/201` |
| Teacher, own group, record/read | `200/201` |
| Teacher, another teacher's student/record | **404** |
| Teacher, update today's own record | `200` |
| Teacher, update an older record | **403** |
| Teacher, delete | **403** (`review.delete` is manager-only) |
| Cross-center | **404** |

`review.record` and `review.update` are teacher defaults.
`Review::visibleTo()` = center for a manager; records the teacher authored or
whose historical group they own (including soft-deleted groups) for a teacher.

## 6. Flutter

- `ReviewPage` opens from a student row in `Group Details` (replay icon) via
  `/students/:id/review`.
- Inputs: surah (`/quran/surahs`), from/to ayah, amount source
  (حساب تلقائي / مقدار يدوي), amount and pages in manual mode, grade chips,
  three error counters, notes.
- Calculated mode derives pages and amount from the range; manual mode accepts
  the amount and optional pages.
- Arabic, RTL, Material 3; nothing is written before the save button.

## 7. Business rules enforced

- **BR-REV-01** — independent entity; separate table/endpoints.
- **BR-REV-02** — student, surah, range, pages, amount, grade, errors, notes,
  date, teacher.
- **BR-REV-03** — same measurement engine and CALCULATED/MANUAL rule.
- **BR-REV-04** — never aggregated into new-memorization totals.
- **BR-MEM-06 / BR-QRN-09** — measurement from page data, no constant divisor.
- **BR-MEM-11** — no future date; no date before enrollment.
- **BR-TEN-01/02** — `center_id` from the actor; cross-center = 404.
- **BR-AUT-02/05** — teacher scope; history keeps the original author/group.

## 8. Testing

Backend (executed):

```
php artisan test
Tests: 147 passed (459 assertions)

vendor/bin/pint --test
PASS — 122 files
```

Review-specific: **28** tests — 23 feature (create/read/update/delete,
teacher/manager scopes, cross-center 404, date/group/author storage,
idempotency, multiple per day, calculated via provider, 8-line vs 15-line page
proof, provider failure 422, start>end 422, calculated amount not manually
editable, source immutable, no memorization/attendance coupling, filters) +
5 policy unit tests.

The memorization suite still passes after extracting `ResolveQuranAmount`
(35 tests), proving the refactor did not change behaviour.

Flutter: `dart format` parsed all 59 Dart files (0 changes on re-check).
Model, repository and screen tests were written. The Flutter SDK is not
installed here, so `flutter analyze` / `flutter test` were **not** executed
locally; CI runs them.

## 9. Known limitations

- The fixture is partial (Al-Fatiha, Al-Baqarah 1–7, Al-Ikhlas, An-Nas);
  calculated reviews outside it fail with 422. Use manual mode or extend the
  fixture.
- The API/UI expose a single-surah range; cross-surah ranges remain
  representable at the domain level.
- Editing a calculated review's range/amount is not supported; only grade,
  errors, notes (and a manual amount) can be corrected.
- `week_key` and evaluation columns are deferred.
- No offline queue yet; `client_uuid` and `version` keep the door open.
