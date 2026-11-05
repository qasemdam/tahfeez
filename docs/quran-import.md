# Quran dataset import

**How a dataset becomes a snapshot in the `quran_*` tables — and why it is never
activated by the import itself.** Phase 7, S3.

The import engine validates first and writes second. Nothing here decides
*whether* a dataset may be used: that is the activation step, and the dataset
gate (OD-029) still governs which source is acceptable.

---

## 1. The command

```bash
php artisan quran:import {path} --edition=... [--format=json] [--source=...]
                          [--source-version=...] [--source-url=...] [--notes=...]
```

| Option | Required | Meaning |
|---|---|---|
| `path` | yes | The file to import |
| `--edition` | **yes** | Edition identifier, e.g. `madinah_1405h_hafs_uthmani`. No default: coordinates are edition-specific |
| `--format` | no (json) | Which adapter reads the file; see `config/quran.php` → `import.sources` |
| `--source` | no (file) | Source identifier stored on the snapshot (`kfgqpc`, `qul`, …) |
| `--source-version` | no | Version string as published; falls back to the file's own `source_version` |
| `--source-url` | no | Where the file came from |
| `--notes` | no | Free-form note stored with the snapshot |

Example:

```bash
php artisan quran:import storage/app/quran/dataset.json \
    --edition=madinah_1405h_hafs_uthmani \
    --source=kfgqpc \
    --source-version=v3.0 \
    --source-url=https://example.org/dataset.zip \
    --notes="structural check only"
```

Exit codes: `0` the dataset passed and was stored as an inactive snapshot ·
`1` the file could not be read, the format is unknown, or a check failed.

The command prints one row per check (`V-01`…`V-15`), then the checksum, the
counts and the snapshot id.

## 2. What the command does

```text
hash → duplicate guard → parse → re-hash → validate → write
```

1. **SHA-256** of the file, stored on the snapshot.
2. **Duplicate guard**: a file whose checksum already belongs to a `passed` or
   `incomplete` snapshot is refused — the message names the existing version.
   Re-importing a *failed* file is allowed, because the bytes that failed are not
   a dataset. A partial unique index on `checksum_sha256` (where the status is
   `passed` or `incomplete`) backs this up: the application check is a
   check-then-insert, and two concurrent imports of the same bytes could both
   pass it. The index is what actually stops the second one; the conflict is
   reported with the same message.
3. **Parse** through the adapter for `--format`. A parse error writes nothing.
4. **Re-hash** (V-11) to catch a file that changed while it was being read.
5. **Validate** (see §4). All checks run in memory; no row is written yet.
6. **Write**, in one transaction: the snapshot row, then surahs, pages and ayahs
   in chunks of 500, then the status flips from `pending` to the verdict — see
   §5.

A storage failure is reported as a reference id, never as the driver message:
`QueryException` embeds the statement and its bound values, and for `quran_ayahs`
those values are the text of the ayahs. The log records the exception class and
the same reference, nothing more.

A dataset that fails validation is stored as a snapshot with
`validation_status = failed` and **no children** — an audit trail that
`DatabaseQuranDataProvider` will never read (it requires `passed`).

Nothing in this path touches an existing snapshot: no update, no delete, no
re-parenting. Each import is a new `dataset_version_id`.

**The snapshot is never activated.** `is_active` is written as `false` and stays
that way until someone runs an explicit activation step. Until then the provider
keeps answering from whatever was active before.

## 3. The file format (v1: canonical JSON)

Only the neutral Tahfeez format is supported today. A vendor file (KFGQPC CSV,
a SQL dump, a third-party layout database) needs an **adapter** that produces the
same `DatasetPayload`; validation and persistence never learn vendor quirks.

```json
{
  "edition": "madinah_1405h_hafs_uthmani",
  "source_version": "v1",
  "surahs": [
    { "id": 1, "name_ar": "…", "name_en": "…", "ayah_count": 7,
      "page_start": 1, "page_end": 1,
      "name_transliteration": null, "revelation_place": "makkah" }
  ],
  "pages": [
    { "page_number": 1, "lines_count": 8, "line_offset": 0,
      "juz_number": 1, "hizb_number": null, "rub_number": null }
  ],
  "ayahs": [
    { "id": 1, "surah_id": 1, "number_in_surah": 1, "text_uthmani": "…",
      "page_start": 1, "line_start": 1, "page_end": 1, "line_end": 1,
      "juz_number": 1, "is_sajda": false }
  ]
}
```

- `line_offset` is optional; it is the running sum of the previous page heights
  and is computed when absent. It is an ordering/audit field — **never** a
  measurement basis.
- `lines_count` is **mandatory**. It is the physical height of the page as
  published by the edition, and it is the denominator of every measurement.
  Nothing derives it from the lines the ayah rows happen to cover.
- Optional fields (`name_transliteration`, `revelation_place`, `juz_number`,
  `hizb_number`, `rub_number`, `is_sajda`) may be omitted.

## 4. Checks

Blocking checks — any failure aborts the import.

