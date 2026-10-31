# Phase 1 — Memorization (New Hifz) Vertical Slice

Branch: `feature/phase-1-memorization` · follows `a87fed3` (attendance).

Records **new memorization** only. Review, attendance, evaluation and reports
are separate slices. Nothing here computes a weekly score, a ranking or an
attendance penalty. **OD-029 stays Open/Blocked**; the Quran provider is a
development fixture, not canonical data.

## 1. Scope

| Included | Not included |
| --- | --- |
| Simple and Professional entry modes | Review (Murajaʿah) |
| Manual and calculated amounts | Weekly evaluation / weighted score |
| Historical group and teacher | Reports / rankings |
| Grade, three error counters, notes | Quran reader / highlighting |
| Soft delete, client_uuid idempotency | Real QUL/KFGQPC import (OD-029) |
| Read-only surah list from the provider | Full offline sync |

## 2. Data model

```text
memorizations
  center_id · student_id · group_id · teacher_id
  memorization_date (date)
  surah_id · from_ayah_id · to_ayah_id            -- global Quran ids
  from_ayah_number · to_ayah_number              -- numbers within the surah
  from_page · to_page
  amount_pages numeric(8,4) · amount_lines
  amount_source CALCULATED | MANUAL
  quran_dataset_version_id (no FK yet) · quran_dataset_version
  grade · hifz_errors · tajweed_errors · reading_errors · notes
  client_uuid · version · timestamps · deleted_at

  unique (center_id, client_uuid)
  index (center_id, student_id, memorization_date)
  index (center_id, teacher_id, memorization_date)
```

- `group_id` is the group the student was in **at recording time**; it is never
  rewritten by a later transfer.
- `teacher_id` is the author of the record (a manager who records is the author).
- `from_ayah_number`/`to_ayah_number` make the API self-contained; the global
  ids remain the canonical reference.
- `quran_dataset_version` records which provider measured the amount
  (`fixture-dev-v1` today). The id has no FK because the dataset table is part
  of the blocked import.
- Multiple records per student per day are allowed (two sessions). Idempotency
  is by `client_uuid`.

## 3. Simple vs Professional

**Simple (default)** — the teacher only needs:

```text
التاريخ · المقدار · درجة التسميع (اختياري) · ملاحظات · حفظ
```

No surah, ayah or page is required. `amount_source = MANUAL`.

**Professional** — adds:

```text
السورة · من آية · إلى آية · من صفحة · إلى صفحة
مصدر المقدار (يدوي / تلقائي) · المقدار (يدوي فقط)
درجة التسميع · أخطاء الحفظ/التجويد/القراءة · ملاحظات
```

- `MANUAL`: the amount is required; the Quran range is optional. If a complete
  range is supplied, it is resolved and stored (pages default from the
  provider).
- `CALCULATED` ("AUTO"): the range is required; the client-supplied amount is
  ignored. If the provider cannot answer, the request fails with a clear
  **422**, never a guess.

## 4. QuranDataProvider

`App\Domain\Quran\Contracts\QuranDataProvider` is the only way the domain
reaches Quran data. It exposes:

```php
surahs(): array
linesOnPage(int $page): int
ayahLocation(int $surahId, int $ayahNumber): AyahLocation
measure(int $fromAyahId, int $toAyahId): PageMeasurement
datasetVersion(): string
datasetVersionId(): int
```

`PageMeasurement` already carried `pageStart`/`lineStart`/`pageEnd`/`lineEnd`
from Phase 0 and is reused unchanged.

The binding is `App\Infrastructure\Quran\FixtureQuranDataProvider` — a
**development fixture**, not an edition:

- Page 1 has **8** lines, page 2 has **15**. The engine reads `lines_count`
  from data and never divides by 15 (BR-QRN-09 / BR-MEM-06).
- It maps a small subset (Al-Fatiha, Al-Baqarah 1–7, Al-Ikhlas, An-Nas) and
  fails clearly for anything else.
- It proves the formula: `Σ covered_lines(p) / lines_count(p)`, with
  `page_start != page_end` for an ayah that crosses a page.

Swapping in the real dataset later means replacing the binding; no domain or
controller change is required.

