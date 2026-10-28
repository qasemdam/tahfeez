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
| OD-005 | Quran source & licensing | **Partial** — source resolved, licensing gate remains | bundling only |
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
| OD-025 | Page-break ayah encoding in the official dataset | Open — technical verification | Phase 2 import sign-off |

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

**Applied to:** `quran-data.md` §2, §12 · `quran-source-record.md` · `business-rules.md` BR-QRN-07, BR-QRN-08 · ADR-0008.

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

## OD-025 — Page-break ayah encoding in the official dataset

**Question.** The KFGQPC row shape is one `page` plus `line_start`/`line_end`. When an ayah continues past the bottom of its page, does the dataset:

- **A)** let `line_end` exceed `lines_count(page)` (overflow encodes the continuation), or
- **B)** clamp/split so that `line_end ≤ lines_count(page)` always holds?

**Why it matters.** Under A, the importer can reconstruct exact coordinates by walking forward. Under B, a page-break ayah would be silently truncated and every range ending on one would be under-measured. The two cases require different handling, and guessing produces plausible-but-wrong numbers — the worst failure mode for this system.

**Action (Phase 2, before import sign-off).**
1. Inspect the artifact for `line_end > lines_count(page)` occurrences and count them.
2. Compare at least 5 candidates against the printed mushaf.
3. Record the result in `validation_report` and in `quran-data.md` §6 (replace the A/B wording with the observed behaviour).
4. If the count is zero, prove it — do not assume it.

**Recommendation:** implement the normalisation rule already specified (`quran-data.md` §6) so both encodings are handled, then verify empirically which one the artifact actually uses.

**Blocks:** Phase 2 import sign-off — not the start of Phase 2 work. The measurement engine can be built against synthetic fixtures before the artifact is in hand.
