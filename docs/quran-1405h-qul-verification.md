# QUL Madinah V1 (1405) — Verification Report

**Date:** 2026-10-29 · **Phase:** 0 (verification only, no application code)
**Verdict:** edition **corroborated** · ayah coordinates **not derivable standalone** · licensing **BLOCKED**
→ **OD-029: OPEN — QUL data verified but licensing blocked**

---

## 1. Source

| Field | Value |
|---|---|
| Platform | Quranic Universal Library (QUL) — `qul.tarteel.ai` |
| Platform code | `TarteelAI/quranic-universal-library` — **MIT**, © Tarteel Inc. (code only) |
| Artifact repository | `blueheron786/quranic-universal-library-mushaf-layouts` — **third-party mirror, 1 star, no LICENSE file** |
| Dataset in scope | `qpc-v1-15-lines.db` — "Mushaf Al-Madinah V1 (1405 print) layout" |
| Sibling artifact | `qpc-v2-15-lines.db` — "QCF V2 (1421H print)" |

The mirror is **not** an official QUL distribution channel. The official download could not be retrieved: `qul.tarteel.ai/resources/mushaf-layout` is JavaScript-rendered, the resource list is behind a Turbo frame, QUL's own docs state **"no API is available"**, and the CDN path guesses returned 404. The file was therefore validated as a **mirror copy**; its bytes are not proven identical to QUL's published file.

## 2. Artifact used

```text
qpc-v1-15-lines.db.zip  →  qpc-v1-15-lines.db
```

Downloaded from `raw.githubusercontent.com/blueheron786/quranic-universal-library-mushaf-layouts/HEAD/qpc-v1-15-lines.db.zip`. Archive integrity OK, single member.

## 3. SHA-256

| Object | Size | SHA-256 |
|---|---|---|
| `qpc-v1-15-lines.db.zip` | 99,413 B | `fb04bdfb0da38925853bc0ccdecd08b1536ac74fb1c8e9ff27a9af7ad7a452f9` |
| `qpc-v1-15-lines.db` (extracted) | 241,664 B | `54e5cf604da95f9e65db3fb9dce0c75c17d197e41543ba846ed49264f9c0442a` |
| `qpc-v2-15-lines.db.zip` (contrast) | 99,450 B | `29941fa5e8504bc50a3007e2d1ef2f65280c483ea8b465662141f2ce7cdaed3d` |

## 4. Database schema

Two tables only.

```sql
CREATE TABLE info (
  name TEXT, number_of_pages INTEGER, lines_per_page INTEGER, font_name TEXT
);

CREATE TABLE pages (
  page_number INTEGER, line_number INTEGER, line_type TEXT, is_centered INTEGER,
  first_word_id INTEGER, last_word_id INTEGER, surah_number INTEGER
);
```

| Table | Rows |
|---|---|
| `info` | 1 |
| `pages` | 9,046 |

`info` content (verbatim): `('Quran Complex V1 ( 1405 print )', 604, 15, 'v1')`

**There is no ayah column, no surah/ayah mapping, and no text.** A row means: *this page, this line, holds words `first_word_id…last_word_id`*. That is a **rendering layout**, not a coordinate table.

## 5. Edition evidence

### 5.1 Declared (weak on its own)
`info.name` = "Quran Complex V1 ( 1405 print )" — written by whoever built the mirror's source, not by KFGQPC.

