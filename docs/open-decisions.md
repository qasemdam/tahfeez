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
| OD-030 | Groups (حلقات) schema and teacher-assignment coupling | **Resolved 2026-10-31** (Phase 1, pending review) | — |
| OD-031 | Role value `teacher` vs documented `muhaffiz` | Open — non-blocking | — |
| OD-032 | Attendance record: historical group + one row per student/day | **Resolved 2026-10-31** (Phase 1) | — |
| OD-033 | Teacher correction window for attendance | **Resolved 2026-10-31** (Phase 1) | — |
| OD-034 | Memorization schema: historical group, ayah numbers, dataset version | **Resolved 2026-10-31** (Phase 1) | — |
| OD-035 | Review schema and its separation from memorization | **Resolved 2026-10-31** (Phase 1) | — |
| OD-036 | Recitation scope: standalone, no amount, no score yet | **Resolved 2026-10-31** (Phase 1) | — |
| OD-037 | Evaluation engine calculator contracts; no default weights | **Resolved 2026-10-31** (Phase 1) | — |
| OD-038 | Weekly evaluation: week key, inline rule set, deferred revisions | **Resolved 2026-11-01** (Phase 1) | — |
| OD-039 | Weekly report period input and requirement source | **Resolved 2026-11-01** (Phase 1) | — |
| OD-040 | Monthly report: calendar month and requirement coverage | **Resolved 2026-11-01** (Phase 1) | — |
| OD-041 | Monthly achievement when evaluation coverage is partial | Open — non-blocking | — |
| OD-042 | Dashboard student count: membership-scoped or centre-wide | Open — non-blocking | — |

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

---

## OD-030 — Groups (حلقات) schema and teacher-assignment coupling · **RESOLVED 2026-10-31** (Phase 1, pending review)

**Question.** Phase 0 has no `groups` table. The hierarchy `Center → Teachers → Groups → Students` is needed before Attendance and Memorization, but the field names and the relationship to `student_teacher_assignments` were undefined.

**Decision.** Keep it small and explicit:

```text
groups
  id, center_id (FK), teacher_id (FK users, required), name(120),
  status(active|inactive), notes, version, timestamps, deleted_at

group_memberships
  id, center_id, group_id, student_id, joined_at, left_at (null = active),
  assigned_by, reason, timestamps
  unique (student_id) where left_at is null   -- at most one active group

student_teacher_assignments
  + group_id nullable                        -- marks a group-driven assignment
```

Rules:

- A group belongs to one center and one teacher; `teacher_id` is required.
- A student has **at most one active group**. Moving closes the old membership and inserts a new row; history is never overwritten.
- The group's teacher is the student's teacher while the student is in the group:
  - join → close the previous assignment and assign the group's teacher;
  - leave → close only the assignment whose `group_id` is this group;
  - change the group teacher → reassign every active member in one transaction;
  - delete the group → soft-delete, close active memberships and group-driven assignments.
- `group_id` is deliberately **not** a foreign key: assignment history must survive a group purge.

**Permissions.** `groups.view` (teacher default, own groups only), `groups.create`, `groups.update`, `groups.delete`, `groups.manage_students` (manager by default). Scope is structural: the controller resolves `Group::visibleTo($user)`, so an out-of-scope id is a **404** before the policy decides **403**.

**API.** `GET/POST /groups`, `GET/PATCH/DELETE /groups/{id}`, `POST /groups/{id}/students`, `DELETE /groups/{id}/students/{studentId}`. A read-only `GET /teachers` (manager only) supplies the assignment picker.

**Why not the alternatives.**

- *Nullable `teacher_id`* (create first, assign later) leaves a group that cannot accept students and complicates every query. Requiring the teacher is simpler.
- *Independent membership and teacher assignment* creates two sources of truth for "who teaches this student" and lets a teacher see a student who is not in their group. Coupling them is the point of the hierarchy.
- *No history table* would erase which group a student was in when a record was created — the same mistake `students.teacher_id` was rejected for in Phase 0.

