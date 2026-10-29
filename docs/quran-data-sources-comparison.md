# Tahfeez — Quran Data Sources: Engineering Study

Status: **Phase 0 study, for review** · 2026-10-28
Question: *what is the smallest dataset Tahfeez needs to measure memorization precisely, while keeping a future Madinah-mushaf reader possible?*

Inspected at pinned commits:

| Repository | Commit | Date | Note |
|---|---|---|---|
| `hadealahmad/quran-madina-layout` | `b6b120923b29` | 2026-09-02 | single "Initial release" commit |
| `tarekeldeeb/quran-madina-html` | `7cc0a686da30` | 2026-10-25 | release 2.2.2 |

---

## 1. Executive summary

1. **Neither layout project is a measurement source.** Both are *rendering* datasets: they answer "how does this line look on this page", not "which page and line does this ayah occupy". Tahfeez needs the latter, and the KFGQPC official dataset already provides it directly.
2. **Both projects hardcode the page as 15 lines** (`range(1, 16)` in the reference engine, `line_1to15` in the schema, `15 Lines/Page` in the skill spec). That is correct for the 1405 AH print and exactly the assumption Tahfeez must not inherit (BR-QRN-09).
3. **The "Madinah Mushaf" is not one layout.** The 1405 AH and 1441 AH prints break lines differently on roughly 37% of lines — only 5,658 of 9,046 lines share a word count, per the project's own measurements. Page measurement is therefore **edition-dependent**. Tahfeez has now pinned **`madinah_1405h_hafs_uthmani`** (OD-026, resolved 2026-10-29).
4. **Neither dataset can represent an ayah that crosses a page break.** Each ayah carries one `page` field and line numbers `1…15`. In the sampled shards no page-final line ends mid-ayah, and no page boundary repeats an ayah. Whether that reflects the print or the model is unverifiable from software alone — **OD-025 stays open and blocking for import sign-off.**
5. **There is a licensing conflict between the two repositories.** `quran-madina-html` licenses *code and assets* under **Waqf GPL 2.0** (a copyleft licence: modified redistributions must stay under it). `quran-madina-layout` claims **MIT** and states its datasets are "derived from quran-madina-html (MIT License)" — but it ships files that appear to be the same v1 databases (identical filenames, byte sizes within 1 byte). **BLOCKER-01.**
6. **Recommendation: A — King Fahd only for master data.** Option B is acceptable *later*, and only as a separate, separately-licensed presentation layer that never feeds the measurement tables. Option C is rejected.
7. **The official artifact is 1441H, not 1405H** — measured, not assumed: 100% line-break match with the 1441H layout on juz 30, 20.9% with 1405H. The 1405H print is not published on the developer platform. **OD-029.**

---

## 2. What was inspected

Not README-only. For each repository: the full file tree (401 and 690 entries), the license files, the specification documents, the Python build pipeline, the JSON schemas, and **actual data samples** (juz-03, juz-30 shards, v2 shard, manifest) analysed programmatically.

| Artifact | Repo 1 | Repo 2 |
|---|---|---|
| Data files | 331 under `assets/db` (10 dataset variants × manifest + 30 juz shards, plus monolithic DBs) | 443 under `assets/db` (v1 + v2: 1441H, 1405H, IndoPak) |
| Code | Qt6/QML desktop applet + web reader + `pseudo_engine.py` | `src/db/v2/*.py` pipeline (justification, font patching, encoding), JS runtime, Flutter/React/RN wrappers |
| Specs | `SKILL.md`, `references/{data-schemas,pipeline-and-math,typography-and-text,framework-ports}.md` | `docs/v2/design-decisions.md`, `docs/development/databases.md`, mkdocs guide |
| Tests | — | `test/db_test.py`, `test/render_test.py`, `v2_layout_test`, `v2_encoding_test`, `v2_cursive_test` |
| License | MIT (code) | Waqf GPL 2.0 (code + assets) |

---

## 3. `quran-madina-layout` — what it actually is

### 3.1 Structure

A **rendering engine** for the 1405 AH (old Madina) 15-line print, plus an "AI skill" describing how to port it. Ten prebuilt datasets, each a function of `(dataset edition × font family × font size)`:

```text
assets/db/Madina05-Hafs-24px/
├── manifest.json      # title, edition year, font, line width, content_hash,
│                      # 114 suras, 30 juz starts, 604 page boundaries
├── juz-01.json … juz-30.json
└── (monolithic Madina05-Hafs-24px.json, ~1.9 MB)
```

### 3.2 Data model (verified against the files, not the docs)

```jsonc
// juz shard
{ "j": 29,
  "d": [ [ suraIdx0, ayaIdxInternal, page1, [ {"l": 1, "t": "سُورَةُ النَّبَإِ", "s": -1} ] ], … ] }
```

- `l` — line number, **1…15** (`line_1to15` in the schema).
- `t` — the text *segment* of that ayah on that line, with kashida already inserted.
- `s` — precomputed horizontal stretch factor; `-1` means centred (page 1, short lines, surah ends).

Manifest `pages` is `[suraFrom, ayaFrom, suraTo, ayaTo]` per page — **ayah-level page boundaries only**, no lines.

### 3.3 The ayah-index trap

`ayaIdxInternal` is **not** the ayah number:

| Internal | Meaning |
|---|---|
| 0 | surah title frame |
| 1 | basmala slot |
| 2 | ayah 1 |

So `internal = ayah_no + 1`, and every surah reports `ayah_count + 2` (Al-Fatiha = 9). Any integration that maps these indices to canonical ayah numbers without the offset will be off by one ayah — silently, and in the direction that corrupts ranges.

### 3.4 Measured properties (juz-30 shard, 638 records)

| Property | Value |
|---|---|
| Parts (line segments) | 811 |
| `l` range | 1…15, **zero** values above 15 |
| Ayahs spanning more than one line | 170 / 638 |
| Page-final text lines ending mid-ayah | 0 (juz-03: 21 pages, juz-30: 23 pages; 4 "exceptions" are surah-title decorations) |
| Page boundaries repeating an ayah | 0 of 604 |
| Ayah 2:282 (longest in the Qur'an) | page 48, 15 parts, lines 1…15 — occupies the whole page |

### 3.5 Justification algorithm

Genuinely good engineering, and worth learning from even if we never use the data:

1. Measure a unit kashida **in context** (`Width("بـب") − Width("بب")`), because glyph advance depends on shaping.
2. `K_needed = ceil(deficit / U_k)`; distribute evenly across eligible words using `divmod` with a spread remainder.
3. Re-measure, then residual `S = clamp(W_target / W_new, 0.5, 2.0)` rounded to 3 decimals.
4. Runtime clamp `min(s × stretch_scale, W_target / W_measured)` so no line can overflow.

Unjustified lines (`s = -1`): page 1, lines with ≤2 words (ḥurūf muqaṭṭaʿāt), and natural surah-end lines that fit.

### 3.6 What it is not

- Not a measurement dataset: page boundaries are ayah-level; line occupancy must be derived from parts, and page-spanning ayahs are not representable.
- Not edition-neutral: 1405 AH only.
- Not free of the 15-line constant: the reference renderer iterates `range(1, 16)`.

---

## 4. `quran-madina-html` — what it actually is

### 4.1 Structure

A production web component (`<quran-madina-html>`) with wrappers for React, React Native, Flutter, Vue, Angular, Svelte, Astro and WordPress. Two data generations:

- **v1** — `assets/db/Madina05-*.json`, the same shape as repo 1's data (repo 1's files appear to be copies).
- **v2** — `assets/db/v2/{Madina-1441H,Madina-1405H,IndoPak-15}.json`, a rewritten model.

### 4.2 v2 data model (verified)

```jsonc
{ "j": 29,
  "d": [ [77, 0, 582, [ {"l":1, "t":"سُورَةُ النَّبَإِ", "f":["z0i6",""]} ]] ],
  "L": { "582": [ {"k":"sura","w":15.4376},
                  {"k":"text","w":15.4376,"z":0.92,
                   "s":[-0.1293,-0.0098,…],   // per-gap space widths, in em
                   "c":0.9019} ] } }          // per-word scaleX
```

- `d` — ayah records: same `[sura, ayaInternal, page, parts]` shape as v1, plus font-feature tags `f` per word.
- `L` — per page, per line: kind (`sura`/`bism`/`text`), line width **in em**, font scale `z`, gap widths `s`, word scale `c`.

