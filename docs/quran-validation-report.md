# Quran Artifact Validation Report — KFGQPC Hafs v3.0

**Run date:** 2026-10-29 · **Phase:** 0 (verification only, no application code)
**Verdict:** structural assertions **12/12 PASS** (V-01…V-10, V-12, N-03; V-11 checksum recorded, byte-stability not re-tested) · edition check **FAILED** · licence **BLOCKED**
→ **Not activatable.** No snapshot may be marked `passed`.

---

## 1. Artifact identity

| Field | Value |
|---|---|
| Source | King Fahd Glorious Quran Printing Complex — developer platform |
| Page | https://qurancomplex.gov.sa/quran-dev/ |
| Artifact URL | `https://download.qurancomplex.gov.sa/resources_dev/kfgqpc_hafs_v30.zip` |
| Artifact name | `kfgqpc_hafs_v30.zip` (dataset "v3.0") |
| Riwayah (declared) | Hafs ʿan ʿAsim |
| Script (declared) | Uthmani |
| **Edition (declared)** | **none — the artifact states no edition** |
| Downloaded at | 2026-10-29 |
| Size | 10,155,774 bytes (matches server `Content-Length`) |
| SHA-256 | `227e6b1564d980f2bd09c2c35ebfb0330ac268c79a7c247cd1ab665bc635f245` |
| Server `Last-Modified` | 2026-09-30 11:25:17 GMT |
| SQL dump header date | 2026-09-30 14:21 |
| Archive integrity | OK (`zipfile.testzip()` → no corrupt member) |
| Formats inside | CSV, JSON, SQL, TXT, XLSX, XML, HTML + `.ttf` font + `.docx` + `.pdf` |
| Records | 6,236 ayahs × 11 columns |

Columns (as shipped): `id, jozz, page, sura_no, sura_name_en, sura_name_ar, line_start, line_end, aya_no, aya_text_unicode, aya_text_emlaey`.

**There is no readme, no manifest, no metadata block, and no edition field in any of the seven formats.** The only textual metadata is the SQL header date.

---

## 2. Method

The JSON representation was parsed as-is (read-only; the source was not modified). 6,236 rows were checked programmatically. Line-break comparisons used two independent reference layouts from `quran-madina-html` v2 — 1405H and 1441H — which share one build pipeline, so the comparison isolates *layout* from *pipeline*.

---

## 3. Structural validation (V-01 … V-12)

| ID | Check | Result | Evidence |
|---|---|---|---|
| V-01 | 114 surahs, numbered 1…114 | **PASS** | distinct = 114 |
| V-02 | Ayah counts per surah + total 6,236 | **PASS** | 0 mismatches, total = 6,236, canonical sum = 6,236 |
| V-03 | Pages continuous 1…604 | **PASS** | 604 distinct, min 1, max 604 |
| V-04 | Page in range, monotonic in mushaf order | **PASS** | 0 out of range, monotonic = true |
| V-05 | `1 ≤ line_start ≤ line_end ≤ page height` | **PASS** | 0 invalid, **max `line_end` = 15** |
| V-06 | No missing ayahs (gap-free ids) | **PASS** | 6,236 ids, 6,236 distinct, 1…6236 |
| V-07 | No duplicate ayahs / ids | **PASS** | 0 duplicates |
| V-08 | Surah/ayah ordering, contiguous per surah | **PASS** | monotonic = true, contiguous = true |
| V-09 | Text present, no Latin characters | **PASS** | 0 empty, 0 Latin |
| V-10 | Deterministic samples | **PASS** | 1:1→p1 L2, 1:7→p1 L6-8, 2:255→p42 L8-13, 2:282→p48 L1-15, 112:1→p604 L3, 114:6→p604 L15 |
| V-11 | Checksum | **PASS (recorded)** | SHA-256 `227e6b15…f635f245` computed over the downloaded bytes; matches the server's `Content-Length`. Not re-fetched, so byte-stability across downloads is unverified — a second fetch is required before activation |
| V-12 | Boundary fixtures | **PASS** | first = 1:1, last = 114:6, surah start pages monotonic |