**Naming.** Table `groups` (quoted by the ORM; `GROUPS` is a keyword in window frames), model `Group`, Arabic label **حلقة**. No `halaqat` table name — it would diverge from the `/groups` API without adding clarity.

**Blocks:** nothing.

---

## OD-031 — Role value `teacher` vs documented `muhaffiz` · **OPEN** (non-blocking)

**Question.** `docs/permissions.md` and BR-AUT-01 name the role `muhaffiz`; the Phase 1 implementation stores `teacher` (`User::ROLE_TEACHER`) and the API/Flutter use that value.

**Options.**

1. Keep `teacher` as the stored/wire value and display **محفظ** in the UI; amend the docs to say the identifier is `teacher`.
2. Rename the value to `muhaffiz` via a data migration, update `ROLE_TEACHER`, the factories, tests and the Flutter `AppUser`, and change the API contract.

**Recommendation:** option 1 for the MVP. The Arabic label is what users see; the stored value is an implementation identifier, and renaming it now buys no behaviour. If a public API or third-party integration ships, take option 2 in a dedicated, versioned change — not inside a feature slice.

**Blocks:** nothing. The Groups slice uses the existing `teacher` value and labels it **المحفظ**.

---

## OD-032 — Attendance record: historical group + one row per student/day · **RESOLVED 2026-10-31** (Phase 1)

**Question.** `database.md` defines `attendances` without a `group_id`, while the product needs to know which group a student was in when attendance was taken. The docs also use `teacher_id` and `late_minutes`, while the slice brief used `created_by` and `delay_minutes`.

**Decision.** Follow the documented field names and add the missing historical reference:

```text
attendances
  center_id · student_id · group_id · teacher_id
  attendance_date · status · late_minutes · notes
  client_uuid · version · timestamps · deleted_at

  unique (center_id, student_id, attendance_date)
  unique (center_id, client_uuid)
```

- `teacher_id` = who recorded it (the documented name for `created_by`).
- `late_minutes` = the documented name for `delay_minutes`.
- `group_id` is **required** and stores the group at recording time. It is never
  updated when the student later moves groups. `RESTRICT` on delete protects
  the reference; groups are soft-deleted, so normal deletion is unaffected.
- One row per student per day, regardless of group (OD-007's per-day MVP
  recommendation). Re-submitting the same day **updates** the row; the
  original `group_id` and `teacher_id` are kept.
- Statuses are exactly `present`, `excused_absence`, `unexcused_absence`,
  `late` (BR-ATT-01). `late_minutes` is accepted only with `late`, must be
  `>= 0`, and is cleared when the status changes away from `late`.
- `client_uuid` makes a retried offline create idempotent; full sync is not
  implemented in this slice.

**Not coupled to evaluation.** Attendance is raw data. No automatic penalty,
weight or grade is derived here (BR-ATT-04). The evaluation engine will decide
later, explicitly, whether and how attendance participates.

**Blocks:** nothing.

---

## OD-033 — Teacher correction window for attendance · **RESOLVED 2026-10-31** (Phase 1)

**Question.** May a teacher correct an attendance record from an earlier day?

**Options.**

1. Teacher may edit any record in their own group.
2. Teacher may edit only the center's current business day; older corrections
   require a manager.
3. Teachers may not edit at all.

**Decision.** Option 2. `attendance.update` is a teacher default, but the
policy also requires the record's date to equal **today in the center's
timezone**. Managers may correct any date in their center. The same-day rule
mirrors the documented precedent for memorization updates and avoids silently
rewriting historical attendance.

**Enforcement.** `AttendancePolicy::update()` checks center, permission, group
ownership (teacher) and the date. The controller resolves the record through
`Attendance::visibleTo()`, so an out-of-scope id is 404 before the policy
returns 403.

**Blocks:** nothing.

---

## OD-034 — Memorization schema: historical group, ayah numbers, dataset version · **RESOLVED 2026-10-31** (Phase 1)

**Question.** `database.md` defines `memorizations` without a `group_id`, with
global `from_ayah_id`/`to_ayah_id` only, and with `quran_dataset_version_id`
pointing at a table that does not exist yet (OD-029). The slice brief uses
`AUTO` for a calculated amount, while the docs use `CALCULATED`.

