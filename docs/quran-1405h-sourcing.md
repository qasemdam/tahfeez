# Sourcing 1405H Data — Official Channel Investigation

**Date:** 2026-10-29 · **Phase:** 0 (research only) · **Status:** **No official 1405H dataset found**
Related: OD-029 · `quran-validation-report.md` · `quran-source-record.md`

---

## 1. The question

Can Tahfeez obtain **official** KFGQPC data for the **1405 AH** Madinah print — not just the text, but page/line coordinates (`ayah → page, line_start, line_end`) — so that memorization amounts are measured against the print the centers actually use?

## 2. The answer

**No. As of 2026-10-29, KFGQPC publishes no 1405H page/line dataset.**

The developer platform exposes exactly **one** Hafs dataset, and it is aligned with the **1441 AH** print (verified: 100% line-break match on juz 30 vs 1405H's 20.9%). No edition selector, no archive of older prints, no metadata field, no API.

This does **not** mean the data does not exist internally — KFGQPC prints the 1405H mushaf and built a desktop publishing application around the Madinah text. It means it is **not published for developers**, and must be requested.

---

## 3. What was searched

All sources below are official KFGQPC properties. GitHub, forums and third-party mirrors were deliberately excluded as evidence of official availability.

| # | Source | URL | What it is | Result |
|---|---|---|---|---|
| 1 | Developer platform | `qurancomplex.gov.sa/quran-dev/` | Official developer data hub | **14 cards / 11 download links.** 9 Quran datasets (Hafs, Warsh, Shubah, Qaloun, Duri, Susi, Bazzi, + smart-device fonts), 2 fonts, 3 tafsir/tajweed docs. **No edition selector. Zero occurrences of "1405" or "1441"** (checked with both Latin and Arabic-Indic digits) |
| 2 | Hafs artifact itself | `download.qurancomplex.gov.sa/resources_dev/kfgqpc_hafs_v30.zip` | 7 formats + font + docx + pdf | **No readme, no manifest, no metadata, no edition field.** docx = plain Quran text only. PDF = 604 pages, text-based, no document metadata |
| 3 | Download host | `download.qurancomplex.gov.sa/resources_dev/` | File host | Directory listing **403 Forbidden** — no browsing, no way to discover unpublished artifacts |
| 4 | Site search (WP REST) | `qurancomplex.gov.sa/wp-json/wp/v2/search?search=1405` | Official site index | 6 results, all corporate/history pages. No dataset |
| 5 | Productions page | `qurancomplex.gov.sa/productions/` | Production & distribution history | "1405" appears **once**: *"بدأ المجمع توزيع إصداراته … منذ عام 1405هـ"* — the **founding/distribution year**, not an edition dataset |
| 6 | Hafs editions sales page | `qurancomplex.gov.sa/sales/hafs/` | Printed editions for sale | Product listing; **no edition year, no data** |
| 7 | Publications & contact | `qurancomplex.gov.sa/epub-info/` | Apps + editions + contact | Links to apps and sales; **no dataset, no edition metadata** |
| 8 | Contact page | `qurancomplex.gov.sa/contact/` | Official contact | Address, phones, `info@qurancomplex.gov.sa` |
| 9 | Content embedding service | `qurancomplex.gov.sa/embed-isdarat/` | Embed mushaf on a website | Display widget for "رواياته وإصداراته" — **no data export, no API, no terms** |
| 10 | Digital publishing site | `nashr.qurancomplex.gov.sa` | مصحف المدينة النبوية للنشر الحاسوبي | Windows desktop app **v3.16, 134 MB**, fonts for Hafs & Warsh for publishing. **Not a dataset**; see §6 |
| 11 | Open data portal | `od.data.gov.sa` | Saudi national open data | **Unreachable from this environment** (proxy 502). Unverified — see §7 |

### Search terms used
`1405`, `1441`, `١٤٠٥`, `١٤٤١`, `۰۱۴۰۵` (mixed digit forms), `طبعة`, `الطبعة`, `إصدار`, `نسخة` — across the developer page, productions, sales, publications and contact pages. Only the founding-year sentence matched.

---

## 4. Why "Hafs + Uthmani + 604 pages" is not enough

The instruction is explicit and the evidence supports it. Three properties are shared by **both** Madinah prints:

- 604 pages ✓ (both)
- 15 lines per page ✓ (both, with 8-line opening pages)
- Hafs ʿan ʿAsim, Uthmani script ✓ (both)

The only discriminating signal is the **line-break pattern**, and that is where the official artifact fails the 1405H test:

| Reference | Same page | Same line span |
|---|---|---|
| 1441H | 100.0% | **100.0%** |
| 1405H | 92.4% | **20.9%** |

So an artifact cannot be accepted as 1405H on the basis of page count, line count, riwayah or publisher. It needs either explicit metadata (absent) or a line-break match (fails).

---

## 5. Why other sources are not "official"

| Source | Why it cannot be the official source |
|---|---|
| `quran-madina-layout` | Third-party MIT project over data derived from `quran-madina-html`; licence conflict (BLOCKER-01); 1405H layout is DigitalKhatt-derived, not KFGQPC-published |
| `quran-madina-html` | Third-party, Waqf GPL 2.0; 1405H layout from DigitalKhatt (MIT code / OFL fonts), not from KFGQPC |
| Tanzil | Text corpus only — no page/line coordinates. Cross-validation source at best |
| `quran-qcf4` (npm, MIT) | KFGQPC **1441H** page data republished by a third party; not 1405H, and not the primary source |

None of these can be presented to a center as "the official 1405H data", and adopting one would contradict ADR-0008 (KFGQPC as the sole master source).

---

## 6. The digital publishing application — a lead, not a source

`nashr.qurancomplex.gov.sa` distributes **مصحف المدينة النبوية للنشر الحاسوبي** v3.16 (Windows, 134 MB), described as providing the Uthmani text for the Madinah mushaf in Hafs and Warsh for publishing purposes.

Why it is **not** treated as a data source here:

1. It is an **application**, not a dataset — its internal format is undocumented.
2. Extracting page/line tables from a binary would be reverse-engineering, not sourcing.
3. Its EULA has not been reviewed; "free to download" ≠ "free to extract and redistribute".
4. Its edition is not stated on the page — the same ambiguity as the JSON artifact.

**Recorded as a lead only.** If it turns out to contain a 1405H coordinate table, using it still requires written permission. It is not a workaround.

---

## 7. What could not be verified

| Item | Reason | Consequence |
|---|---|---|
| `od.data.gov.sa` (national open data) | Not reachable from this environment (proxy 502) | A dataset could exist there. **Must be checked manually before concluding the search is exhaustive** |
| KFGQPC internal archive | Not public | Only a direct request can establish it |
| Sales page product details | Page lists products without edition years | Inconclusive, not negative |

The first item is the only material gap. Everything else was checked directly.

---

## 8. Official contact channels

| Channel | Detail |
|---|---|
| General email | `info@qurancomplex.gov.sa` |
| Sales & marketing | `mrkt@qurancomplex.gov.sa` |
| Contact page | https://qurancomplex.gov.sa/contact/ |
| Sales contact page | https://qurancomplex.gov.sa/sales-workingtime/ |
| Phones | +966 14 8615600 · +966 14 8615700 (sales ext. 2444 / 1931 / 1932 / 1934) |
| Address | Madinah, Tabuk Road, PO Box 6262, 41442, Saudi Arabia |
| Digital publishing contact | https://nashr.qurancomplex.gov.sa/contact/ |

**No message has been sent.** This document prepares the request only.

### 8.1 Request to send (Arabic)

> السلام عليكم ورحمة الله وبركاته،
>
> نحن فريق تقني يعمل على نظام لإدارة مراكز تحفيظ القرآن الكريم، ونعتمد في قياس مقدار الحفظ على مواضع الآيات في مصحف المدينة النبوية.
>
> نطلب رسميًا الحصول على بيانات **مصحف المدينة النبوية — طبعة 1405هـ — رواية حفص عن عاصم — بالرسم العثماني**، على أن تتضمن بيانات الصفحات والأسطر لكل آية (أو أي بيانات رسمية تسمح بإعادة بناء مواضع الآيات على مستوى الصفحة والسطر).
>
> ونرجو بيان شروط الاستخدام والترخيص، وهل يُسمح بتضمين هذه البيانات داخل تطبيق يُوزَّع للمستخدمين.
>
> وتفضلوا بقبول فائق الاحترام.

### 8.2 Technical specification to attach

```text
Edition:            Madinah Mushaf, 1405 AH
Riwayah:            Hafs 'an 'Asim
Script:             Uthmani
Pages:              604
Lines per page:     15 (pages 1–2: 8)
Required per ayah:  surah_no, aya_no, page_start, line_start, page_end, line_end
Optional:           juz, hizb, sajda flag, page line-count table
Format:             CSV or JSON (SQL acceptable)
Metadata required:  edition identifier, dataset version, publication date, checksum
Licence required:   permission to bundle in a distributed application;
                    attribution requirements; redistribution terms; font terms
```

### 8.3 Why this specification

- `page_end` / `line_end` are requested explicitly because the current artifact exposes only one `page` per ayah; the A+ model needs both ends to be unambiguous (`quran-data-model.md` §2.4).
- The page line-count table is requested because pages 1–2 are 8 lines, not 15, and that value must come from the source rather than be assumed.
- Licence terms are requested in the same message so the answer covers bundling, not just download.

---

## 9. Recommendation

1. **Send the request** in §8.1 to `info@` and `mrkt@`, and use the contact form. Ask for a written answer that includes licence terms.
2. **Check `od.data.gov.sa` manually** — it is the one gap in this search.
3. **Do not** substitute a third-party 1405H layout, and **do not** reconstruct coordinates ourselves while the request is open.
4. **Meanwhile**, Phase 2 engineering can proceed against the *verified structural facts* (page heights, decoration lines, measurement formula) using synthetic fixtures — no production dataset required.
5. If KFGQPC answers "not published", OD-029 reduces to: switch the canonical edition to 1441H (cheap, changes the reference print) or build 1405H coordinates in-house (expensive, preserves the decision).

---

## 10. Status of OD-029

**Open — blocked on an external response.**

Narrowed by this investigation:

| Option | Status after research |
|---|---|
| A — switch canonical edition to 1441H | Available, but reverses a product decision; requires explicit approval |
| B — obtain 1405H from KFGQPC | **Not downloadable.** Requires a direct request; no evidence it is published |
| C — build 1405H coordinates in-house | Available but expensive; explicitly forbidden for now by the current instruction |

The decision cannot be closed from public sources. It needs either a reply from KFGQPC or a product call between A and C.
