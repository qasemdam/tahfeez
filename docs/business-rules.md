# Tahfeez — Business Rules (Phase 0)

Status: **Draft for review** · Phase 0 · Owner: Architecture
Every rule below is traceable to a numbered section of the Master Instruction (§n).
Rules marked **[OD-nnn]** depend on an unresolved decision — see `open-decisions.md`.
Nothing in this document may be changed silently: changes require an ADR.

Conventions:
- **MUST / MUST NOT** = hard requirement, enforced server-side.
- **SHOULD** = default behaviour, configurable.
- All calculations are **deterministic**. AI is forbidden inside business logic (§21).

---

## 1. Tenancy & Identity

| ID | Rule |
|----|------|
| BR-TEN-01 | Every business record belongs to exactly one **Center**. `center_id` is mandatory on all tenant tables and is never accepted from the client — it is derived from the authenticated user. |
| BR-TEN-02 | A user may only read or write data of their own center. Cross-center access MUST return `404` (not `403`) to avoid resource enumeration. |
| BR-TEN-03 | The hierarchy `Region → Center → Users → Students` MUST be additively extensible. MVP ships `Center` only; adding `Region` later MUST NOT require rewriting existing tables (§7). |
| BR-TEN-04 | Every center has a `timezone` and a **`weekly_evaluation_start_day`** setting (enum `WeekStartDay`: `saturday`…`friday`). Week boundaries are computed in the center's timezone, never in UTC and never on the client. The value is configuration, **not** a universal rule — no day may be hard-coded in evaluation logic. |
| BR-TEN-05 | Changing `weekly_evaluation_start_day` applies only to periods opening on/after `weekly_evaluation_start_day_effective_from`. It MUST NOT recalculate, re-bucket or mutate any existing weekly evaluation, and MUST NOT alter a stored `week_start`/`week_end`. A change that would make a new period overlap an already-computed period is rejected. |
| BR-TEN-06 | **Gregorian is the canonical calendar** for all stored dates and all period keys. Hijri dates may be *displayed* alongside Gregorian, computed at render time. A Hijri date is never a storage key, never a join key, and never the basis of a week or month boundary. **[OD-003 resolved]** |

## 2. Roles & Authorization

| ID | Rule |
|----|------|
| BR-AUT-01 | MVP roles: `center_manager`, `muhaffiz`. Additional roles (`supervisor`, `region_admin`) are out of scope. |
| BR-AUT-02 | A `muhaffiz` sees **only** students with an active assignment to them. This is enforced in the query layer *and* in policies — never only in the UI (§20). |
| BR-AUT-03 | A `center_manager` sees all students of the center and may manage teachers, students, evaluation templates and settings. |
| BR-AUT-04 | A `muhaffiz` may act on a student's records only within the permissions granted by the manager. Permission changes take effect on the next request (no cached authorization in the token beyond the role). |
| BR-AUT-05 | Removing a student from a teacher MUST NOT delete historical records created by that teacher. History keeps the original `teacher_id`. |
| BR-AUT-06 | The server is the only authority on permissions. Client-side hiding of UI is cosmetic only. |

## 3. Students

| ID | Rule |
|----|------|
| BR-STU-01 | A student belongs to one center and has at least: name, age, grade level, phone, guardian name, enrollment date, teacher, status (`active`/`inactive`). |
| BR-STU-02 | Age is a **derived** value from date of birth where available; a static age is stored only as a fallback. **[OD-001]** |
| BR-STU-03 | `enrolled_at` is a business date (no time component) and is immutable after creation; corrections require an audited edit permission. |
| BR-STU-04 | A student has **at most one active teacher assignment** at any instant. Assignment changes are recorded as history rows with `assigned_at` / `unassigned_at`. |
| BR-STU-05 | Setting a student to `inactive` MUST NOT delete records, MUST exclude the student from default reports and rankings, and MUST NOT affect historical weekly evaluations. |
| BR-STU-06 | Student deletion is a soft delete, restricted to `center_manager`, and fully audited. |
| BR-STU-07 | Student phone and guardian phone are optional but validated when present (E.164-normalised where a country is known). |
| BR-STU-08 | A student's weekly evaluation profile is stable until explicitly changed by an authorized user (§13). |

## 4. Quran Master Data