**Decision.**

```text
memorizations
  center_id · student_id · group_id · teacher_id
  memorization_date
  surah_id · from_ayah_id · to_ayah_id
  from_ayah_number · to_ayah_number          -- display-friendly, same range
  from_page · to_page
  amount_pages numeric(8,4) · amount_lines
  amount_source CALCULATED | MANUAL
  quran_dataset_version_id (no FK yet) · quran_dataset_version (label)
  grade · hifz_errors · tajweed_errors · reading_errors · notes
  client_uuid · version · timestamps · deleted_at

  unique (center_id, client_uuid)
```

- **`group_id` is historical**, exactly like attendance: captured at creation,
  never updated when the student moves.
- **`from_ayah_number`/`to_ayah_number`** are stored alongside the global ids so
  the API is self-contained for the UI; the global ids remain the canonical
  Quran reference.
- **`quran_dataset_version_id` has no FK** until the dataset table exists in
  Phase 2. `quran_dataset_version` stores the provider label
  (`fixture-dev-v1` today) so a record explains what measured it. The fixture
  is a development artifact, **not** canonical data; OD-029 is untouched.
- **`amount_source`** uses the documented values `CALCULATED` and `MANUAL`.
  The product's "AUTO" is `CALCULATED`; the Flutter enum maps it to **تلقائي**.
  The source is immutable (BR-MEM-02): a PATCH cannot convert one into the
  other.
- **Multiple records per student per day are allowed** (two sessions).
  Idempotency is by `client_uuid`, not by student+date — unlike attendance,
  where the day is the unit.
- **`week_key` is deferred.** It is a denormalised index hint for evaluation,
  and this slice computes no weekly value. Adding it later is additive.
- **Teacher update window:** own records only, same center business day; a
  manager may correct any date. This follows the documented matrix
  (`memorization.update`: own records, same day) rather than inventing a new
  rule.
- **No evaluation coupling:** no `achievement_pct`, no `weighted_score`, no
  ranking. Memorization stores raw amount and quality data only (BR-ATT-04
  logic applies equally here).

**Blocks:** nothing.

---

## OD-035 — Review schema and its separation from memorization · **RESOLVED 2026-10-31** (Phase 1)

**Question.** `database.md` defines `reviews` with the same shared measurement
block as `memorizations`, but the slice needed the same additions made for
memorization (historical group, in-surah ayah numbers, dataset version label)
without duplicating the amount pipeline.

**Decision.** `reviews` mirrors `memorizations` but remains a separate table,
model, endpoints and report source (BR-REV-01):

```text
reviews
  center_id · student_id · group_id · teacher_id
  review_date
  surah_id · from_ayah_id · to_ayah_id
  from_ayah_number · to_ayah_number
  from_page · to_page
  amount_pages numeric(8,4) · amount_lines
  amount_source CALCULATED | MANUAL
  quran_dataset_version_id (no FK yet) · quran_dataset_version
  grade · hifz_errors · tajweed_errors · reading_errors · notes
  client_uuid · version · timestamps · deleted_at

  unique (center_id, client_uuid)
```

- **`group_id` is historical**, like memorization and attendance.
- **The amount pipeline is shared, not copied.** `ResolveQuranAmount` owns the
  MANUAL/CALCULATED orchestration for both domains; the measurement formula
  stays in `QuranDataProvider`. Review never hard-codes `/15`.
- **Multiple reviews per student per day are allowed**; idempotency is by
  `client_uuid`.
- **`week_key` is deferred** with the evaluation slice; no weekly value is
  computed here.
- **Review never feeds memorization totals** (BR-REV-04): a review is not added
  to `amount_pages` of memorization, and the two tables are queried separately.
- **Teacher update window:** own records, same center business day; manager any
  date. Delete is manager-only (`review.delete`); a teacher lacks it by default.
- **The dataset version has no FK yet** because the Quran dataset table is part
  of the blocked import (OD-029). The fixture label is stored for provenance.

