# Tahfeez — Quran Master Data Model Specification

Status: **Phase 0 specification, for review** · 2026-10-28
Companion: `quran-data.md` (import & measurement), `quran-data-sources-comparison.md` (source study), `database.md` (physical schema)

This document defines the **minimum** data Tahfeez must hold to measure memorization exactly, and the explicit boundary of what it must *not* hold.

---

## 1. Invariants

| # | Invariant | Enforced by |
|---|---|---|
| I1 | Quran data is read-only for every role | no write route, no policy grants write |
| I2 | Page height is data, never a constant | `quran_pages.lines_count`; BR-QRN-09 |
| I3 | Every import is an immutable, version-keyed snapshot | `quran_dataset_versions` + composite PKs |
| I4 | Every calculated amount is traceable to one dataset version | `records.quran_dataset_version_id` |
| I5 | A new dataset never changes a historical amount | no `UPDATE` path on snapshots; BR-MEM-14 |
| I6 | The edition is pinned and recorded | `quran_dataset_versions.edition` (**OD-026**) |
| I7 | Coordinates are absolute for both ends of an ayah | `page_start/line_start/page_end/line_end` |
| I8 | Layout/rendering data never enters the measurement tables | ADR-0009; BR-QRN-10 |

---

## 2. Canonical level: A+ (both ends)

```text
quran_dataset_versions  1 ──< quran_surahs
                        1 ──< quran_pages
                        1 ──< quran_ayahs
```

### 2.1 `quran_dataset_versions`

| Field | Type | Required | Purpose |
|---|---|---|---|
| id | bigint | ✓ | |
| source | varchar(32) | ✓ | `kfgqpc` |
| source_url | varchar(255) | ✓ | exact artifact URL |
| source_version | varchar(64) | ✓ | as published |
| **edition** | varchar(32) | ✓ | canonical value for Tahfeez: `madinah_1405h_hafs_uthmani` (OD-026 resolved) |
| source_format | varchar(8) | ✓ | csv/json/sql/xml/txt/xlsx |
| downloaded_at | timestamptz | ✓ | |
| imported_at | timestamptz | ✓ | |
| checksum_sha256 | char(64) | ✓ | of the raw artifact |
| validation_status | varchar(12) | ✓ | pending/passed/failed |
| validation_report | jsonb | ✓ | per-check results, page-break count, line-count distribution |
| is_active | boolean | ✓ | exactly one true (partial unique index) |
| notes | text | | |

`edition` is mandatory because page/line coordinates are edition-specific: the 1405 AH and 1441 AH prints break lines differently on ~37% of lines. Two datasets that disagree are not "wrong" — they are different editions, and mixing them silently changes grades.

### 2.2 `quran_surahs`

`(dataset_version_id, id)` · `name_ar` · `name_en` · `ayah_count` · `page_start` · `page_end`

Optional and nullable, populated only if the source provides them: `name_transliteration`, `revelation_place`.
`ayah_count` is the **canonical** count (7, 286, …), not `count + 2`. Decoration slots from third-party datasets must never reach this field.

### 2.3 `quran_pages`

`(dataset_version_id, page_number)` · `lines_count` · `line_offset` · `juz_number` (nullable) · `hizb_number` / `rub_number` (nullable)

- `lines_count` — the **physical page height**, taken from the edition specification: 15 on pages 3–604 and **8 on pages 1–2** for `madinah_1405h_hafs_uthmani` (verified on the real artifact). It is **not** derived from the lines that ayah records cover: 57 pages carry surah title/basmala decorations on 2 lines, so coverage-based counting reports 13 instead of 15 and overstates measurements taken there by up to 15%.
- `line_offset` — Σ previous `lines_count`; ordering/audit only, not a measurement basis.
- `juz_number` from `jozz`. `hizb`/`rub` are not in the official field list → nullable, never guessed.

### 2.4 `quran_ayahs`

| Field | Type | Purpose |
|---|---|---|
| dataset_version_id | bigint | part of PK |
| id | integer | global ayah id 1…6236, assigned deterministically at import |
| surah_id | smallint | |
| number_in_surah | smallint | from `aya_no` |
| text_uthmani | text | from `aya_text_unicode`, verbatim |
| page_start | smallint | from `page` |
| line_start | smallint | from `line_start` |
| **page_end** | smallint | normalised (§3) |
| **line_end** | smallint | normalised (§3) |
| juz_number | smallint null | from `jozz` |
| is_sajda | boolean | |

Constraints: PK `(dataset_version_id, id)`; `unique (dataset_version_id, surah_id, number_in_surah)`; `index (dataset_version_id, page_start, line_start)`; `check (line_start >= 1)`; `check (page_end > page_start or line_end >= line_start)`.

**Why both ends.** With only `page`, an ayah that crosses a page break is ambiguous: `line_end = 15` might mean "ends at the bottom of this page" or "continues onto the next". Storing `page_end` makes the range closed and measurable without inference. This is the difference between level A and A+.

---

## 3. Normalisation at import

The official row shape is `(page, line_start, line_end)`. Two encodings are possible (**OD-025**); the importer handles both:

