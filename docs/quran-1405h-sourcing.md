# 1405H Data — Official Request Package

**Status:** prepared, **not sent** · 2026-10-29 · Phase 0
**Purpose:** obtain, in writing, the page/line coordinates and the usage terms for the Madinah print Tahfeez is pinned to.
**Related:** OD-029 · `quran-validation-report.md` · `quran-source-record.md` · ADR-0008

---

## Part A — Why this request exists (summary of evidence)

A full sweep of KFGQPC's official channels on 2026-10-29 established that **no page/line dataset for the reference print is published**.

| Checked | Outcome |
|---|---|
| Developer platform `qurancomplex.gov.sa/quran-dev/` | One Hafs dataset; no edition selector; the strings "1405"/"1441" do not appear (checked with Latin and Arabic-Indic digits) |
| The published artifact, all 7 formats + docx + pdf | No edition metadata of any kind |
| `download.qurancomplex.gov.sa/resources_dev/` | Directory listing 403 — unpublished files cannot be discovered |
| Official site search, productions, sales, publications | Only the complex's **founding year** (1405 AH), not a dataset |
| `nashr.qurancomplex.gov.sa` | Desktop publishing application v3.16 — not a dataset; EULA unreviewed |
| `od.data.gov.sa` | Unreachable from this environment — manual check pending |

The published Hafs artifact was measured against both known layouts (juz 30, 564 ayahs):

| Reference | Same page | Same line span |
|---|---|---|
| 1441H | 100.0% | **100.0%** |
| 1405H | 92.4% | **20.9%** |

**Conclusion:** the published data describes the 1441 AH print. The reference print must be requested directly.

---

## Part B — The request package

Three artefacts, to be used together:

| # | Artefact | Use |
|---|---|---|
| B.1 | Short email | First contact; short enough to be read |
| B.2 | Formal letter | Official correspondence, signed and stamped if possible |
| B.3 | Technical annex | Attached to both; the specification they can act on |

### B.1 — Short email (ready to send)

**Subject:** طلب بيانات مواضع الآيات (الصفحة والسطر) لمصحف المدينة النبوية — التخطيط المرجعي

> السلام عليكم ورحمة الله وبركاته،
>
> نحن فريق تقني يعمل على نظام «تحفيظ» لإدارة مراكز تحفيظ القرآن الكريم. يقيس النظام مقدار حفظ الطالب بمواضع الآيات في المصحف (الصفحة ورقم السطر)، وليس بعدد الآيات أو باسم السورة.
>
> نرجو التكرم بتزويدنا — أو بإرشادنا إلى الجهة المختصة — ببيانات مواضع الآيات للمصحف المدينة النبوية بالتخطيط المرجعي، رواية حفص عن عاصم، بالرسم العثماني، 604 صفحات، 15 سطرًا في الصفحة (والصفحتان الأولى والثانية 8 أسطر)، وهو التخطيط الذي يُشار إليه في بعض المصادر التقنية بـ«طبعة 1405هـ». ونرجو بيان المسمّى الرسمي المعتمد لديكم لهذا الإصدار.
>
> المطلوب لكل آية: رقم السورة، رقم الآية، صفحة البداية، سطر البداية، صفحة النهاية، سطر النهاية، مع أي بيانات وصفية تثبت الطبعة.
>
> ونرجو كذلك إفادتنا كتابيًا بشروط الاستخدام والترخيص، وهل يُسمح بتضمين هذه البيانات داخل تطبيق يُوزَّع للمستخدمين.
>
> المواصفات الفنية التفصيلية وأسئلة الترخيص مرفقة في ملف واحد.
>
> وتفضلوا بقبول فائق الاحترام.
>
> [الاسم] — [الصفة] — [البريد] — [الهاتف]

### B.2 — Formal letter (Arabic, official)