| ID | Rule |
|----|------|
| BR-QRN-01 | Quran data is **read-only** for all users. There is no write endpoint, no admin UI, and no client-side mutation path. |
| BR-QRN-02 | The reference edition is the **Madinah Mushaf, Hafs ʿan ʿAsim, Uthmani script** (§8). Any other edition is out of scope. |
| BR-QRN-03 | Surah names, ayah text, ayah numbers and page numbers MUST NOT be editable, and MUST NOT be derived or guessed at runtime. |
| BR-QRN-04 | Quran data is imported as an **immutable, versioned snapshot** with `source`, `source_version`, `downloaded_at`, `imported_at`, `checksum_sha256` and `validation_status`. Importing a new version adds rows; it never rewrites an existing snapshot. |
| BR-QRN-05 | The client bundles a Quran dataset for offline use; the server remains the reference for validation. A mismatch MUST be resolved in favour of the **active** server dataset version. |
| BR-QRN-06 | Quran identifiers (`surah_id`, `ayah_id`) are stable integers and are the only accepted inputs for ranges. Free-text surah names are never stored. |
| BR-QRN-07 | **Source priority.** (1) KFGQPC official developer dataset — primary and authoritative; (2) Tanzil — secondary cross-validation only, never imported as master data; (3) any other source — not authoritative without an explicit ADR. Replacing the primary source requires an ADR, not a configuration change. |
| BR-QRN-08 | No permission is assumed. Before a dataset is committed to the repository or bundled into a shipped application, a provenance record must document source URL, dataset version, download date, checksum, attribution requirements and licence terms as provided by the source. Unresolved licensing is a **blocking** decision. |
| BR-QRN-09 | **Page structure is data.** The system MUST NOT assume a fixed number of lines per page. `lines_count` is derived from the imported dataset, and measurement is a sum of page fractions (`Σ covered_lines(p) / lines_count(p)`), never a division by a constant. |
| BR-QRN-10 | **The edition is pinned** to `madinah_1405h_hafs_uthmani` — Madinah Mushaf, 1405 AH, Hafs ʿan ʿAsim, Uthmani script, 604 pages, 15 lines per page. Page/line coordinates are edition-specific — the 1405 AH and 1441 AH prints differ on roughly 37% of line breaks — so amounts measured under different editions are not comparable. Switching edition creates a new dataset version; it never rewrites existing records. **[OD-026 resolved]** |
| BR-QRN-11 | **Layout data is not measurement data.** Line widths, gaps, stretch factors, font features and per-word positions MUST NOT be imported into the measurement tables. Third-party layout datasets (`quran-madina-layout`, `quran-madina-html`) are reference implementations only; if a printed-page reader ships, its layout is a separate versioned asset with its own licence. |
| BR-QRN-12 | **Page height is a property of the page, not of its contents.** `lines_count` is the physical number of text lines (15 on pages 3–604, 8 on pages 1–2 for the canonical edition). It MUST NOT be derived from the lines covered by ayah records: surah title and basmala decorations occupy 2 lines belonging to no ayah, so coverage-based counting would report 13 instead of 15 and overstate those pages by up to 15%. |
| BR-QRN-13 | **An artifact must prove its edition before activation.** Being published by the official complex is not evidence of which print it describes. Edition must be established from explicit metadata or from an independent line-break comparison, recorded in the validation report. An artifact that cannot be proven to match `madinah_1405h_hafs_uthmani` MUST NOT be activated, whatever its provenance. |

## 5. Memorization (New Hifz)

| ID | Rule |
|----|------|
| BR-MEM-01 | A memorization record captures: student, teacher, date, surah, start ayah, end ayah, start page, end page, amount, amount source, recitation grade, errors, notes (§9). |
| BR-MEM-02 | **Amount source is explicit and immutable for a given record version**: `CALCULATED` or `MANUAL`. The system MUST NOT convert one into the other, and MUST NOT silently overwrite a manual amount with a calculated one (§9). |
| BR-MEM-03 | When a range of ayahs is provided, the amount is **calculated** by the system from the mushaf page/line mapping (BR-MEM-06). The user does not type the calculated amount. |
| BR-MEM-04 | When a manual amount is provided, the ayah range MAY be omitted; the record is marked `MANUAL` and MUST be visually distinguished in reports. |
| BR-MEM-05 | Start page/end page are **derived** from the ayah range and stored read-only for reporting. They are not user-editable in the `CALCULATED` path. |
| BR-MEM-06 | **Page measurement (core rule).** A range is measured in mushaf lines, not by counting ayahs and not by treating one surah as one page. For pages `p1…pn` covered by the range: `amount_pages = Σ (covered_lines(p) / lines_count(p))`. This is a sum of page fractions; it reduces to `lines / lines_count` only when the covered pages happen to share a height. `lines_count` comes from the dataset (BR-QRN-09). |
| BR-MEM-07 | A surah is **never** assumed to equal one page. No rule may infer page count from surah identity or ayah count alone (§10). |
| BR-MEM-08 | Precision: `amount_pages` is stored as `numeric(8,4)`. `amount_lines` (integer) is stored alongside for `CALCULATED` records so the value can be recomputed and audited. |
| BR-MEM-09 | Display/evaluation rounding is a **policy**, applied after measurement, never baked into the stored raw value. **[OD-004]** |
| BR-MEM-10 | If a student memorises more than required, the **actual** amount is recorded as-is; the **required** amount is not modified. See BR-EVL-04 (§10). |
| BR-MEM-11 | A memorization record's `session_date` MUST fall within the student's enrollment period and MUST NOT be in the future relative to the center's timezone (clock-skew tolerance documented in the sync spec). |
| BR-MEM-12 | Editing a memorization record that is already consumed by an **approved** weekly evaluation MUST NOT silently change that evaluation. It creates a recompute proposal instead (§13). |
| BR-MEM-13 | A single range MAY span two surahs (e.g. end of Al-Baqarah into Al-Imran). Ranges are expressed in global ayah order, so cross-surah ranges are well-defined and MUST NOT be split into fabricated records. **[OD-010]** |
| BR-MEM-14 | Every `CALCULATED` record stores the `quran_dataset_version_id` used for measurement. Importing or activating a newer dataset MUST NOT change the stored amount of an existing record. Re-measuring an old record is an explicit, audited operation with a reason. |