```text
if line_end <= lines_count(page):
    page_end = page; line_end' = line_end
else:
    carry = line_end − lines_count(page)
    walk forward pages until carry is absorbed
    page_end, line_end' = resulting position
```

The count of normalised ayahs is written to `validation_report`. A non-zero count requires visual verification of a sample against the printed mushaf before activation.

---

## 4. Measurement

For a range covering pages `p1…pn`:

```text
covered(p)   = lines of page p inside the range
amount_pages = Σ covered(p) / lines_count(p)
amount_lines = Σ covered(p)
```

Properties:
- exact for mid-page starts and ends;
- correct on irregular pages (no fixed height);
- reduces to `lines / lines_count` only when covered pages share a height;
- computed with `bcmath`, stored as `numeric(8,4)`;
- deterministic: same dataset version + same range ⇒ same value, forever.

`achievement_pct = actual / required × 100`, uncapped; `weighted_score` is a separate 0–100 value. See `evaluation.md`.

---

## 5. What is stored, what is not

| Data | Stored in PostgreSQL? | Where it lives | Why |
|---|---|---|---|
| Surah names, ayah counts | ✓ | `quran_surahs` | small, queried |
| Ayah text (Uthmani) | ✓ | `quran_ayahs` | needed for search/validation |
| Ayah → page/line (both ends) | ✓ | `quran_ayahs` | measurement |
| Page line counts | ✓ | `quran_pages` | measurement |
| Raw source artifact | ✗ | object storage / `data/quran/` (git-ignored) | reproducible, licence-gated |
| Fonts | ✗ | app asset / CDN | separate licence, not data |
| Layout recipes (widths, gaps, scales, features) | ✗ | static asset, only if a reader ships | font- and edition-coupled, large, not queryable |
| Rendered page images | ✗ | never | the product is text-based |
| Word tokens (Level C) | ✗ (MVP) | would be `quran_words` if adopted | no MVP rule needs them |
| Stretch factors / kashida positions | ✗ | build artifact | rendering detail |

**Rule of thumb:** if a value is needed to answer a *business* question, it goes in the database. If it is needed to *draw* something, it is an asset.

Sizing: ~6,236 ayah rows + 604 page rows + 114 surah rows per version (≈1–2 MB of text). Three retained versions remain trivial; the table grows by edition, not by usage.

---

## 6. Source mapping

### 6.1 From KFGQPC (the only production path)

| Official field | Internal | Transform |
|---|---|---|
| `sura_no` | `surah_id` | direct |
| `aya_no` | `number_in_surah` | direct |
| `aya_text_unicode` | `text_uthmani` | verbatim |
| `page` | `page_start` | direct |
| `line_start` | `line_start` | direct |
| `line_end` | `line_end` → normalised | §3 |
| `jozz` | `juz_number` | direct |
| — | `id` | global 1…6236 in mushaf order |
| — | `page_end` | derived (§3) |
| `sura_name_ar` / `sura_name_en` | `quran_surahs` | deduplicated from flat rows |

### 6.2 From a third-party layout dataset (not permitted in production)

Documented only so a future deviation is explicit and testable. If ever used:

- `internal_aya_index − 1 = ayah_no`; **drop** internal indices 0 (title) and 1 (basmala);
- `line_start = min(part.l)`, `line_end = max(part.l)`; reject `l > lines_count(page)`;
- derive `lines_count` from distinct `l` per page;
- **reject the import** if any ayah's parts imply a page change (the model cannot express it);
- record `source = <project>` and `edition` accordingly.

Requires an ADR and a licence clearance. It is not a configuration option.

---

## 7. Future extensions (specified, not built)

### 7.1 Level C — words

Trigger: a product requirement for word-level ranges or word highlighting.

```text
quran_words (dataset_version_id, id, ayah_id, position_in_ayah, text, page, line, position_in_line)
```

Constraints to pin before building: basmala counted as 4 words (or not), sura names excluded, At-Tawba has none, Al-Fatiha's basmala is verse 1. These conventions differ between implementations and must be written down, not inherited.

Additive only: no existing table changes, and measurement continues to use lines.

### 7.2 Level D — reader layout

Trigger: a funded requirement for a printed-page reader.

- Separate static asset, versioned with its own `content_hash`.
- Never joined into `quran_ayahs`; the measurement path must not import it.
- Licence cleared per asset class (data, fonts) before bundling.

---

## 8. Acceptance criteria for any dataset

A dataset may be activated only if all hold:

1. Validation gate V-01…V-12 passed (`quran-validation-plan.md`).
2. `edition` recorded and matches the print the center actually uses.
3. Provenance record complete (`quran-source-record.md`), including licence terms.
4. Page-break ayah count recorded and, if non-zero, visually verified.
5. `lines_count` distribution recorded (uniformity is reported, not assumed).
6. Golden fixtures regenerate identically from the artifact.
7. Activation is a separate, audited action.

---

## 9. Anti-requirements (explicitly out)

- No `15` constant anywhere in code, schema, fixtures or tests.
- No user-editable Quran text, in any role.
- No layout data in measurement tables.
- No ayah renumbering, no "simplified" ayah counts, no decoration slots.
- No silent dataset upgrade.
- No derived value stored without its source version.