> بسم الله الرحمن الرحيم
>
> **إلى:** معالي المشرف العام على مجمع الملك فهد لطباعة المصحف الشريف — حفظه الله
> **نسخة إلى:** الإدارة المعنية بخدمات المطورين / إدارة تقنية المعلومات
>
> **الموضوع:** طلب الحصول على بيانات مواضع الآيات (الصفحة والسطر) لمصحف المدينة النبوية ذي التخطيط المرجعي، وبيان شروط الاستخدام
>
> السلام عليكم ورحمة الله وبركاته، وبعد:
>
> ننوه بجهود مجمع الملك فهد لطباعة المصحف الشريف في خدمة كتاب الله تعالى، وبما يسّره من منصة للمطورين وبيانات حاسوبية أسهمت في خدمة التطبيقات القرآنية. ونسأل الله أن يجعل ذلك في موازين حسناتكم.
>
> نحن فريق تطوير نظام **«تحفيظ»** لإدارة مراكز تحفيظ القرآن الكريم، وهو نظام يهدف إلى مساعدة المحفظين ومديري المراكز على متابعة حفظ الطلاب ومراجعتهم وتسميعهم وحضورهم، وحساب مقدار الحفظ أسبوعيًا بدقة، وإصدار التقارير.
>
> ويقوم النظام على مبدأ أساسي، هو أن يُقاس مقدار الحفظ بمواضع الآيات في المصحف — الصفحة ورقم السطر — لا بعدد الآيات ولا باسم السورة. ولا يمكن تحقيق ذلك إلا ببيانات مواضع الآيات المرتبطة بالتخطيط المطبوع للمصحف الذي بين يدي الطلاب والمحفظين، لأن فواصل الأسطر تختلف بين إصدارات المصحف المطبوعة، ويترتب على ذلك اختلاف في مقدار الحفظ المحتسب.
>
> ولما كان المصحف المطبوع في المجمع له أكثر من إصدار بتخطيطات مختلفة، فإننا نحرص على تحديد الإصدار المقصود **بخصائصه** لا بمسمّاه، وذلك على النحو الآتي:
>
> | الخاصية | الوصف |
> |---|---|
> | المصحف | مصحف المدينة النبوية |
> | الرواية | حفص عن عاصم |
> | الرسم | الرسم العثماني |
> | عدد الصفحات | 604 صفحات |
> | عدد الأسطر | 15 سطرًا في الصفحة، والصفحتان الأولى والثانية 8 أسطر |
> | التخطيط | التخطيط المرجعي القديم، الذي يُشار إليه في بعض المصادر التقنية بـ«طبعة 1405هـ» |
>
> ونؤكد أننا لا نطلب إعادة إصدار نص القرآن الكريم، فهو متوفر ولله الحمد، وإنما نطلب **بيانات المواضع والتخطيط** (أي: موضع بداية الآية ونهايتها على مستوى الصفحة والسطر)، مع ما يثبت هوية الإصدار.
>
> **وعليه، نرجو التكرم بما يلي:**
>
> 1. تزويدنا ببيانات مواضع الآيات للإصدار الموصوف أعلاه، وفق المواصفات الفنية المرفقة (الملحق أ)، أو إرشادنا إلى الجهة المختصة التي يمكن أن تزوّدنا بها.
> 2. بيان **المسمّى الرسمي المعتمد لديكم** لهذا الإصدار، حتى نعتمده في توثيقنا بدلًا من التسمية المتداولة «1405هـ».
> 3. إفادتنا كتابيًا بشروط استخدام هذه البيانات وترخيصها، وفق الأسئلة المرفقة (الملحق ب).
> 4. وفي حال تعذّر تزويدنا بالبيانات، نرجو التكرم ببيان ذلك كتابيًا، وإفادتنا إن كانت هناك إجراءات أو نماذج معتمدة لطلبها.
>
> ونضع أنفسنا رهن إشارتكم لتقديم أي توضيح تقني، ونلتزم بالإشارة إلى مصدر البيانات وبعدم تعديل نص القرآن الكريم بأي حال.
>
> وتفضلوا بقبول فائق الاحترام والتقدير.
>
> **مقدّم الطلب:** [الاسم]
> **الصفة:** [المسؤول عن المشروع]
> **الجهة:** [اسم الجهة]
> **البريد الإلكتروني:** [البريد]
> **الهاتف:** [الرقم]
> **التاريخ:** [التاريخ]
> **المرفقات:** الملحق (أ) المواصفات الفنية — الملحق (ب) أسئلة الترخيص

### B.3 — Technical annex (الملحق أ)

> **الملحق (أ): المواصفات الفنية المطلوبة**
>
> **1. الغرض**
> حساب مقدار الحفظ من نطاق آيات (من آية إلى آية) بتحويله إلى مقدار بالصفحات، اعتمادًا على التخطيط المطبوع، مع إمكانية التحقق البرمجي من صحة المواضع.
>
> **2. الحقول المطلوبة لكل آية**
>
> | الحقل | النوع | الوصف |
> |---|---|---|
> | `sura_no` | عدد صحيح | رقم السورة 1–114 |
> | `aya_no` | عدد صحيح | رقم الآية داخل السورة |
> | `page_start` | عدد صحيح | الصفحة التي تبدأ فيها الآية |
> | `line_start` | عدد صحيح | رقم السطر الذي تبدأ فيه الآية داخل الصفحة |
> | `page_end` | عدد صحيح | الصفحة التي تنتهي فيها الآية |
> | `line_end` | عدد صحيح | رقم السطر الذي تنتهي فيه الآية داخل الصفحة |
>
> **3. حقول اختيارية مفيدة**
> `jozz` · `hizb` · `rub` · علامة السجدة · اسم السورة بالعربية والإنجليزية.
>
> **4. جدول الصفحات (مهم)**
> نرجو تزويدنا بجدول لارتفاعات الصفحات: رقم الصفحة وعدد أسطرها الفعلية. وذلك لأن الصفحتين الأولى والثانية في هذا التخطيط 8 أسطر لا 15، ولا يصح استنتاج ذلك من البيانات.
>
> **5. البيانات الوصفية (إلزامية لتوثيق النسخة)**
> المسمّى الرسمي للإصدار · رقم/تاريخ الإصدار · تاريخ النشر · عدد الصفحات · عدد الآيات · بصمة تحقق (SHA-256) للملف.
>
> **6. الصيغة**
> CSV أو JSON (وSQL مقبول). الترميز UTF-8. أي صيغة أخرى متاحة لديكم مقبولة، وسنتولى التحويل.
>
> **7. معايير القبول التي سنطبقها داخليًا**
> 114 سورة · 6236 آية · ترقيم صفحات متصل 1–604 · لا آيات مفقودة أو مكررة · ترتيب صحيح · `line_start ≤ line_end` · مطابقة عينات محددة مع النسخة المطبوعة · تحقق من البصمة.
>
> **8. بيئة الاستخدام**
> خادم PostgreSQL، وتطبيق Flutter لأندرويد وiOS والويب، مع تخزين محلي للعمل دون اتصال. ولا يُعدَّل نص القرآن الكريم، ولا تُشتق منه نسخة مختلفة.