## 6. Review (Murajaʿah)

| ID | Rule |
|----|------|
| BR-REV-01 | Review is an **independent entity** from new memorization, with its own table, endpoints and reports (§16). |
| BR-REV-02 | A review records: student, surah, start/end ayah, start/end page, review amount, recitation grade, errors, notes, date, teacher. |
| BR-REV-03 | Review amounts use the same measurement engine as memorization (BR-MEM-06) and the same `CALCULATED` / `MANUAL` source rule. |
| BR-REV-04 | Review records MUST NOT be aggregated into "new memorization" totals in any report or evaluation unless an explicit, named criterion does so. |

## 7. Recitation (Tasmiʿ)

| ID | Rule |
|----|------|
| BR-REC-01 | Recitation supports a simple grade (`excellent`, `very_good`, `good`, `needs_review`) and a detailed error breakdown: memorization errors, tajweed errors, reading/recitation errors (§14). |
| BR-REC-02 | The score derived from a recitation MUST be produced by a **configurable rule set**, not by a formula hard-coded in the source. |
| BR-REC-03 | Rules, thresholds and weights are defined by the center/teacher; the system only supplies defaults. |
| BR-REC-04 | A recitation record stores the **rule set version** used, so historical scores remain reproducible. |
| BR-REC-05 | Recitation is a first-class record with its own report (§17). Whether it is always attached to a memorization/review record or may stand alone is **[OD-006]**. |

## 8. Attendance

| ID | Rule |
|----|------|
| BR-ATT-01 | Statuses are exactly: `present`, `excused_absence`, `unexcused_absence`, `late` (§15). |
| BR-ATT-02 | `late_minutes` is optional and only meaningful for `late`. |
| BR-ATT-03 | One attendance record per student per attendance day. A second record for the same student/day is a conflict, not a duplicate to be appended. **[OD-007]** |
| BR-ATT-04 | Attendance affects evaluation **only** if a criterion explicitly references it. The default is **no effect** — the system MUST NOT assume absence lowers the grade (§15). |
| BR-ATT-05 | Attendance cannot be recorded for dates before enrollment or for inactive periods; historical records are never rewritten by a status change. |
| BR-ATT-06 | Bulk entry for a whole class/day is supported and must be idempotent (re-submitting the same day does not duplicate records). |

## 9. Evaluation