**Blocks:** nothing.

---

## OD-036 — Recitation scope: standalone, no amount, no score yet · **RESOLVED 2026-10-31** (Phase 1)

**Question.** `database.md` gives `recitations` the shared measurement block
(`amount_pages`, `amount_source`, `rule_set_id`, `computed_score`) and an
optional attachment to a memorization/review. The slice needed to know how much
of that belongs in Phase 1.

**Decision.** Recitation is a **standalone performance event**:

```text
recitations
  center_id · student_id · group_id · teacher_id
  recitation_date
  surah_id · from_ayah_id · to_ayah_id
  from_ayah_number · to_ayah_number
  from_page · to_page
  grade · hifz_errors · tajweed_errors · reading_errors · notes
  quran_dataset_version_id (no FK yet) · quran_dataset_version
  client_uuid · version · timestamps · deleted_at

  unique (center_id, client_uuid)
```

- **No `amount_pages` / `amount_lines`.** Recitation records the range and the
  performance, not a page amount. The range is sufficient for a later
  evaluation; adding an amount would make recitation a memorization copy.
- **No `rule_set_id` / `computed_score`.** BR-REC-02 forbids a hard-coded
  scoring formula; BR-REC-03/04 need the rule-set engine, which is a later
  slice. Computing a score now would violate the rule, so the grade and error
  counters are stored raw.
- **No `recitation_type` and no `memorization_id` / `review_id`.** Phase 1
  records a standalone event. OD-006 (attached vs standalone) remains open for
  the linking decision; the table can gain nullable columns later without a
  rewrite.
- **`group_id` is historical** and `teacher_id` is the author.
- **The dataset version is stored** (id without FK + label) because the derived
  pages are edition-specific (BR-QRN-10). This is provenance, not a score.
- **Grades reuse `App\Domain\Assessment\RecitationGrade`** (the same catalogue
  memorization/review validate against); no duplicate enum.
- **Quran validation goes through `QuranDataProvider::ayahLocation()`**. The
  provider resolves the range and the page span; a client-supplied page must
  match it, otherwise 422. No `/15`, no direct KFGQPC/QUL dependency.
- **Multiple recitations per day are allowed**; idempotency is by `client_uuid`.
- **Teacher update window:** own records, same center business day; manager any
  date. Delete is manager-only (`recitation.delete`).
- **No coupling:** a recitation never creates or changes a memorization,
  review or attendance row.

**Blocks:** nothing.

---

## OD-037 — Evaluation engine calculator contracts; no default weights · **RESOLVED 2026-10-31** (Phase 1)

**Question.** `docs/evaluation.md` defines the criterion catalogue and the
aggregation formula, but the concrete weights and several calculator params
are center/template data. The engine needed a contract without inventing
business values.

**Decision.**

- **No default weights are shipped.** A rule set must supply criteria whose
  weights are `> 0`, `<= 100` and total exactly `100.00`; otherwise the engine
  throws. An empty or partial rule set is a configuration error.
- **The five documented criteria are implemented with explicit params.**
  Nothing is hard-coded: memorization `required_pages`/`cap`, review
  `target_pages`/`cap`, recitation `aggregate`, error `weights_by_type`/`bands`,
  attendance `status_scores`/`count_absences_in_denominator`.
- **Recitation quality consumes pre-computed scores.** The banded recitation
  rule document in `evaluation.md` §4 is a separate rule-set engine; this
  engine aggregates the scores and does not invent a grade-to-score formula.
- **Attendance denominator.** All records when
  `count_absences_in_denominator` is true; records excluding unexcused
  absences when false. This is the simplest reading of the documented flag and
  keeps the documented default (absences do not hurt) honest. Flagged for
  review when the attendance criterion is first configured.
- **Missing data.** `treat_as_zero` keeps the weight with a zero contribution;
  `exclude_and_renormalize` drops the criterion and renormalises;
  `block` yields `weighted_score = null` and `is_complete = false`.
