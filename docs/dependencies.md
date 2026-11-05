# Third-party dependencies and licences

**What the backend ships, under which licence, and what changes when the
deployment model does.** Applies to `backend/` on the
`feature/phase-1-export-foundation` line.

The rule is the same one `BR-QRN-08` applies to Quran data: no permission is
assumed. A dependency that ends up inside a file we hand to a customer carries
obligations, and those obligations are recorded here rather than discovered
during an audit.

Two facts drive everything below:

1. Tahfeez is delivered as a **centrally hosted SaaS**. The backend runs on
   infrastructure we operate; a customer receives HTTP responses and generated
   files, not the program.
2. The only copyleft component in the runtime is the PDF engine.

---

## 1. Runtime dependencies

Installed by `composer install` and loaded by the application in production.

| Package | Version | Licence | Why it is here |
|---|---|---|---|
| `laravel/framework` | 12.70.2 | MIT | The framework |
| `laravel/tinker` | 2.11.1 | MIT | REPL |
| `mpdf/mpdf` | 8.3.1 | **GPL-2.0-only** | PDF rendering, Arabic shaping — see §3 |
| `phpoffice/phpspreadsheet` | 5.10.0 | MIT | XLSX generation — see §6 |

## 2. Development-only dependencies

Never installed in a production image (`composer install --no-dev`), never
shipped.

| Package | Version | Licence |
|---|---|---|
| `fakerphp/faker` | 1.24.1 | MIT |
| `laravel/pint` | 1.30.4 | MIT |
| `laravel/sail` | 1.69.0 | MIT |
| `mockery/mockery` | 1.6.15 | BSD-3-Clause |
| `nunomaduro/collision` | 8.9.5 | MIT |
| `phpunit/phpunit` | 11.5.57 | BSD-3-Clause |
| `smalot/pdfparser` | 2.13.1 | LGPL-3.0 |

`smalot/pdfparser` is the only LGPL component in the tree, and it is used by the
export tests alone (they parse the generated PDF back and read it). It is a dev
dependency precisely so the LGPL obligation never reaches a deployment.

---

## 3. mPDF — GPL-2.0-only

| | |
|---|---|
| Package | `mpdf/mpdf` 8.3.1 |
| Licence | **GNU General Public License v2.0 only** (`GPL-2.0-only`) |
| Source | <https://github.com/mpdf/mpdf> · installed via Packagist |
| Licence text | `backend/vendor/mpdf/mpdf/LICENSE.txt` |
| Declared in | `backend/composer.json` → `"mpdf/mpdf": "^8.3"` |

**Why it is here.** It renders the print view to PDF and it shapes Arabic: it
applies the bidi algorithm, selects contextual glyph forms and embeds a TrueType
subset with a `ToUnicode` map. The alternative that was evaluated first,
`tecnickcom/tcpdf` 7, is a compatibility facade whose font data is not installed
— it fails on the first page (`unable to read file: helvetica.json`) and cannot
embed an Arabic face at all.

**GPL-2.0 is a copyleft licence.** Its obligations attach to *distributing* the
program (or a work based on it): making the corresponding source available under
the same licence. Running the program on our own servers and serving its output
over HTTP is not distribution.

### 3.1 Current posture — centrally hosted SaaS

The adoption of mPDF is **tied to the central hosted model**. Under that model:

- The backend is operated by us and is not distributed to customers.
- A customer receives HTTP responses and generated files (PDF, XLSX, HTML),
  never the program itself.
- A generated report is output of the program, not a derivative work of the
  engine, so the report files carry no copyleft obligation.

This is the working assumption recorded for the SaaS model. It is an engineering
record, not a legal opinion.

### 3.2 What changes if the backend is distributed

**Any future distribution of a Backend copy — self-hosted, on-premise,
white-label, a container image handed to a customer, or a desktop bundle —
requires a licence review before distribution, not after.** Under GPL-2.0 the
options are:

1. **Comply.** Ship the backend's corresponding source under GPL-2.0 with the
   distributed copy. This reaches the whole backend, not just the export module.
2. **Replace the engine** with a non-copyleft renderer and keep the backend
   closed. `tecnickcom/tcpdf` 6.x is LGPL-3.0-or-later and would need a spike to
   confirm Arabic shaping with a separately licensed font; Gotenberg (MIT, in
   front of Chromium) moves rendering to a service.