This is a **layout recipe**, not a coordinate table: word positions are computed at render time from gaps and scales. It is not queryable for "which line does ayah X start on" without re-running the layout logic.

### 4.3 Pipeline (the interesting part)

- Text: Tanzil Uthmani 1.1, re-encoded at build time to the font's expected encoding (`tanzil_encoding.py`), validated token-by-token against both prints (84,116 tokens; only pause marks differ).
- Line breaks: **KFGQPC 1441H page data** via the `quran-qcf4` npm package (MIT), reduced to first/last token per line as `sura:aya:index` — *no glyphs or text retained*. 1405H and IndoPak breaks come from DigitalKhatt (MIT code, OFL fonts).
- Justification: HarfBuzz justifies each line at build time; stretched words are encoded as font feature tags (`jp01…jpNN`), with a CoreText workaround (`q04 3` on/off twins) and cursive-join fixes.
- Verification: every word form of all three layouts is re-shaped and compared; the build fails on mismatch. Line widths verified within 0.08 px.

This is a mature, well-tested project — and a warning about scope: reproducing it is a multi-month typography effort, not a data import.

### 4.4 Provenance chain

```text
Quran text ──────── Tanzil (Uthmani 1.1)
1441H line breaks ─ KFGQPC page data → quran-qcf4 (MIT) → first/last token per line
1405H / IndoPak ─── DigitalKhatt (code MIT, fonts OFL 1.1)
Justification ───── HarfBuzz at build time + patched fonts (OFL)
Container ───────── Waqf GPL 2.0
```

Each layer has a **different** licence. That is the core licensing problem, not a detail.

---

## 5. Cross-cutting findings

| # | Finding | Consequence for Tahfeez |
|---|---|---|
| F1 | Page/line coordinates are **edition-specific** (1405H vs 1441H differ on ~37% of lines) | Must pin an edition in the dataset version; amounts from different editions are not comparable (**OD-026**) |
| F2 | Both datasets use `internal = ayah + 1` with two decoration slots | Any import from these sources needs an explicit offset + decoration filter; never map raw indices |
| F3 | Both hardcode 15 lines per page | Reinforces BR-QRN-09: `lines_count` must be data |
| F4 | Neither can represent a page-spanning ayah (single `page`, `l ≤ 15`) | OD-025 remains blocking; the official artifact must be checked directly |
| F5 | Layout data is a *recipe* (widths, gaps, scales), not coordinates | Cannot be used to answer measurement questions without a renderer |
| F6 | Word numbering has conventions (basmala = 4 words, sura name not counted, At-Tawba none) | If Level C is ever added, these rules must be specified, not assumed |
| F7 | Repo 1's data appears copied from repo 2's v1 assets, under a different licence | Licence conflict — BLOCKER-01 |

### 5.1 Empirical confirmation from the real artifact (2026-10-29)

The official KFGQPC Hafs artifact (`kfgqpc_hafs_v30`, 6,236 rows, SHA-256 `227e6b15…f245`) was downloaded and compared against both layouts on juz 30 (564 ayahs):

| Reference | Same page | Same line span |
|---|---|---|
| 1441H (repo 2, v2) | **100.0%** | **100.0%** |
| 1405H (repo 2, v2) | 92.4% | **20.9%** |

Both references share one build pipeline, so tooling cannot explain the gap. **The platform currently publishes the 1441 AH layout.** It does not expose the 1405 AH print as a developer dataset — which turns the edition question from a documentation detail into a blocking product decision (**OD-029**), and means the "1405H is available officially" assumption behind OD-026 does not hold.

---

## 6. Precision levels A / B / C / D

### Level A — `surah + ayah + page + line_start + line_end`

| Provides | Enables | Missing |
|---|---|---|
| Which page an ayah starts on, and its first/last line | Exact page-fraction measurement for any ayah range; page and juz grouping; "2.5 pages" | No word positions; no page-spanning representation unless `page_end` is added |

**A+** (recommended): `page_start, line_start, page_end, line_end`. Same cost, unambiguous for spanning ayahs.

### Level B — `page + line → ayah` mapping