## 5. API

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/memorization` | List/filter (`student_id`, `group_id`, `date`) |
| `POST` | `/api/v1/memorization` | Create (201) or idempotent replay (200) |
| `GET` | `/api/v1/memorization/{id}` | Show |
| `PATCH` | `/api/v1/memorization/{id}` | Grade / errors / notes / manual amount |
| `DELETE` | `/api/v1/memorization/{id}` | Soft delete |
| `GET` | `/api/v1/quran/surahs` | Read-only surah list from the provider |

The resource exposes `amount_pages`, `amount_lines`, `amount_source`,
`from_page`/`to_page`, `from_ayah`/`to_ayah`, `surah_name`, grade, the three
error counters and notes. It deliberately has **no** `achievement_pct`,
`weighted_score` or `week_key`.

## 6. Permissions

| Case | Expected |
| --- | --- |
| Manager, own center, any date | `200/201` |
| Teacher, own group, record/read | `200/201` |
| Teacher, another teacher's student/record | **404** |
| Teacher, update today's own record | `200` |
| Teacher, update an older record | **403** |
| Teacher, delete | **403** (`memorization.delete` is manager-only) |
| Cross-center | **404** |

`memorization.record` and `memorization.update` are teacher defaults.
`Memorization::visibleTo()` = center for a manager; records the teacher
authored or whose historical group they own (including soft-deleted groups)
for a teacher.

## 7. Flutter

- `MemorizationPage` opens from a student row in `Group Details` (book icon)
  via `/students/:id/memorization`.
- Simple mode is the default: amount, optional grade chips and notes, one save.
- Professional mode is a segmented option: surah picker (`/quran/surahs`),
  from/to ayah, from/to page, manual/calculated amount source, three error
  counters.
- Arabic labels: ممتاز، جيد جدًا، جيد، يحتاج مراجعة؛ يدوي/تلقائي.
- Nothing is persisted until the save button; a successful save shows a
  snackbar and returns to the group.

## 8. Business rules enforced

- **BR-MEM-02** — `amount_source` is explicit and immutable.
- **BR-MEM-03/05** — calculated ranges derive pages and amount from the
  provider; the client does not type the calculated amount.
- **BR-MEM-04** — a manual amount may omit the ayah range.
- **BR-MEM-06** — `Σ covered_lines(p) / lines_count(p)`, never `/ 15`.
- **BR-MEM-07** — a surah is never treated as a page.
- **BR-MEM-08** — `amount_pages` stored at 4 dp; `amount_lines` kept for
  calculated records.
- **BR-MEM-11** — no future date; no date before enrollment.
- **BR-MEM-14** — the dataset version label/id is stored on calculated records.
- **BR-ATT-04 / no evaluation** — no score is derived here.
- **BR-TEN-01/02** — `center_id` from the actor; cross-center = 404.

## 9. Testing

Backend (executed):

```
php artisan test
Tests: 119 passed (367 assertions)

vendor/bin/pint --test
PASS — 106 files
```

Memorization-specific: **43** tests — 30 feature (manager/teacher, simple,
professional, MANUAL, CALCULATED, provider failure, surah/ayah validation,
start/end, pages, historical group, author, grade, errors, notes, idempotency,
multiple per day, update/delete policy, no attendance coupling, no evaluation
fields, surah endpoint) + 5 policy unit tests + 8 fixture measurement tests
(including the 8-line page, partial page, cross-page range and a spanning
ayah).

Flutter: `dart format` parsed all 52 Dart files (0 changes on re-check).
Model, status, repository and screen tests were written. The Flutter SDK is not
installed here, so `flutter analyze` / `flutter test` were **not** executed
locally; CI runs them.

## 10. Known limitations

- The fixture is partial: only four surahs are mapped. A CALCULATED request for
  an unmapped ayah fails with 422; use MANUAL or extend the fixture.
- The API/UI expose a single-surah range. The domain accepts global ayah ids,
  so cross-surah ranges (BR-MEM-13) are representable later without a schema
  change.
- Editing a CALCULATED record's range/amount is not supported; only grade,
  errors, notes (and a MANUAL amount) can be corrected.
- `week_key` and all evaluation columns are deferred to the evaluation slice.
- No offline queue yet; `client_uuid` and `version` keep the door open.
- Surah names are display-only, resolved from the provider at read time; they
  are not stored on the record.
