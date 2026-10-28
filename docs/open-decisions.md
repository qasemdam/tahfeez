# Tahfeez — Open Decisions Register

Status: **Awaiting review** · Phase 0
Rule §22: an undefined rule is **not invented**. It is recorded here, with options and a recommendation, and blocked until decided.

**How to read the "Blocks" column:** `Phase n` means the decision must be made before that phase starts. `—` means it can be decided later without rework.

---

## 0. Summary

| ID | Decision | Blocks | Recommendation |
|---|---|---|---|
| OD-001 | Age: date of birth vs static age | Phase 3 | Store `birth_date`, keep `age_years` as fallback |
| OD-002 | Which day starts the week | Phase 6 | Center-configurable, default Saturday |
| OD-003 | Hijri or Gregorian week/report calendar | Phase 6 | Gregorian for storage, Hijri for display |
| OD-004 | Rounding policy for evaluation input | Phase 6 | `exact`, configurable to 0.25/0.5 |
| OD-005 | Quran dataset source & font licence | **Phase 2** | KFGQPC Madinah dataset + licensed Uthmani font |
| OD-006 | Recitation: standalone or attached | Phase 4 | Standalone record, optional link |
| OD-007 | Attendance granularity: day or session | Phase 5 | One record per student per day |
| OD-008 | May a criterion exceed 100%? | Phase 6 | Per-criterion `cap`, default uncapped for pages |
| OD-009 | Missing-data default policy | Phase 6 | `block` for recitation, `treat_as_zero` for pages |
| OD-010 | Cross-surah ranges allowed in one record | Phase 4 | Yes |
| OD-011 | Where `required_pages` is configured | Phase 6 | Profile version, center default 1.00 |
| OD-012 | Encrypt local mobile DB | Phase 8 | Yes, SQLCipher |
| OD-013 | Token lifetimes | Phase 1 | 30d mobile / 12h web |
| OD-014 | Ranking tie-break rule | Phase 7 | Errors asc, then student id |
| OD-015 | Who publishes templates / approval flow | Phase 6 | Manager publishes; teacher drafts |
| OD-016 | Grade level: free text or lookup table | Phase 3 | Free text in MVP |
| OD-017 | Column-level encryption for PII | Phase 9 | No in MVP; disk encryption + access control |
| OD-018 | Framework/dependency versions | **Phase 1** | Pin after environment audit |
| OD-019 | `change_log` retention window | Phase 8 | 90 days |
| OD-020 | Data residency / erasure legal basis | Phase 9 | Organization policy, not code |
| OD-021 | Can a teacher belong to multiple centers? | Phase 3 | No in MVP (one center per user) |
| OD-022 | Deleting a record referenced by an approved evaluation | Phase 6 | Soft delete + recompute proposal |
| OD-023 | May managers mutate users/permissions offline? | Phase 8 | No |
| OD-024 | Branding of exported PDFs | Phase 7 | Neutral template + center name |

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

## OD-002 — Week start day

**Question.** `achievement_pct` is weekly. A week must have a defined first day, or two devices will disagree about which records belong to "this week".

**Options.** Saturday (common in the region) · Sunday · Monday · Friday · configurable per center.

**Recommendation:** configurable per center, default **Saturday**. Stored as `centers.week_start_day`, resolved server-side only.

**Blocking note:** this is a *blocking* decision for the evaluation phase. Building weekly aggregation before it is fixed guarantees a migration of historical rows.

---

## OD-003 — Calendar system

**Question.** Reports and centers in the region often think in Hijri months; the database and sync need an unambiguous calendar.

**Options.** Gregorian only · Hijri only · Gregorian storage + Hijri display.

**Recommendation:** store `date` values as Gregorian (unambiguous, timezone-safe), display Hijri where the center chooses. Never store a Hijri date as the primary key of a period.

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

## OD-005 — Quran dataset and font licensing

**Question.** The system's correctness depends on a specific printed mushaf. The dataset must be legally usable and the font must be licensed for embedding in a mobile app.

**Options.**
1. KFGQPC (King Fahd Complex) published Madinah mushaf data + KFGQPC Uthmanic Hafs font.
2. A community dataset (e.g. Tanzil text) with a separately sourced page/line mapping.
3. Build the mapping in-house from scanned pages.

**Recommendation:** option 1, with written permission/attribution recorded in `docs/decisions/`. Option 3 is months of work and is not MVP.

**Blocking:** Phase 2 cannot start without this. Importing an unlicensed or mismatched dataset would corrupt every measurement in the system.

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

## OD-008 — Score caps

**Question.** Can a criterion produce more than 100 (e.g. 250% memorization)? If yes, a single criterion can dominate a weighted score.

**Options.** Cap everything at 100 · cap per criterion · uncapped with weights absorbing it.

**Recommendation:** uncapped for `memorization_pages` (it is the spec's headline behaviour), capped at 100 for quality criteria, configured per criterion via `params.cap`. The aggregation must then state whether the total is clamped — recommendation: clamp the **final** weighted score to 0…100, keep `achievement_pct` uncapped.

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