3. **Keep the engine server-side only** and expose rendering as a service that
   the distributed component calls, so no GPL code ships.

Whichever path is chosen, the change is an ADR, not a silent dependency bump.
`docs/decisions/` is where that decision belongs.

---

## 4. DejaVu Sans — Bitstream Vera licence

| | |
|---|---|
| File | `backend/vendor/mpdf/mpdf/ttfonts/DejaVuSans.ttf` (+ `-Bold`) |
| Licence | Bitstream Vera Fonts Copyright; DejaVu changes are public domain |
| Licence text | `backend/vendor/mpdf/mpdf/ttfonts/DejaVuinfo.txt` |
| Ships with | `mpdf/mpdf` — not vendored by this repository |
| Used by | `PdfReportRenderer` as the pinned face (`default_font`) |

**Why this face.** mPDF's `autoLangToFont` silently switches Arabic runs to
*XB Riyaz*, a font bundled with the library whose own name table declares the
GNU General Public License (see §5). DejaVu ships with the same package under a
permissive licence and carries the Arabic GSUB tables mPDF needs to shape the
script, so the pipeline stays inside one dependency and one licence family.

**What the licence permits.** The Bitstream Vera grant allows use, copying,
merging, publishing, distribution and embedding, including as part of a larger
package. Conditions that apply to us:

- The copyright and trademark notice must travel with copies of the font
  software. It does: `DejaVuinfo.txt` sits next to the `.ttf` inside the mPDF
  package, and `composer install` brings both.
- The fonts may not be sold on their own — they are not a product here.
- A modified version must be renamed. We do not modify the font.

**How it is used.** Only as an embedded subset inside generated PDFs
(`MPDFAA+DejaVuSans`, `Type0`/`Identity-H`, with a `ToUnicode` map). The `.ttf`
is never served, never bundled into the mobile app and never offered for
download.

**If mPDF is ever replaced** (§3.2), the font does not have to go with it: vendor
`DejaVuSans.ttf` and `DejaVuinfo.txt` together under `backend/resources/fonts/`
and point the new engine at them. The notice is the condition, not the engine.

---

## 5. Fonts deliberately not used

| Font | Licence | Why not |
|---|---|---|
| **XB Riyaz** (`vendor/mpdf/mpdf/ttfonts/XB Riyaz.ttf`) | **GPL** — declared in the font's own `name` table (`nameID 13`: "GNU General Public License"; `nameID 0`: "Copyright (c) Irmug.org, 2008") | Copyleft font embedded in customer-facing documents. mPDF would select it automatically for Arabic; `autoLangToFont` is therefore **off** |
| KFGQPC Hafs / UthmanTN | No licence file shipped | Blocked by `open-decisions.md` BLOCKER-02; not bundled |
| Amiri | OFL-1.1 | Typographically better Arabic, but a new asset (~430 KB + notice). **Not adopted** — the decision is the product owner's |

---

## 6. Other components touched by exports

`phpoffice/phpspreadsheet` (MIT) writes the XLSX file. MIT imposes no condition
beyond attribution in the distributed source, and the library is not modified.

The print view and the HTML export use no third-party code: Blade, the framework
and inline CSS.

---

## 7. Adding a dependency

Before a package enters `composer.json`:

1. Read its `license` field and its licence file — not a blog post or a font
   aggregator. XB Riyaz is described as OFL on several sites; its own metadata
   says GPL.
2. Ask whether it ends up **inside a file we hand to a customer**, or only
   inside the program.
3. If copyleft: record it here, state the deployment assumption it relies on,
   and note what distribution would trigger.
4. If it bundles assets (fonts, data, images), record the asset licence
   separately — a permissive library can ship a copyleft asset.
5. Add the entry to §1 or §2 in the same commit that changes `composer.json`.

## 8. Review triggers

This document must be revisited when any of the following happens:

- The delivery model changes from central hosting to **distribution of the
  backend** (self-hosted, on-premise, white-label, image hand-off).
- The PDF engine or the Arabic face changes.
- A new asset is bundled into generated documents.
- A dependency's licence changes on upgrade (check `composer audit` and the
  package's `LICENSE` on every major bump).