| ID | Rule |
|----|------|
| BR-EVL-01 | Default baseline: **required = 1 page per week**. |
| BR-EVL-02 | `achievement_pct = actual_pages / required_pages × 100`, computed with decimal arithmetic (no floating point), rounded to 2 decimals, half-up. |
| BR-EVL-03 | Reference values that MUST hold: 0.5 → 50%, 1 → 100%, 1.5 → 150%, 2 → 200%, 3 → 300% (§11). |
| BR-EVL-04 | A student above 100% is **outstanding**, but MUST NOT become the new baseline for other students. The baseline stays 1 page = 100% (§11). |
| BR-EVL-05 | `required_pages` is a property of the student's **evaluation profile version**, not a global constant editable in place. |
| BR-EVL-06 | Advanced evaluation supports multiple weighted criteria. The sum of active criteria weights MUST equal exactly 100.00% before a template version can be published (§12). |
| BR-EVL-07 | Criteria are limited to the documented catalogue (memorization amount, recitation quality, errors, attendance, review). New criteria require an ADR — the system MUST NOT invent mandatory criteria (§12). |
| BR-EVL-08 | Every weekly evaluation stores an immutable **snapshot** of the rules used (template version, weights, thresholds, calculator versions) (§13). |
| BR-EVL-09 | Changing a profile or template creates a **new version** with `effective_from`. Past weeks are never recomputed with the new rules. |
| BR-EVL-10 | An approved weekly evaluation is immutable. Corrections create a new **revision** with a mandatory reason, keeping the previous revision retrievable. |
| BR-EVL-11 | The evaluation engine is deterministic and pure: same inputs + same snapshot ⇒ same output, always. No randomness, no AI, no wall-clock dependency. |
| BR-EVL-12 | Criteria whose inputs are missing (e.g. no attendance recorded) MUST be handled by an explicit, configured policy (`treat_as_zero` / `exclude_and_renormalize` / `block`), never by an implicit default. **[OD-009]** |
| BR-EVL-13 | **Basic achievement is not capped.** `achievement_pct` may exceed 100% (250%, 300%) and is stored as computed. Any display cap is a presentation choice, never a stored-value change. **[OD-008 resolved]** |
| BR-EVL-14 | Weekly evaluation generation is **idempotent**: recomputing the same week with unchanged inputs yields the same revision content, not a new revision. |
| BR-EVL-15 | **Two separate values, never mixed.** Basic achievement (`achievement_pct`, uncapped, §11) and the advanced weighted score (`weighted_score`, normalised **0–100**, §12) are stored in separate columns, displayed separately, and never averaged, combined, substituted or used interchangeably. No report may present one as the other. |
| BR-EVL-16 | A weekly evaluation owns its **period boundaries** (`week_start`, `week_end`, `week_start_day`) as immutable snapshot data. Recomputing a historical week uses those stored boundaries, never the center's current setting. Record aggregation uses `session_date BETWEEN week_start AND week_end` — never a denormalised `week_key`. |

## 10. Reports

| ID | Rule |
|----|------|
| BR-RPT-01 | Reports cover: weekly, monthly, per-student, attendance, memorization, review, recitation, rankings, progress, teacher performance, center dashboard (§17). |
| BR-RPT-02 | Reports read from stored evaluations and records; they MUST NOT recompute business values with a different formula than the engine. |
| BR-RPT-03 | Export formats: PDF, Excel, Print. Exports are generated asynchronously for large ranges and expire after a defined TTL. |
| BR-RPT-04 | Reports respect tenant and role scope: a teacher's report never contains students not assigned to them. |
| BR-RPT-05 | Rankings MUST be deterministic and tie-broken by a documented rule (e.g. lower error count, then student id) so results are stable across requests. **[OD-014]** |

## 11. Offline & Sync

| ID | Rule |
|----|------|
| BR-SYN-01 | The app MUST be usable without internet for recording memorization, review, recitation and attendance. |
| BR-SYN-02 | Every offline-created record carries a client-generated identifier (`client_uuid`) so retries never duplicate data. |
| BR-SYN-03 | Server time is authoritative for ordering. Client clocks are business data (`session_date`), never sync ordering. |
| BR-SYN-04 | Conflict resolution is **per entity** and documented. Blanket "last write wins" is forbidden (§19). |
| BR-SYN-05 | Unresolvable conflicts are surfaced to the user and never silently dropped. |
| BR-SYN-06 | Every applied sync operation is written to the audit trail with actor, device and operation id. |
| BR-SYN-07 | Reference data (Quran, templates) is read-mostly and may be cached; business records are never discarded to save space without user consent. |
| BR-SYN-08 | A client that has been offline longer than the change-log retention window MUST perform a full resync, and must be told so explicitly. |

## 12. Security & Audit

| ID | Rule |
|----|------|
| BR-SEC-01 | Every endpoint authenticates and authorizes. No endpoint is protected only by the mobile app. |
| BR-SEC-02 | All input is validated server-side, including values the client "already validated". |
| BR-SEC-03 | Tokens are device-scoped, revocable, and never stored in plain text on the server or in logs. |
| BR-SEC-04 | Secrets never enter the repository. Only `.env.example` is committed. |
| BR-SEC-05 | Tenant isolation is enforced structurally (scope + policy + composite indexes), not by convention. |
| BR-AUD-01 | Audit log is append-only: no update, no delete, no soft delete. |
| BR-AUD-02 | Audited actions include: authentication, permission changes, student lifecycle, record create/update/delete, evaluation approval/recompute, export generation, sync conflict resolution. |
| BR-AUD-03 | An audit entry records before/after state (redacted for sensitive fields), actor, device, IP and request id. |

## 13. Definition of "Done" for a business rule

A rule is considered implemented only when:
1. It is enforced in the backend (not only the client).
2. It has at least one automated test that fails if the rule is removed.
3. It is listed in this document with its ID, and the test references that ID.