| ID | Check |
|---|---|
| V-01 | 114 surahs, numbered 1…114, no gaps, no duplicates |
| V-02 | 6,236 ayahs in total; each surah's declared `ayah_count` equals the rows it has; declared counts sum to 6,236 |
| V-03 | 604 pages, numbered 1…604, no gaps, no duplicates |
| V-04 | Every ayah starts inside page 1…604; start pages are non-decreasing in mushaf order |
| V-05 | `1 ≤ line_start ≤ lines_count(page_start)`, `1 ≤ line_end ≤ lines_count(page_end)`, `page_end ≥ page_start` |
| V-06 | Global ayah ids form 1…6,236 with no gaps |
| V-07 | No duplicate ayah id and no duplicate `(surah, number_in_surah)` |
| V-08 | Ayah order matches `(surah, number)` order; surah start pages are non-decreasing |
| V-09 | Ayah text is non-empty and contains no Latin letters; surah names are non-empty |
| V-11 | The file's SHA-256 is unchanged between the first hash and the write |
| V-13 | No orphan: every ayah points at a surah in the dataset and at a page that exists |
| V-14 | No required field is empty; `lines_count ≥ 1`; `ayah_count ≥ 1`; page numbers ≥ 1 |
| V-15 | Page heights match the documented profile of the declared edition (8 lines on pages 1–2, 15 elsewhere for `madinah_1405h_hafs_uthmani`) |
| V-16 | **Range order.** Each ayah ends at or after it starts, and starts at or after the previous ayah ends (compared as `(page, line)`). Sharing a line is allowed; overlapping or running backwards is not |
| V-17 | **Surah page range.** `page_end ≥ page_start`, and the range contains every page its own ayahs occupy |

**Why V-16 exists.** Without it a single row can claim the whole mushaf: an ayah
starting at 1:1 and ending at 604:8 passes every other check, and `measure()`
then returns 603 pages for one ayah. V-16 is what makes a measurement
meaningful, and it imposes no fixed page span — a "one page" ceiling would be a
guess about the print, not a fact about the data.

**Why V-17 stops at containment.** Whether a surah's first page is the page of
its first ayah or a preceding header page is a convention of the source, which
is unverified (OD-029). Containment is provable from the dataset; equality is
not asserted.

Recorded but not blocking:

| ID | Status | Why |
|---|---|---|
| V-10 | **skipped** | Deterministic sample comparison needs a checked-in golden sample; the repository has none yet (OD-029) |
| V-12 | **skipped** | Golden boundary fixtures, same reason |

Skipped is not passed: the reason is stored with the check, the command prints a
warning, and — because both are listed as *critical* — the snapshot is stored as
`incomplete` rather than `passed`. See §5.

`V-15` validates a *declaration*; it never supplies one. The dataset must still
state `lines_count` for every page, and the measurement path reads that value.

## 5. Statuses

A snapshot carries one of four values in `validation_status`:

| Status | Meaning | Served by `DatabaseQuranDataProvider`? |
|---|---|---|
| `pending` | the row exists inside the running transaction | no |
| `passed` | every check ran and none failed | **yes** |
| `incomplete` | nothing failed, but a **critical** check was skipped | no |
| `failed` | at least one check failed; no children were written | no |

`incomplete` exists because "no failures" is not the same as "verified". A
dataset that parsed and passed every structural check but skipped the
golden-sample comparison is real data with an unproven guarantee — it is stored
so the work is not lost and the attempt is auditable, but it is never served and
must never be activated.

Which checks are critical is declared in `config/quran.php` →
`import.critical_checks`. Today that list includes V-10 and V-12 (the
golden-sample checks), which are always skipped because no fixture is checked
in. **Consequence: no dataset can reach `passed` until a golden sample exists,
or until that list is changed as an explicit product decision.** That is the
honest state under OD-029, not a bug.

### Report contents

`validation_report` stores:

```json
{
  "passed": false,
  "status": "incomplete",
  "checks": [{"id": "V-01", "label": "surah count", "status": "passed", "detail": "…"}],
  "stats": {
    "surahs": 114, "pages": 604, "ayahs": 6236,
    "pages_without_ayahs": 0,
    "ayahs_per_page": {"min": 3, "max": 22},
    "max_ayah_page_span": 2,
    "line_height_distribution": {"8": 2, "15": 602}
  }
}
```

Every check keeps its id, label, status and reason. `stats` is derived from the
dataset itself — no reference figures — so it can be recorded without asserting
anything about the print. `pages_without_ayahs` is reported, never enforced:
whether every page of the edition carries ayahs is a property of the source.

## 6. Adding a source adapter

1. Implement `App\Domain\Quran\Import\QuranSource::read(string $path): DatasetPayload`.
2. Map the vendor's fields to the column shape in §3 — including
   `lines_count` from the vendor's own page table, never from ayah coverage.
3. Register it in `config/quran.php` under `import.sources.<format>`.
4. Add a fixture-based test; do not commit the vendor artifact (`.gitignore`
   excludes `data/quran/*`).

**KFGQPC is not implemented.** Its published artifact is the 1441H layout, not
the 1405H print this product targets, and its licence is unresolved (OD-029).
Writing an adapter before that is settled would encode the wrong edition.

## 7. Activation

Not implemented in S3 and deliberately not part of `quran:import`. When it
arrives it must: require `validation_status = passed`, keep exactly one active
snapshot (the partial unique index enforces it), and never mutate a snapshot —
activating a different dataset is a switch, not an edit.