Inverts the same information: for each line, which ayahs (or parts of ayahs) it holds. Useful for pagination and for rendering "page N" as a text block.

- **Derivable from A** for the line where an ayah starts/ends; not derivable for mid-line partial coverage without word data.
- Alone, it cannot measure a range that starts mid-line precisely.

### Level C — `ayah + word + page + line + word position`

| Provides | Enables | Cost |
|---|---|---|
| Word identity and order, per line | Word-level highlighting, "memorized 3 words", precise partial-line ranges, search-result positioning | ~84k rows per dataset version; word segmentation and basmala conventions must be pinned |

Not required by any MVP rule: hifz ranges are ayah-based (§9), and page-fraction measurement needs only lines.

### Level D — full layout

Line widths, gaps, per-word scale, font features, kashida placement — everything needed to *draw* the page as printed.

- Font-dependent and edition-dependent; regenerated when the font changes.
- Not a database concern: it is a build artifact shipped as static assets.

### Decision table

| Question | Level needed |
|---|---|
| How many pages did the student memorize? | **A+** |
| Which page/line does this ayah start on? | **A+** |
| Weekly achievement %, reports, rankings | **A+** |
| Show the page as printed (reader) | **D** + A/C for mapping |
| Highlight a word inside an ayah | **C** |
| "He memorized up to word 5 of ayah 7" | **C** (not in MVP) |

---

## 7. Comparison matrix

| | KFGQPC official | quran-madina-layout | quran-madina-html | Tanzil |
|---|---|---|---|---|
| Type | Master data (text + coordinates) | Rendering dataset + engine | Rendering dataset + runtime | Text corpus |
| Page | ✅ | ✅ (604) | ✅ (604) | ❌ |
| Line | ✅ `line_start`/`line_end` | ⚠️ derivable from parts, `l ≤ 15` | ⚠️ derivable, `l ≤ 15` | ❌ |
| Ayah | ✅ | ✅ (internal index, +1 offset) | ✅ (same) | ✅ |
| Word | ❌ (not in field list) | ❌ (text segments, no word ids) | ⚠️ implicit in spans | ✅ (text only) |
| Layout | ❌ | ✅ (stretch factors) | ✅ (widths, gaps, scales, features) | ❌ |
| Edition | **pinned: `madinah_1405h_hafs_uthmani`** (OD-026) | 1405 AH | 1441H default; 1405H, IndoPak | n/a |
| Versioning | per import | `content_hash` per DB | per release | 1.1 |
| Licence | to be recorded (OD-005) | MIT claimed — **disputed** | Waqf GPL 2.0 | own terms |
| Redistribution | must be verified | **blocked pending BLOCKER-01** | copyleft; modified redistributions stay under Waqf | verify before any redistribution |
| Suitability | **primary** | reference only | reference only | cross-validation only |
| Main risk | edition ambiguity, licensing | licence conflict, 15-line constant, no spanning | copyleft, font coupling, maintenance burden | text only, no coordinates |

---

## 8. Licensing findings

Assessed separately per asset class, as required. **This is an engineering assessment, not legal advice.**

| Asset | Repo 1 | Repo 2 | Tahfeez position |
|---|---|---|---|
| Source code | MIT | Waqf GPL 2.0 (WordPress plugin dual GPLv2+/Waqf) | Not used in MVP |
| Quran text | Inherited (Tanzil-derived) | Tanzil Uthmani 1.1 | KFGQPC is our text source; Tanzil only for cross-validation, never redistributed |
| Fonts | `Hafs.ttf`, `UthmanTN_v2-0.ttf`, `AmiriQuran*`, `me_quran` — **no licence file in the repo** | DigitalKhatt, OFL 1.1, renamed `QMH …` | **BLOCKER-02** for any bundled font until terms are recorded |
| Layout data | MIT claimed | Waqf GPL 2.0 | **BLOCKER-01** |
| Generated JSON | MIT claimed | Waqf GPL 2.0 | Same as layout data |
| Derived assets | — | Patched fonts (OFL), feature-tagged DBs | Not used |

### BLOCKER-01 — licence conflict on the layout data

Evidence:

- `quran-madina-html/LICENSE.md`: *"Code and assets are licensed under 'Waqf' General Public License 2.0"*.
- Waqf GPL 2.0, clause seven: *"The user can redistribute modifications only under this license…"* — a copyleft obligation on derivative works.
- `quran-madina-layout/README.md`: *"The precomputed Quranic layout databases and datasets are derived from quran-madina-html (MIT License)."*
- `quran-madina-layout` ships `assets/db/Madina05-Hafs-24px.json` (1,924,045 bytes); `quran-madina-html` ships the same path at 1,924,046 bytes. Same filenames, same v1 dataset family, 1-byte difference.

Either repo 1 is mistaken about the licence, or it is redistributing copyleft assets under a permissive claim. **We must not rely on repo 1's MIT claim for any data.** Until resolved in writing, treat the layout datasets as Waqf GPL 2.0.

Practical consequence for Tahfeez: we do not need them. KFGQPC provides the coordinates directly, so the conflict is avoidable rather than something to litigate.

### BLOCKER-02 — font licensing

`quran-madina-layout` bundles KFGQPC typefaces with **no licence file**. Repo 2's fonts are OFL 1.1 via DigitalKhatt, which is usable but requires the OFL notice and rename rules to be honoured.

Tahfeez's reader, if built, should use an **OFL-licensed** Uthmani font with the notice shipped, or obtain written terms from KFGQPC. Do not bundle a font whose terms are undocumented.

### Tanzil

Tanzil's terms are specific to the Tanzil project and must be recorded verbatim if used. In Tahfeez, Tanzil is a **cross-validation source only** — never redistributed, never bundled — so it does not enter the distribution surface.

---

## 9. What Tahfeez needs now vs. later

### Now (MVP measurement)

- Level **A+**: `page_start, line_start, page_end, line_end` per ayah, plus surah metadata and per-page `lines_count`.
- Dataset version metadata (source, edition, checksum, validation status).
- **Nothing** from either layout repository.

### Later (reader)

- Level **D** as a separate, static, font-coupled asset — never joined into measurement tables.
- Level **C** only if the product asks for word-level ranges or word highlighting.
- A separate licence review for whatever layout data is used, at the moment it is adopted.

---

## 10. Recommendation

**A — King Fahd only — for master data and measurement.**

Then, if and when a printed-page reader is funded, **B in the narrow sense**: KFGQPC remains the master source, and a layout layer is added as an *isolated presentation artifact*, preferably rebuilt in-house from OFL fonts plus line breaks derived from our own licensed dataset.

**Never C.** Reasons, in order of severity:

1. **Licence**: the layout data is copyleft (Waqf GPL 2.0) and its MIT claim is contradicted by the upstream licence. Making it the source would put a copyleft obligation at the centre of the data model.
2. **Wrong shape**: it stores rendering recipes, not coordinates. Measurement would have to re-run layout logic to answer "which line is ayah 7 on".
3. **Wrong assumption**: 15 lines per page is baked in; our rules forbid that constant.
4. **Incomplete**: no representation for page-spanning ayahs, no word identities.
5. **Edition-locked**: 1405 AH only, while the official dataset describes a different print.

**Why A is sufficient:** the specification measures memorization in pages and lines (§9, §10), never in words. Level A+ answers every MVP question exactly, with one dataset, one licence to clear, and no rendering dependency.

---

## 11. Risks

| # | Risk | Mitigation |
|---|---|---|
| R1 | Edition ambiguity (1405H vs 1441H) changes measured amounts | **Resolved (OD-026)** — edition pinned to `madinah_1405h_hafs_uthmani`; each artifact must still *prove* its edition (VR-EDITION) |
| R2 | Page-spanning ayahs unrepresentable in every available source | **OD-025** — verify against the printed mushaf, not against software |
| R3 | Someone "just imports" a layout DB later for convenience | ADR-0009 + BR-QRN-10: layout data never enters measurement tables |
| R4 | Font bundled without terms | BLOCKER-02, provenance record per asset |
| R5 | Ayah-index offset (+1, two decoration slots) copied into an importer | Import only from KFGQPC; if ever not, an explicit offset test is mandatory |
| R6 | Word-level requirements arrive late (word highlighting) | Design keeps Level C additive: `quran_words` keyed by dataset version, no change to measurement tables |