- **No persistence, no clock, no AI.** The engine returns a result DTO; the
  weekly-evaluation slice will resolve the profile, persist the snapshot and
  own the period boundaries.
- **Two values, never mixed.** `achievement_percentage` is uncapped;
  `weighted_score` is 0..100 or null.

**Blocks:** nothing. The weekly-evaluation slice can consume the engine as-is.

---

## OD-038 — Weekly evaluation: week key, inline rule set, deferred revisions · **RESOLVED 2026-11-01** (Phase 1)

**Question.** `database.md` described `weekly_evaluations` with
`week_start`/`week_end`, a `revision` chain, `computed_by`/`approved_by` and a
`components` column, and a profile version that points at a template version.
The template tables are a Phase 6 deliverable and none of them exist yet, so
the slice had to decide what to store now without pre-building Phase 6.

**Decision.**

- **`week_key` is the canonical week start date.** It is a `date`, not a
  `2026-W43` string, and it exists to carry the uniqueness constraint
  `(center_id, student_id, week_key)`. Aggregation always uses
  `date BETWEEN week_start_date AND week_end_date`, so BR-EVL-16 still holds:
  nothing is bucketed by `week_key`.
- **Three period columns, not two.** `week_key`, `week_start_date` and
  `week_end_date` are all stored, plus `weekly_evaluation_start_day` and the
  center timezone inside `rules_snapshot`. A later change to the center
  setting cannot move a stored week.
- **The rule set lives inline in the profile version.** `evaluation_profile_versions`
  carries `required_pages`, `effective_from` and `rule_set` (JSON). The engine
  consumes it directly and the weekly row copies it into `rules_snapshot`.
  `template_version_id` is stored as a nullable integer with **no FK** until
  the template tables ship.
- **Two snapshot columns instead of many scalar columns.** `rules_snapshot`
  freezes the inputs and configuration; `result_snapshot` freezes the engine
  output including every component. Reading a historical row never re-runs the
  engine and never reads the current profile or center setting.
- **Revisions are deferred.** Phase 1 supports only `computed → approved`.
  BR-EVL-10's revision chain (mandatory reason, previous revision retrievable)
  is not implemented; a correction today means deleting and recomputing a
  draft week. Deferred deliberately rather than half-built — it needs the
  audit-log slice to be trustworthy.
- **Duplicates return the stored row.** A retried `client_uuid`, or a second
  create for the same student/week, returns `200` with the existing row. There
  is no recompute-on-duplicate path (BR-EVL-14, BR-EVL-17).
- **No profile means the documented default.** A student without a profile
  version is evaluated with `required_pages = 1.00` (BR-EVL-01) and no rule
  set, so `weighted_score` is null and only the baseline achievement is
  produced. The row records that no profile version was used.

- **Soft delete removes, it does not tombstone.** The two uniqueness
  constraints are partial (`WHERE deleted_at IS NULL`), so a deleted week can be
  recomputed. A plain unique index made the second insert a 500 — the deleted
  row stayed invisible to the duplicate check but still occupied the index.
- **A `client_uuid` belongs to one student.** Reusing it for another student is
  a 422, never "here is that other student's evaluation".
- **Teacher scope is structural on create too.** A teacher holding
  `evaluation.compute` may still only compute for students in their own groups;
  anything else is 404, matching memorization/review/recitation.

**Blocks:** nothing. Phase 6 (templates) can add `template_version_id` FKs and
a revision chain without rewriting stored rows.


---

## OD-039 — Weekly report period input and requirement source · **RESOLVED 2026-11-01** (Phase 1)

**Question.** A weekly report needs a period, but the center's start day is
configuration that can change. If the report resolves every request from the
*current* setting, a stored week computed under an older setting would be
reported with different boundaries. Separately, the report has to show a
`required_pages` for students who have no stored evaluation.

**Decision.**

- **Two inputs, one required.** `week_key` is a reference date the server
  resolves through `WeekPeriodResolver` (center timezone + current start day).
  `week_start_date` is used **verbatim** as the boundary, so a historical week
  stays readable after the setting changes. Neither value is ever computed on
  the client.