### 5.2 Structural signature (consistent)
| Property | Observed |
|---|---|
| Pages | 604, continuous 1…604 |
| Lines | 9,046 = 602 × 15 + 2 × 8 |
| Pages 1–2 | **8 lines** — the irregular opening pages of this print |
| `surah_name` lines | 114 |
| `basmallah` lines | 112 = 114 − 2 (At-Tawba has none; Al-Fatiha's basmala is verse 1) |

The 8-line opening pages and the 112 basmala lines are edition-specific fingerprints, not generic properties.

### 5.3 Independent line-break comparison (decisive)

Juz 30, 564 ayahs, against two layouts built by one pipeline:

| Reference | Same page | Same line span |
|---|---|---|
| **1405H** | **100.0%** | **92.9%** |
| 1441H | 92.4% | 21.5% |

The contrast is unambiguous: QUL V1 matches the 1405 layout and does not match the 1441 layout.

### 5.4 Conclusion
**QUL V1 is the 1405 Madinah print.** Confidence: **high**. Three independent signals agree (declared metadata, structural fingerprints, line-break comparison). The 7.1% line-span difference is attributable to the reference being a DigitalKhatt rendering rather than the QPC plates.

## 6. Validation results

| # | Check | Result |
|---|---|---|
| 1 | 604 pages | **PASS** — continuous 1…604, no gaps, no extras |
| 2 | Lines per page | **PASS** — 15 × 602, 8 × 2; line numbers contiguous within each page |
| 3 | Total lines | **PASS** — 9,046 = 602×15 + 16 |
| 4 | Pages 1–2 different | **PASS** — 8 lines each |
| 5 | 114 surahs | **PASS** — 114 `surah_name` rows, 114 distinct `surah_number` |
| 6 | Basmala lines | **PASS** — 112, exactly the expected count |
| 7 | Word-id continuity | **PASS** — 0 discontinuities across 8,820 ayah lines; ids run 1…83,668 |
| 8 | Ayah count | **NOT DIRECTLY TESTABLE** — no ayah column |
| 9 | Duplicate ayah across pages | **NOT TESTABLE** — no ayah identifier |
| 10 | Ayah crossing a page | **NOT TESTABLE** from the artifact |

Checks 8–10 are not failures of the data; they are **limits of the schema**. The file cannot answer ayah-level questions by itself.

### 6.1 Attempt to derive ayah coordinates (and why it failed)

QUL word ids total 83,668; the KFGQPC text tokenises to 77,432; the difference is **exactly 6,236** — one token per ayah, i.e. the ayah-number marker is counted as a word. That relationship allowed a candidate mapping.

It **drifts**. Verified example — Surah 37 begins at page 445 line 15 (title) and page 446 line 1 (basmala), with the first ayah at page 446 line 2 (word ids 61,192–61,200). The derived mapping placed 37:1 starting at page 445 line 14 — four lines early. The same drift produced 185 implausible "page-crossing" ayahs, including 25-character surah-opening ayahs spanning four lines.

**Conclusion: word ids cannot be mapped to ayahs without QUL's own word table.** The exact global total is a coincidence of aggregation, not a guarantee of local alignment. Any downstream measurement built on such a mapping would be silently wrong.

## 7. Provenance

```text
KFGQPC printed mushaf (1405 AH)          ← the physical artefact, not licensed to us
        ↓  (page/line layout transcribed by whom? unknown)
QUL resource "Quran Complex V1 (1405 print)"   ← platform curated by community
        ↓  (unofficial mirror, no LICENSE)
blueheron786/quranic-universal-library-mushaf-layouts
        ↓
qpc-v1-15-lines.db  ← the file validated here
```

**The chain has two unverifiable links:**

1. **Who produced the layout?** QUL's own Credits page states: *"The majority of the resources currently available in QUL were created or curated by the community, not Tarteel."* The 1405 layout is therefore not attested by KFGQPC, and QUL does not claim authorship.
2. **Is the mirror faithful?** No signature, no version, no official download to compare against.

By contrast, the KFGQPC artifact had a named publisher and a verifiable download URL. This one has neither.

## 8. Licensing

| Asset | Licence found | Usable? |
|---|---|---|
| QUL platform code (`TarteelAI/quranic-universal-library`) | **MIT** | Yes — but it covers code, not data |
| This layout database | **No LICENSE file** in the mirror repo | **No** |
| QUL website terms | Footer links to `tarteel.ai/terms` — a **consumer app ToS**, not a data licence | **No** |
| QUL stated intent | *"Resources included in QUL are intended for you to download and package with your own project"* | Supportive, but an intent statement, not a grant |
| Underlying print | KFGQPC 1405 print — KFGQPC publishes **no** data terms | **No** |
| Fonts | Not included in this artifact | n/a |

**Verdict: BLOCKED.** Four separate gaps: no licence on the file, no data licence on the platform, a ToS that does not address redistribution, and an underlying print whose publisher grants nothing in writing. The "intended to be packaged" sentence is the strongest signal available — and it is still not a licence, and it comes from a party that says the data is not theirs.

## 9. Suitability for Tahfeez

### 9.1 Field mapping

| QUL field | Tahfeez field | Verdict |
|---|---|---|
| `page_number` | `page_start` / `page_end` | ✅ direct |
| `line_number` | `line_start` / `line_end` | ✅ direct |
| `surah_number` | `surah_id` | ⚠️ only on `surah_name` rows (114 rows), **not per ayah** |
| `first_word_id` / `last_word_id` | — | ⚠️ no Tahfeez equivalent; requires a word table |
| `line_type` | — | ℹ️ needed to exclude title/basmala lines |
| `is_centered` | — | ℹ️ presentation only |
| — | `aya_no` | ❌ **absent** |
| — | `quran_dataset_version_id` | ❌ absent; no version, no checksum |
| — | `lines_count` per page | ⚠️ derivable (count rows per page) — the one place this artifact is genuinely useful |

**Net: 2 of 6 required fields are directly available.** The artifact is line-oriented; Tahfeez is ayah-oriented.

### 9.2 What it would take to use it

1. A QUL word table mapping `word_id → surah, ayah` (separate resource, separate licence).
2. A proven tokenisation contract between that table and the layout.
3. A validation that the derived ayah coordinates match the printed page — which is the same manual verification we cannot do without the print.

That is a three-artifact dependency chain, each link with its own licence, replacing a single artifact (KFGQPC) that already provides ayah-level coordinates.

### 9.3 What it is genuinely good for

- **Page height table**: 604 rows, 15/15/…/8/8 — exactly the `lines_count` metadata Tahfeez needs, and independently confirms the 8-line opening pages.
- **Line inventory per page** for a future reader.
- **A second opinion** on the 1405 layout's structure.

## 10. Risks

| # | Risk | Severity |
|---|---|---|
| R1 | No licence — cannot bundle, cannot ship | **Blocking** |
| R2 | Provenance is a mirror of an unattributed community transcription | **High** |
| R3 | No ayah column — cannot measure without a second dataset | **High** |
| R4 | Word-id mapping drifts; a naive join would silently corrupt amounts | **High** |
| R5 | No version/checksum in the artifact; no way to detect a silent upstream change | Medium |
| R6 | QUL disclaims accuracy ("may contain errors") | Medium |
| R7 | The 92.9% line agreement means it is *not* byte-identical to a DigitalKhatt 1405 rendering — one of the two is slightly off, and we cannot tell which without the print | Medium |

## 11. Proposed decision

**OD-029 → OPEN — QUL data verified but licensing blocked.**

Reasoning:

- The **edition question is now answered**: QUL V1 is the 1405 print, corroborated by three independent signals. That is real progress and it removes the "does 1405 data exist at all" uncertainty.
- The **licensing question is not answered**, and it is not a formality: no licence on the file, no data licence on the platform, a consumer ToS, and a publisher who grants nothing.
- The **engineering fit is poor**: the artifact is line-oriented and needs a companion word table plus a tokenisation contract to answer an ayah-level question that KFGQPC answers directly.

**Recommended next step:** treat QUL V1 as a **structural reference**, not a master source — use it to cross-check page heights and line counts, exactly as `quran-madina-html` is used. Do not build measurement on it.

**What would change the verdict:** written terms from Tarteel/QUL covering redistribution of the layout data, **and** the QUL word table under the same terms, **and** a provenance statement naming who transcribed the 1405 layout.

---

## 12. Summary table

| Question | Answer |
|---|---|
| Is QUL V1 the 1405 print? | **Yes** — high confidence (declared + structural + line-break comparison) |
| Hafs / Uthmani? | Not stated in the artifact; consistent with the 1405 Madinah print |
| 604 pages? | **Yes**, continuous |
| 15 lines? | **Yes** on 602 pages, **8** on pages 1–2 |
| Can it produce `page_start/line_start/page_end/line_end` per ayah? | **No** — no ayah column, and word-id mapping drifts |
| Is it a rendering layout or real coordinates? | **Rendering layout** (line → word range) |
| Licence? | **None found** → BLOCKED |
| Suitable as Tahfeez master data? | **No.** Useful as a structural cross-check only |
