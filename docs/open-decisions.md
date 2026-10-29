# Tahfeez — Open Decisions Register

Status: **Review round 1 applied — 2026-10-28** · Phase 0
Rule §22: an undefined rule is **not invented**. It is recorded here, with options and a recommendation, and blocked until decided.

**How to read the "Blocks" column:** `Phase n` means the decision must be made before that phase starts. `—` means it can be decided later without rework.
**Status:** `Open` · `Resolved` (decided, applied to docs) · `Partial` (part decided, a named gate remains).

---

## 0. Summary

| ID | Decision | Status | Blocks |
|---|---|---|---|
| OD-001 | Age: date of birth vs static age | Open | Phase 3 |
| OD-002 | Which day starts the week | **Resolved 2026-10-28** | — |
| OD-003 | Hijri or Gregorian calendar | **Resolved 2026-10-28** | — |
| OD-004 | Rounding of measured pages | Open | Phase 6 |
| OD-005 | Quran source & licensing | **Partial** — source resolved; 2 licensing blockers remain | bundling only |
| OD-006 | Recitation: standalone or attached | Open | Phase 4 |
| OD-007 | Attendance granularity: day or session | Open | Phase 5 |
| OD-008 | Score caps | **Resolved 2026-10-28** | — |
| OD-009 | Missing-data default policy | Open | Phase 6 |
| OD-010 | Cross-surah ranges allowed | Open (recommend yes) | Phase 4 |
| OD-011 | Where `required_pages` is configured | Open | Phase 6 |
| OD-012 | Encrypt local mobile DB | Open | Phase 8 |
| OD-013 | Token lifetimes | Open | Phase 1 |
| OD-014 | Ranking tie-break rule | Open | Phase 7 |
| OD-015 | Who publishes templates | Open | Phase 6 |
| OD-016 | Grade level: free text or lookup | Open | Phase 3 |
| OD-017 | Column-level encryption for PII | Open | Phase 9 |
| OD-018 | Framework/dependency versions | Open | **Phase 1** |
| OD-019 | `change_log` retention window | Open | Phase 8 |
| OD-020 | Data residency / erasure legal basis | Open | Phase 9 |
| OD-021 | Multi-center teacher membership | Open | Phase 3 |
| OD-022 | Deleting a record referenced by an approved evaluation | Open | Phase 6 |
| OD-023 | Offline permission changes | Open | Phase 8 |
| OD-024 | Branding of exported PDFs | Open | Phase 7 |
| OD-025 | Page-break ayah encoding in the official dataset | **Resolved 2026-10-29** — none present, no truncation | — |
| OD-026 | Reference edition (Madinah 1405H / Hafs / Uthmani / 604 / 15) | **Resolved 2026-10-29** | — |
| OD-027 | Word-level data (level C) for the future reader | Open — deferred | post-MVP |
| OD-028 | Reader layout strategy (if a reader is funded) | Open — deferred | post-MVP |
| OD-029 | **Edition conflict**: canonical 1405H vs the only official artifact (1441H) | **Open / Blocked** — next: written inquiry to KFGQPC | Phase 2 import |

**Resolved in review round 1:** OD-002, OD-003, OD-008 fully; OD-005 partially. Their entries below are kept with the decision text and rationale, not deleted — the reasoning is part of the record.

---

## OD-001 — Age representation

**Question.** The spec lists "age" as student data. Age changes over time; a stored number becomes wrong within a year.

**Options.**
1. `birth_date` only — accurate, requires the guardian to know it.
2. `age_years` only — matches the paper form, decays over time.
3. Both (birth_date preferred, age as fallback).

**Recommendation:** option 3. `age_years` is captured as "age at enrollment" and displayed as such; when `birth_date` exists it wins. Reports that need age compute it at the report date.

**Risk if wrong:** age-based grouping and reports drift by up to 12 months per student.

---

## OD-002 — Week start day · **RESOLVED 2026-10-28**

**Decision.** The weekly evaluation start day is a **center-level setting**, never a universal business rule.

```text
centers.weekly_evaluation_start_day                     enum WeekStartDay { saturday … friday }
centers.weekly_evaluation_start_day_effective_from      date
```