**Structural verdict: the dataset is internally consistent and complete.** No missing, duplicated, misordered or out-of-range data.

---

## 4. Page structure findings (important)

`line_end` never exceeds 15, but **not every page is 15 lines**:

| Page height (max `line_end`) | Pages |
|---|---|
| 15 | 602 |
| **8** | **2** (pages 1 and 2) |

And the *line coverage by ayahs* is not uniform either:

| Uncovered lines on the page | Pages | Cause |
|---|---|---|
| none | 506 | ordinary page |
| (1, 2) | 41 | surah title + basmala at the top of the page |
| (7, 8), (8, 9), (10, 11), (5, 6), (6, 7), (3, 4), … | 56 total | surah starts mid-page: 2 decoration lines |

**Every gap is exactly two consecutive lines** — the surah title frame and the basmala, which belong to no ayah.

### Consequence for the model

`lines_count(page)` MUST be the **physical page height** (15, or 8 on pages 1–2), **not** the number of lines covered by ayah records. Deriving it from coverage would yield 13 for a surah-start page and 8 for page 1, and would overstate every measurement taken on those pages by up to 15%.

This is a real defect that the earlier specification would have produced silently: `quran-data.md` §7 said "`lines_count` = number of distinct lines present on that page in the dataset". That definition is **wrong** and is corrected in this round.

---

## 5. Edition determination — the decisive finding

### 5.1 The artifact declares no edition

Searched all seven formats: no "1405", no "1441", no "طبعة", no manifest, no readme. The vendor page does not state it either. Per VR-EDITION, the edition must therefore be established from structure or comparison — not inferred from the vendor's name.

### 5.2 Structural evidence (consistent with both prints, not decisive)

604 pages, 15 lines on 602 pages, 8 lines on pages 1–2, Al-Fatihah at lines 2–8 of page 1. Both Madinah prints share these traits.

### 5.3 Line-break comparison (decisive)

Juz 30, 564 ayahs with a known reference in both layouts:

| Reference | Same page | Same line span |
|---|---|---|
| **1441H** | **100.0%** | **100.0%** |
| **1405H** | 92.4% | **20.9%** |

Both references come from the same project and the same build pipeline, so the difference cannot be explained by encoding, marker placement or tooling. Sample differences against 1405H are genuine re-flow, not ornaments:

```text
79:12  artifact  page 583, line 13–14    1405H  page 583, line 14
79:13  artifact  page 583, line 14       1405H  page 583, line 14–15
```

### 5.4 Conclusion

> **The KFGQPC Hafs v3.0 artifact encodes the 1441 AH layout, not the 1405 AH layout.**

Confidence: **high** for "not the 1405H layout as published"; **high** for "matches the 1441H layout", with the caveat that the 1441H reference is itself derived from KFGQPC page data, so the 100% figure is partly expected. The 20.9% figure against 1405H is independent evidence and is not explained by tooling.

### 5.5 Consequence

The canonical edition decided in OD-026 is **1405 AH**. This artifact **cannot** serve as the canonical dataset. `validation_status` must remain `failed`; **no snapshot is created**.

This is exactly the failure mode the verification gate exists to catch: importing it would have produced plausible, permanently wrong page amounts for a print the center does not use.

---

## 6. OD-025 — page-spanning ayahs

**Question:** can this artifact represent an ayah that crosses a page break?

**Answer: there are none in this dataset, and the data shows no sign of truncation.**

Evidence:

| Test | Result |
|---|---|
| `line_end > 15` (overflow encoding) | **0 rows** |
| Same `(sura, aya)` on two pages (split encoding) | **0 rows** |
| Longest ayah 2:282 (1,193 chars) | page 48, **lines 1–15** — exactly fills a page |
| Ayahs spanning ≥ 10 lines | 3 |
| Ayahs ending at line 15 | 707 |
| Ayahs starting at line 1 | 667 |
| chars-per-covered-line | median 52, p95 82, p99 92, **max 108** |
| Ayahs above 120 chars/line | **0** |

If any ayah had been clamped to fit one page, its characters-per-line would spike far above the distribution (a 300+ char/line row would be unmistakable). None exists: the distribution is tight and plausible throughout.

**Conclusion:** in this edition every ayah is contained within a single page — the page breaks fall on ayah boundaries. The `page_start / line_start / page_end / line_end` model remains correct and is still required, because (a) the schema must be able to express a spanning ayah if a future edition has one, and (b) the single `page` field is not sufficient to *prove* absence — only the statistical check above does.

**OD-025 → Resolved** for this artifact, with the residual note recorded.

---

## 7. Licensing findings

| Question | Finding |
|---|---|
| Licence text in the archive | **None** |
| Licence/terms on the developer page | **None** — no terms page, no licence file, no click-through |
| Redistribution permission | **Not stated** |
| Attribution requirement | **Not stated** |
| Site footer | "جميع الحقوق محفوظة © 2026" (all rights reserved) |
| Usage statement | "يمكن استخدامه في تطوير التطبيقات والبرمجيات بعدة صيغ" — an invitation to use the data in application development, **not** a licence grant |
| Font (`.ttf`) terms | **Not stated** — separate asset, separate question |

**Verdict: BLOCKED (BLOCKER-02 extended).** "Official" is not a licence. An invitation to use data for development does not grant the right to redistribute it inside a shipped application, and the site simultaneously asserts all rights reserved. Until written terms are obtained from KFGQPC (or a licence is identified), the dataset MUST NOT be committed to the repository or bundled into a distributed build.

Development may continue against a locally held copy referenced by path and hash.

---

## 8. Summary of results

| Area | Result |
|---|---|
| Structural integrity (V-01…V-10, V-12) | **PASS** (12/12 assertions) |
| Checksum (V-11) | **RECORDED** — byte-stability across downloads not yet re-tested |
| Edition = `madinah_1405h_hafs_uthmani` | **FAIL** — artifact is 1441H-aligned |
| Page-break representation (OD-025) | **RESOLVED** — none present, no truncation |
| Page height data | **FINDING** — 8 lines on pages 1–2; 2 decoration lines on 57 pages |
| Licence | **BLOCKED** — no terms, all rights reserved |

**Overall: `validation_status = failed`.** No snapshot, no activation.

---

## 9. Decisions required

1. **Edition conflict (blocking).** The canonical edition is 1405H; the only official KFGQPC artifact available encodes 1441H. Options:
   - **(a)** Change the canonical edition to 1441H and accept this artifact (requires an explicit decision — it reverses OD-026).
   - **(b)** Keep 1405H and obtain a 1405H artifact from KFGQPC (may not exist publicly) or from a licensed print.
   - **(c)** Keep 1405H and build the coordinate table in-house from a 1405H source, with its own validation.
2. **Licensing (blocking for bundling).** Obtain written terms from KFGQPC, or accept that the dataset is used locally for development only and is not shipped.
3. **Page-height source (design).** Confirm that `lines_count` is seeded from the edition specification (15, with 8 on pages 1–2) rather than derived from ayah coverage.

---

## 10. Reproducibility

```text
URL      https://download.qurancomplex.gov.sa/resources_dev/kfgqpc_hafs_v30.zip
SHA-256  227e6b1564d980f2bd09c2c35ebfb0330ac268c79a7c247cd1ab665bc635f245
Size     10155774 bytes
Entry    kfgqpc_hafs_v30-data/kfgqpc_hafs_v30.json  (6236 records)
```

The archive is held locally only, outside the repository (`.gitignore` excludes `data/quran/*`).