- **The report reads records, not evaluations.** Memorization, review,
  recitation and attendance are aggregated from the domain tables for
  `date BETWEEN start AND end`. The report never runs the evaluation engine and
  never creates a weekly evaluation: `evaluation` is `null` when none exists.
- **The requirement comes from the evaluation when there is one.** If a stored
  evaluation exists, `required_pages` is its snapshot — the requirement that was
  in force for that week. Otherwise the profile version effective at the week
  start is read; failing that, the BR-EVL-01 default (`RequiredPages::DEFAULT`,
  one place in code) applies. The report therefore never shows two different
  requirements for the same student in one row.
- **Memorization achievement is computed from live records** with
  `Achievement::percentage` (the canonical uncapped formula), so a record added
  after the evaluation was frozen still shows in the memorization section while
  the `evaluation` section keeps its snapshot. The two figures are deliberately
  separate and are never averaged (BR-EVL-15).
- **No attendance rate.** The business rules define four statuses but no rate,
  so the report returns the counts only rather than inventing a formula.
- **Rows are current memberships.** The student list is the active group
  membership in the actor's scope; a student who left the group before the week
  is not resurrected. Historical *records* keep their own `group_id`/`teacher_id`,
  which the domains already store.

**Blocks:** nothing. Monthly/student/attendance reports can reuse the same query
shape and the same scope rule.


### OD-039 — review round (2026-11-01)

Three contract fixes after a deep review of the weekly report:

- **`start_day` describes the returned boundary.** With `week_start_date` the
  day is derived from the date (`WeekStartDay::fromIsoWeekday`), not from the
  center's current setting. Reporting "monday" next to a Saturday–Friday
  boundary was simply wrong.
- **`week_key` and `week_start_date` are mutually exclusive.** Sending both is
  `422`; the earlier silent precedence of `week_start_date` was an ambiguity the
  contract should not carry.
- **`group` / `teacher` follow the evaluation when one exists.** The stored
  evaluation froze the group and teacher that graded the week; showing the
  student's *current* group next to a historical evaluation rewrote history.
  Without an evaluation the row still shows the current membership, which is
  the documented row-set rule. The extra lookup is one `whereIn` for the
  historical group ids and only runs when an evaluation points outside the
  already-loaded groups.


---

## OD-040 — Monthly report: calendar month and requirement coverage · **RESOLVED 2026-11-01** (Phase 1)

**Question.** A monthly report needs a period and a monthly requirement, but
the system only ever stores weekly evaluations. Summing requirements for weeks
that were never evaluated would mean inventing them; reporting `0%` for a month
with no evaluations would be worse — it reads as "the student did nothing".

**Decision.**

- **The month is a calendar month** (`YYYY-MM`, 1st → last day) in the center
  timezone. It is deliberately *not* derived from `weekly_evaluation_start_day`:
  a month is not an evaluation week, and reusing the week setting would make the
  report depend on unrelated configuration. There is no "current month" default
  — a missing `month` is a validation error.
- **The monthly requirement is the sum of `required_pages` of the stored weekly
  evaluations whose `week_start_date` falls inside the month.** A week with no
  evaluation contributes nothing. The alternatives were rejected explicitly:
  `weeks × RequiredPages::DEFAULT` invents a requirement, and reading the
  current profile rebuilds history from configuration that may have changed.
- **`achievement_percentage` is `null` when that sum is zero.** The ratio is
  undefined, so it is reported as null rather than `0.00`. When the sum is
  positive the value is computed with the canonical uncapped formula
  (`Achievement::percentage`), so a student above the requirement shows 150%,
  250%, 300%.
- **Coverage is visible through `weekly_evaluations.count`**, not through an
  invented per-week field. A month with two of four weeks evaluated shows
  `count: 2` next to a requirement of the two stored weeks, which is exactly the
  data the system actually has. No `missing_weeks` / `evaluation_coverage` field
  was added — that would be a new semantic the business rules do not define.
- **`group` / `teacher` follow the latest stored evaluation of the month**, for
  the same reason as the weekly report: an evaluation froze the group that
  graded it. The row set itself stays the student's current membership.