- Represented by an enum/configuration in both codebases — no literal day and no `day == 6` in evaluation logic.
- The current center is configured with `saturday`. That is a configured value, not a hard-coded assumption.
- Changing the setting MUST NOT recalculate or mutate historical weekly evaluations. Each row keeps its own `week_start` / `week_end` / `week_start_day`.
- The change applies from `effective_from`; a change that would overlap an already-computed period is rejected.

**Applied to:** `business-rules.md` BR-TEN-04, BR-TEN-05, BR-EVL-16 · `evaluation.md` §5.1 · `database.md` `centers`, `weekly_evaluations` · `architecture.md` §10.

---

## OD-003 — Calendar system · **RESOLVED 2026-10-28**

**Decision.** Gregorian is canonical. Hijri is a display concern only.

- All stored dates, period keys and report parameters are Gregorian.
- Hijri dates may be shown alongside Gregorian where useful (parent reports, matching paper registers), computed at render time.
- A Hijri date is never a storage key, a join key, or the basis of a week/month boundary.

**Rationale:** Gregorian is unambiguous across timezones and across libraries; Hijri month lengths vary by convention and would make period boundaries non-deterministic — which conflicts with §21.

**Applied to:** `business-rules.md` BR-TEN-06 · `evaluation.md` §5.1 · `api.md` date conventions.

---

## OD-004 — Rounding of measured pages

**Question.** Line-based measurement yields values like `1.2000` or `2.4667`. Teachers think in halves and quarters.

**Options.**
1. `exact` — use `1.2000` as-is (default).
2. Round to nearest 0.25.
3. Round to nearest 0.5.

**Recommendation:** option 1 for storage and evaluation; option 3 optionally for *display* only. The stored value never changes; the evaluation input policy is explicit per center and recorded in the snapshot.

**Risk if wrong:** a student at 1.2 pages displays as 1.0 and the parent disputes the report. Whatever is chosen must be visible in the UI ("1.20 صفحة").

---

## OD-005 — Quran source & licensing · **PARTIAL** (source resolved 2026-10-28)