### B.4 — Licensing questions (الملحق ب)

> **الملحق (ب): أسئلة الترخيص والاستخدام**
>
> نرجو التكرم بالإجابة كتابيًا على الأسئلة التالية، لأننا لا نستطيع الاعتماد على البيانات في منتج يُوزَّع دون موافقة موثقة:
>
> 1. هل يُسمح باستخدام البيانات داخل تطبيق **تجاري**؟ وداخل تطبيق **غير تجاري**؟ وما الفرق بينهما في الشروط؟
> 2. هل يُسمح **بإعادة توزيع البيانات داخل التطبيق** الذي نوزّعه على المستخدمين؟
> 3. هل يُسمح **بتخزين البيانات محليًا** على جهاز المستخدم (للعمل دون اتصال)؟
> 4. هل يُسمح **بإنشاء نسخة مشتقة** منها (جداول أو فهارس داخلية داخل نظامنا)؟
> 5. هل يُسمح بتخزين البيانات في قاعدة بيانات **PostgreSQL** على خادمنا؟
> 6. هل يُسمح باستخدامها في تطبيق **Flutter** يعمل على Android وiOS والويب؟
> 7. ما **شروط الإسناد (Attribution)** المطلوبة؟ وما الصيغة التي تفضلونها؟
> 8. ما شروط استخدام **الخطوط** إن كانت مطلوبة مع البيانات؟
>
> **أسئلة إضافية:**
>
> 9. هل يلزم توقيع اتفاقية أو تعبئة نموذج معتمد؟ ومن الجهة المخوّلة بذلك؟
> 10. هل توجد قيود على تعديل البيانات أو على طريقة عرضها؟
> 11. هل هناك متطلبات خاصة عند عرض المصحف في تطبيق (مثل الإشارة إلى المجمع أو عدم إجراء تعديلات على الرسم)؟
>
> **ملاحظة:** في حال عدم وضوح أي بند، سنعتبره غير مسموح به حتى يرد ما يفيد خلاف ذلك.

---

## Part C — Sending

### C.1 — Contact channels (verified 2026-10-29)

| Channel | Detail |
|---|---|
| General email | `info@qurancomplex.gov.sa` |
| Sales & marketing | `mrkt@qurancomplex.gov.sa` |
| Contact form | https://qurancomplex.gov.sa/contact/ |
| Sales contact page | https://qurancomplex.gov.sa/sales-workingtime/ |
| Phones | +966 14 8615600 · +966 14 8615700 (sales ext. 2444 / 1931 / 1932 / 1934) |
| Address | المدينة المنورة، طريق تبوك، ص.ب 6262، الرمز البريدي 41442 |
| Digital publishing | https://nashr.qurancomplex.gov.sa/contact/ |

### C.2 — Checklist before sending

```text
[ ] Fill every [placeholder] in B.1 and B.2 (name, role, entity, email, phone, date)
[ ] Attach B.3 and B.4 as a single PDF named: Tahfeez-Quran-Data-Request.pdf
[ ] Send to info@ and mrkt@, and submit via the contact form
[ ] Record: date sent, channel, reference number if any
[ ] Keep the sent copy and attachments unchanged for the record
[ ] Diary a follow-up after 14 days
```

**Nothing has been sent. This document prepares the request only.**

### C.3 — How to handle the reply

| Reply | Action |
|---|---|
| Data + written licence terms | Run the validation gate (`quran-validation-plan.md` V-01…V-12), then activation |
| Data, no licence terms | Import locally for development only; **bundling stays blocked** (OD-005) |
| "Not published" / refusal | OD-029 stays open; the choice becomes: switch edition (product decision) or build coordinates in-house |
| No reply after follow-up | Escalate through the formal correspondence channel; do not substitute a third-party source |

---

## Part D — Status

**OD-029 — Open / Blocked.**

- **Reason:** no published official artifact has been proven to represent the required edition.
- **Next action:** official written inquiry to KFGQPC (this document).
- **Constraint:** 1441H MUST NOT be adopted as a substitute without a new product decision. Third-party sources are not accepted. Building the coordinates in-house is out of scope for now.