- **Shared support, not duplicated arithmetic.** `AmountTotals` (decimal
  summation), `StudentScope` (membership + scope) and `GroupLookup` (historical
  groups) moved into `App\Domain\Reports\Support` and are used by both reports,
  so the weekly and monthly figures cannot drift apart.

**Blocks:** nothing. A future student/attendance report can reuse the same
support classes and the same scope rule.

---

## OD-041 — Monthly achievement when evaluation coverage is partial

**Question.** The monthly requirement is the sum of the requirements frozen in
the **stored** weekly evaluations (OD-040). A month can therefore have fewer
evaluations than weeks. Concretely, for October 2026 (four evaluation weeks):

| | |
|---|---|
| stored evaluations | 2 (weeks 1 and 2), `required_pages = 1.00` each |
| monthly `required_pages` | `2.00` |
| memorization in the month | `4.0000` |
| reported `achievement_percentage` | **`200.00`** |

The arithmetic is exactly BR-EVL-02 (`actual / required × 100`, uncapped) and it
is honest about the data the system has: only two weeks were ever graded, and
`weekly_evaluations.count = 2` sits in the same row. But a reader who ignores
the count sees "200%" for a month where half the weeks were never evaluated.

**Options.**

1. **Keep as-is** (current). The requirement covers what was actually graded;
   coverage is visible via `weekly_evaluations.count`. No invented numbers.
2. **Suppress the ratio when coverage is partial** — `achievement_percentage`
   becomes null unless every week of the month has an evaluation. Safe, but
   hides a real figure and requires defining "every week of the month", which
   depends on the center's start day and on month boundaries.
3. **Add an explicit coverage block** — e.g. `weekly_evaluations.expected` (the
   number of evaluation weeks the month contains) so `count / expected` is
   readable without arithmetic. Does not change any stored or computed value.

**Recommendation.** Option 1 now, Option 3 when the report UI is built — it adds
context without inventing semantics. Option 2 is rejected as the default: it
turns "we do not know" into "no data" for the reader, and the missing weeks are
a process gap, not a student result.

**Status.** Open, non-blocking. The current behaviour is documented in
`api.md` §7.2 and pinned by a regression test
(`a half evaluated month uses only the stored requirements`). Changing it is a
business decision, not an implementation fix.

**Blocks:** nothing.


---

## OD-042 — Dashboard student count: membership-scoped or centre-wide

**Question.** `dashboard.students.total` can mean two different things:

- **A. Students with an active membership in a visible group** — what the
  reports already use for their row set.
- **B. Every student record in the centre** — including students that were
  registered but never placed in a group.

A centre with 120 students of whom 8 are unassigned would report `120` under B
and `112` under A. Neither number is wrong; they answer different questions, and
no business rule defines which one the dashboard should show.

**Current behaviour (implemented).** Option **A**. The dashboard shares
`StudentScope` with the reports, so every figure on the page is computed over
exactly the same population: a student is counted when they hold an active
membership in a group the actor can see. `active` is the subset whose status is
`active`; a student with status `inactive` still counts in `total`.

**Why A for now.** It keeps the dashboard consistent with the weekly and monthly
reports — the same student appears in the list and in the KPI. Option B would
produce a total that no report can reconcile, and for a teacher it would be
impossible without leaking the centre's size.

**Options.**

1. **Keep A** (current). Consistent with reports; unassigned students are
   invisible on the dashboard.
2. **Report both** — `students.total` (membership-scoped) plus a separate
   `students.unassigned` for the centre. Adds a field and a definition.
3. **Switch to B for managers only** — the manager sees the centre roster,
   teachers keep A. Splits the meaning of the same key by role, which is easy to
   misread.

**Recommendation.** Keep A, and add `unassigned` later if managers report that
students are going missing from the dashboard. Option 3 is rejected: one key with
two meanings is a bug waiting to happen.

**Status.** Open, non-blocking. Pinned by
`inactive students count in the total but not in the active figure` and by the
scope tests.

**Blocks:** nothing.