**Resolved — data availability and source of truth.**
The **KFGQPC official developer dataset** (<https://qurancomplex.gov.sa/quran-dev/>) is the primary and authoritative source, available in CSV, JSON, SQL, XML, TXT and Excel, with Hafs/Uthmanic Madinah-Mushaf data exposing `page`, `line_start`, `line_end`, `sura_no`, `aya_no`, `aya_text_unicode`, `sura_name_ar`, `sura_name_en`, `jozz`.

Priority: KFGQPC (primary) → Tanzil (cross-validation only) → anything else (requires an ADR).

**Remaining gate — licensing/usage verification (blocking for bundling only).**
No permission is assumed. Before the dataset is committed to the repository or shipped inside an app binary, `quran-source-record.md` must document: source URL, dataset/version, download date, SHA-256, attribution requirements, and the licence/usage terms **as provided by the source**, reviewed and signed off. Font (Uthmani) licensing is a separate line item.

Until then: development uses a locally fetched artifact referenced by path and hash; nothing is bundled or published.

**Added 2026-10-28 — licensing findings from the layout-project study.** Two blockers, both avoidable because KFGQPC supplies the coordinates directly:

- **BLOCKER-01 — licence conflict on layout data.** `quran-madina-html` licenses *code and assets* under **Waqf GPL 2.0** (clause seven: modified redistributions must remain under that licence). `quran-madina-layout` claims **MIT** and states its datasets are "derived from quran-madina-html (MIT License)", while shipping what appears to be the same v1 database (identical filename `Madina05-Hafs-24px.json`, 1,924,045 vs 1,924,046 bytes). Do not rely on the MIT claim. Treat that data as Waqf GPL 2.0 until resolved in writing. **Resolution: we do not use it** (ADR-0009).
- **BLOCKER-02 — font licensing.** `quran-madina-layout` bundles KFGQPC typefaces (`Hafs.ttf`, `UthmanTN_v2-0.ttf`) with **no licence file**. No font may be bundled until its terms are recorded; prefer an OFL-1.1 font with the notice shipped.

**Applied to:** `quran-data.md` §2, §12 · `quran-source-record.md` · `business-rules.md` BR-QRN-07, BR-QRN-08, BR-QRN-10, BR-QRN-11 · ADR-0008, ADR-0009 · `quran-data-sources-comparison.md` §8.

---

## OD-006 — Recitation as a standalone record

**Question.** §14 describes recitation assessment, §17 lists a separate recitation report, but the relationship to memorization/review is not specified.

**Options.**
1. Standalone `recitations` table, optionally linked to a memorization/review row.
2. Embedded fields inside memorization only.

**Recommendation:** option 1. It supports "student recited old material today" without inventing a fake memorization record, and keeps the separate report honest.

---

## OD-007 — Attendance granularity

**Question.** One attendance row per student per day, or per session (morning/evening)?

**Options.** Per day · per session.

**Recommendation:** per day in MVP, enforced by `unique (center_id, student_id, attendance_date)`. Adding sessions later means adding `session_id` to the unique key — an additive migration, but it changes report semantics, so decide now if centers run two shifts.

---

## OD-008 — Score caps · **RESOLVED 2026-10-28**

**Decision.** The two evaluation values live on different scales and are never mixed.

| | Basic achievement | Advanced score |
|---|---|---|
| Column | `achievement_pct` | `weighted_score` |
| Range | **uncapped** (250%, 300%) | **0–100**, normalised |
| Changes when | actual/required changes | weights, criteria or thresholds change |

- `achievement_pct` is never clamped in storage; a display cap is a presentation choice.
- `weighted_score` is clamped to `0…100` after aggregation.
- Neither value is ever averaged, combined, substituted or written into the other's column.

**Applied to:** `business-rules.md` BR-EVL-13, BR-EVL-15 · `evaluation.md` §1, §3.3 · `database.md` `weekly_evaluations`.

---

## OD-009 — Missing data

**Question.** A week with no recitation at all: score 0, exclude the criterion, or refuse to grade?

**Options.** `treat_as_zero` · `exclude_and_renormalize` · `block`.

**Recommendation:** `block` for `recitation_quality` (a missing assessment is not a bad assessment), `treat_as_zero` for `memorization_pages` (not memorizing is a real zero). Both are stored per criterion and visible in the snapshot.

---

## OD-010 — Cross-surah ranges

**Question.** A student finishes Al-Baqarah and starts Al-Imran in the same session. One record or two?

**Options.** One record spanning surahs · force two records.

**Recommendation:** allow one record. Ayah ids are global (`1…6236`), so the range is well-defined; `surah_id` stores the start surah and the end surah is derived. Forcing two records would understate a real session.

---

## OD-011 — Where `required_pages` lives

**Question.** §11 fixes the default at 1 page, but does not say who may change it or at what level.

**Options.** Global constant · center setting · per-student profile version.

**Recommendation:** per-student profile version, defaulting from the center setting (1.00). This is already the design; the decision needed is whether a teacher may change a student's requirement or only a manager. Recommendation: manager, or teacher with `evaluation.profile.change`.

---

## OD-012 — Local database encryption

**Question.** The device holds children's names and guardians' phone numbers in plain SQLite by default.

**Options.** Plain SQLite · SQLCipher with key in Keychain/Keystore · platform file encryption only.

**Recommendation:** SQLCipher. Cost is a build dependency and a small performance hit; the data class justifies it.

---

## OD-013 — Token lifetimes

**Question.** Balance between "teacher stays logged in all week" and "stolen phone".

**Recommendation:** 30-day access token on mobile with device binding and remote revocation; 12-hour on web. Refresh rotates the token. Revisit after real usage data.

---

## OD-014 — Ranking tie-break

**Question.** Two students both at 100%. Who is first?

**Options.** Student id · name · error count · last record date.

**Recommendation:** `achievement_pct DESC, total_errors ASC, student_id ASC`. Deterministic, and "fewer mistakes" is a defensible second criterion. It must be documented in the report footer, otherwise rankings look arbitrary.

---

## OD-015 — Template publishing authority

**Question.** §12 says the teacher defines criteria and weights; §20 requires authorization. Who is allowed to make a template official?

**Options.** Teacher publishes directly · teacher drafts, manager publishes · manager only.

**Recommendation:** teacher may create/edit drafts (if granted), manager publishes. Publishing changes how every student in scope is graded, so it deserves a second pair of eyes.

---

## OD-016 — Grade level representation

**Question.** "الصف الدراسي" could be "Grade 4", "حلقة ٣", or a center-specific level.

**Recommendation:** free text in MVP with a suggested-values picker; promote to a lookup table when centers ask for filtering by level. Do not invent a fixed enum.

---

## OD-017 — PII column encryption

**Question.** Should names/phones be encrypted at the column level?

**Recommendation:** not in MVP. Column encryption breaks `LIKE` search, sorting and reporting, and the threat it addresses (DB dump) is already covered by disk encryption + access control + audit. Revisit if a center requires it contractually.

---

## OD-018 — Dependency versions

**Question.** Which exact Laravel / Flutter / Drift / Riverpod versions to pin.

**Recommendation:** resolve in Phase 1 from the actual environment, then pin in `composer.lock` / `pubspec.lock` and record the versions in `docs/decisions/`. Do not write version numbers into architecture docs before they are installed — they will be wrong.

**Blocking:** Phase 1.

---

## OD-019 — Change-log retention

**Question.** How long the sync feed is kept before a client must full-resync.

**Recommendation:** 90 days. A teacher offline for a whole term is not a supported scenario; 90 days covers holidays and device replacement.

---

## OD-020 — Data residency and erasure

**Question.** Where data is hosted and on what legal basis deletion requests are honoured.

**Recommendation:** an organizational policy decision (hosting region, DPA, retention), documented in `docs/decisions/`. The code provides an audited purge tool; it cannot decide the law.

---

## OD-021 — Multi-center teachers

**Question.** Can one user teach in two centers?

**Recommendation:** no in MVP. `users.center_id` is a single FK; supporting multi-center means a membership table and reworking every scope. Decide before onboarding a shared teacher, because the migration is not free.

---

## OD-022 — Deleting records referenced by an approved evaluation

**Question.** A teacher deletes a memorization row from a week that was already approved.

**Recommendation:** soft delete allowed for the author/manager, but the approved evaluation is **not** silently rewritten; a recompute proposal is created (BR-MEM-12). If the manager rejects it, the record stays deleted but the historical grade remains as approved — and the audit log explains the discrepancy.

---

## OD-023 — Offline permission changes

**Question.** May a manager create a teacher or change permissions without connectivity?

**Recommendation:** no. Authorization changes must be server-observed at the moment they happen; allowing them offline creates a window where a revoked teacher still records grades.

---

## OD-024 — Export branding

**Question.** Do PDF reports carry the center's logo and colours?

**Recommendation:** neutral template with center name, logo slot and Arabic/Latin fonts; per-center theming is a Future Feature. Keep the export pipeline deterministic — no client-side styling.

---

## OD-025 — Page-break ayah encoding · **RESOLVED 2026-10-29**

**Question was.** Does the KFGQPC row shape (`page` + `line_start`/`line_end`) let an ayah continue past the bottom of its page, or does it clamp/split?

**Answer, measured on the real artifact** (`kfgqpc_hafs_v30`, SHA-256 `227e6b15…f245`, 6,236 rows):

| Test | Result |
|---|---|
| Rows with `line_end > 15` (overflow encoding) | **0** |
| Same `(sura, aya)` on two pages (split encoding) | **0** |
| Longest ayah 2:282 (1,193 chars) | page 48, lines 1–15 — exactly one page |
| chars per covered line | median 52 · p99 92 · **max 108** |
| Ayahs above 120 chars/line | **0** |

**Conclusion:** in this edition every ayah is contained within a single page — page breaks fall on ayah boundaries. Nothing is truncated: a clamped ayah would show a chars-per-line spike far above the distribution, and none exists.

**Residual risk, kept on purpose.** The schema still cannot *express* a spanning ayah, and absence was proven statistically, not structurally. Therefore A+ (`page_start/line_start/page_end/line_end`) remains the model, so a future edition with a spanning ayah is representable without a schema change.

**Applied to:** `quran-data.md` §6 · `quran-data-model.md` §2.4 · `quran-validation-report.md` §6 · `business-rules.md` BR-QRN-12.

---

## OD-026 — Reference edition · **RESOLVED 2026-10-29**

**Decision.** The canonical edition of Tahfeez is fixed and explicit — not "the Madinah Mushaf" in general:

| Attribute | Value |
|---|---|
| Mushaf | مصحف المدينة النبوية — Madinah Mushaf |
| Edition | **1405 AH** (الطبعة القديمة) |
| Riwayah | Hafs ʿan ʿAsim |
| Script | Uthmani (الرسم العثماني) |
| Pages | 604 |
| Lines per page | **15** — a *property of this edition*, not a formula |

Canonical identifier: **`madinah_1405h_hafs_uthmani`** — stored in `quran_dataset_versions.edition`.

**The 15-line fact is descriptive, never computational.** The measurement engine MUST NOT contain `covered_lines / 15`. It divides by `lines_count(page)` read from the imported dataset, which happens to be 15 on every page of this edition. If a future edition has 14 or 16 lines on some pages, the formula must still be correct without a code change (BR-QRN-09, ADR-0003).

**Consequences.**

- The edition is part of the dataset identity; two datasets are comparable only if the identifier matches.
- Switching edition (e.g. to 1441 AH) creates a new dataset version. It never rewrites existing records, and amounts measured under different editions are not comparable (BR-QRN-10).
- Reports must state the edition they were measured under.
- **Verification requirement (VR-EDITION):** the edition must be *proved from the artifact*, not assumed from the fact that it is "official". If the artifact carries no metadata identifying the edition, the gap is recorded as a verification requirement and the artifact is not activated on the strength of its name alone.

**Verification outcome (2026-10-29) — the decision stands, the artifact does not match.**
The only official KFGQPC Hafs artifact available (`kfgqpc_hafs_v30`, 6,236 rows, SHA-256 `227e6b15…f245`) declares **no edition**, and its line breaks match the **1441 AH** layout 100% on juz 30 (564 ayahs) while matching the 1405 AH layout on only 20.9% of line spans. It therefore **cannot** serve as the canonical 1405H dataset. `validation_status = failed`; no snapshot was created. Resolution requires **OD-029**.

**Applied to:** `quran-data.md` §2, §3 · `quran-data-model.md` §2.1 · `quran-data-sources-comparison.md` · `quran-validation-plan.md` §2 (S-04) · `database.md` · `business-rules.md` BR-QRN-10, BR-QRN-13 · `roadmap.md` Phase 2 · `testing.md` · ADR-0003, ADR-0008 · `quran-validation-report.md`.

---

## OD-027 — Word-level data (level C) · **OPEN — DEFERRED**

**Question.** Do we ever need `ayah → word → page/line/position`?

**Trigger for building it:** word-level highlighting in a reader, or a product decision that a hifz range may start/end mid-ayah at a word boundary. Neither exists in the MVP: §9 defines ranges by ayah.

**Recommendation:** do not build now. When triggered, add `quran_words` keyed by `dataset_version_id` — purely additive, no change to measurement tables. Before building, pin the conventions that differ between implementations: basmala counted as 4 words (or not), sura names excluded, At-Tawba has none, Al-Fatiha's basmala is verse 1.

**Blocks:** nothing in MVP.

---

## OD-028 — Reader layout strategy · **OPEN — DEFERRED**

**Question.** If a printed-page reader is funded later, where does the layout come from?

**Options.**
1. Build in-house: OFL-1.1 Uthmani font + line breaks derived from our own licensed dataset.
2. Adopt a Waqf-GPL layout dataset and comply with its copyleft terms.
3. License a commercial layout.

**Recommendation:** option 1. It keeps the licence surface clean, avoids a font dependency we do not control, and reuses the coordinates we already validate. Option 2 would force a copyleft obligation onto a shipped asset and is incompatible with a proprietary product unless isolated and disclosed.

**Blocks:** post-MVP. Requires its own ADR and a per-asset licence clearance before any bundling.

---

## OD-029 — Edition conflict: canonical 1405H vs the available official artifact · **OPEN — BLOCKED**

| Field | Value |
|---|---|
| **Status** | **Open / Blocked** |
| **Reason** | No published artifact combines **the right edition** with **usable licensing**. KFGQPC publishes only the 1441H dataset (wrong edition). QUL's V1 layout *is* the 1405 print, but it carries no licence, no provenance, and no ayah column |
| **Next action** | (a) official written inquiry to KFGQPC — package ready, not sent; (b) licensing inquiry to QUL/Tarteel for the V1 layout data |
| **Constraint** | 1441H MUST NOT be adopted as a substitute without a **new product decision**. Third-party sources are not accepted. Building the coordinates in-house is out of scope |
| **Blocks** | Phase 2 production import |

**Situation.** OD-026 pinned the canonical edition to **1405 AH**. The only official KFGQPC Hafs artifact reachable from the developer platform is `kfgqpc_hafs_v30`, and it does **not** describe that print:

| Test (juz 30, 564 ayahs) | vs 1405H layout | vs 1441H layout |
|---|---|---|
| Same page | 92.4% | **100.0%** |
| Same line span | 20.9% | **100.0%** |

Both references come from one project and one pipeline, so tooling cannot explain the gap. The artifact also carries **no edition metadata** in any of its seven formats — the edition can only be established by comparison, and the comparison says 1441H.

The **text, riwayah and script are unaffected**: this is purely a page/line coordinate question. What changes is how much a given ayah range "weighs" in pages.

**Options.**

| # | Option | Cost | Consequence |
|---|---|---|---|
| A | **Change the canonical edition to 1441H** and adopt this artifact | lowest | Reverses OD-026; every amount is measured against the current print; must be approved explicitly, not assumed |
| B | **Keep 1405H**, obtain a 1405H artifact from KFGQPC | medium | Requires confirming that KFGQPC publishes 1405H developer data at all — the platform currently exposes one Hafs dataset, not a per-edition choice |
| C | **Keep 1405H**, build the coordinate table in-house from a licensed 1405H source | highest | Months of typography work; needs its own validation; duplicates what KFGQPC already does for 1441H |

**Recommendation.** Ask KFGQPC directly (option B) before paying for C. If 1405H data is not published, the choice is between A (cheap, changes the reference print) and C (expensive, preserves the decision). Do **not** silently adopt A: the print a center uses is a product fact, not an implementation detail — teachers compare the app against the physical mushaf in their hands.

**Blocks:** Phase 2 import. Until resolved, no dataset can be activated and `validation_status` stays `failed` for the canonical edition.

### Research outcome (2026-10-29) — narrowed, not resolved

An official-channel investigation was run (`quran-1405h-sourcing.md`). Result: **KFGQPC publishes no 1405H page/line dataset.**

- The developer platform exposes **one** Hafs dataset and **no edition selector**; the strings "1405"/"1441" do not appear anywhere on it (checked with Latin and Arabic-Indic digits).
- The artifact carries **no edition metadata** in any of its seven formats.
- The only "1405" on the official site is the complex's **founding/distribution year**, not a dataset.
- The download host forbids directory browsing (403), so unpublished artifacts cannot be discovered.

Option B therefore changes character: it is **not a download**, it is a **request**. Official channels exist (`info@qurancomplex.gov.sa`, `mrkt@qurancomplex.gov.sa`, contact form) and a request template with the exact technical specification is prepared in `quran-1405h-sourcing.md` §8.

**Status: Open — blocked on an external response.** Not resolvable from public sources. One gap remains to check manually: `od.data.gov.sa` was unreachable from this environment.

**Prepared (2026-10-29):** the request package is ready in `quran-1405h-sourcing.md` — formal Arabic letter, short email, technical annex with the required fields, and eleven explicit licensing questions. It has **not** been sent; sending is a human action.

### QUL evaluation (2026-10-29) — edition solved, licensing not

QUL's `qpc-v1-15-lines.db` was downloaded and validated (`quran-1405h-qul-verification.md`).

| Finding | Detail |
|---|---|
| **Edition** | **Verified as the 1405 print** — 100.0% same page / 92.9% same line span vs the 1405H layout, against 92.4% / 21.5% for 1441H; plus 604 pages, 8-line opening pages, 112 basmala lines |
| **Shape** | Line-oriented: `pages(page_number, line_number, line_type, first_word_id, last_word_id, surah_number)`. **No ayah column** |
| **Ayah coordinates** | **Not derivable standalone.** Word ids run 1…83,668 with zero gaps, but mapping them to ayahs requires QUL's word table; a candidate mapping (total − token count = exactly 6,236) drifts mid-Quran and produces implausible results |
| **Licence** | **None.** No LICENSE in the mirror repo; QUL's terms link to Tarteel's consumer ToS; QUL states most resources are community-created, not authored by Tarteel |
| **Provenance** | Unofficial mirror of a community transcription of the print — no publisher attestation, no version, no official download to compare |

**Disposition:** not accepted as master data. Retained as a **structural cross-check** (page heights, line counts), the same role as `quran-madina-html`. OD-029 remains **Open / Blocked**, now with two candidate sources and two open inquiries.
