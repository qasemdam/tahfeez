<?php

namespace Tests\Unit\Reports;

use App\Domain\Reports\Export\ExportColumn;
use App\Domain\Reports\Export\ExportFormat;
use App\Domain\Reports\Export\ExportPayload;
use App\Domain\Reports\Export\ExportSection;
use App\Domain\Reports\Export\ReportKind;
use App\Domain\Reports\Export\Support\DecimalTotal;
use App\Domain\Reports\Export\Support\ExportText;
use PHPUnit\Framework\TestCase;

/**
 * The formatting layer, in isolation from Laravel and from the report queries.
 */
class ExportValueObjectsTest extends TestCase
{
    public function test_a_missing_value_is_an_em_dash_not_a_zero(): void
    {
        $column = ExportColumn::decimal('المحفوظ', 4);

        $this->assertSame('—', $column->display(null));
        $this->assertSame('—', $column->display(''));
        $this->assertSame('0.0000', $column->display('0.0000'));
    }

    public function test_a_percentage_is_shown_with_its_sign_and_never_capped(): void
    {
        $column = ExportColumn::percentage('نسبة الإنجاز');

        $this->assertSame('150.00%', $column->display('150.00'));
        $this->assertSame('250.00%', $column->display('250.00'));
        $this->assertSame('0.00%', $column->display('0.00'));
    }

    public function test_a_score_never_carries_a_percent_sign(): void
    {
        $column = ExportColumn::score('التقييم المرجح');

        $this->assertSame('87.50', $column->display('87.50'));
        $this->assertSame('0.00', $column->numberFormat());
    }

    public function test_number_formats_keep_the_source_scale(): void
    {
        $this->assertSame('0', ExportColumn::integer('حاضر')->numberFormat());
        $this->assertSame('0.0000', ExportColumn::decimal('المحفوظ', 4)->numberFormat());
        $this->assertSame('0.00', ExportColumn::decimal('المطلوب', 2)->numberFormat());
        $this->assertSame('0.00"%"', ExportColumn::percentage('نسبة')->numberFormat());
    }

    public function test_decimal_totals_do_not_drift(): void
    {
        $this->assertSame('0.3000', DecimalTotal::of(['0.1000', '0.2000']));
        $this->assertSame('4.0000', DecimalTotal::of(['1.7500', '2.2500']));
        $this->assertSame('2.00', DecimalTotal::of(['1.00', '1.00'], 2));
    }

    public function test_a_missing_amount_contributes_nothing_to_a_total(): void
    {
        $this->assertSame('1.5000', DecimalTotal::of(['1.5000', null, '']));
        $this->assertSame('0.0000', DecimalTotal::of([null, '']));
    }

    public function test_the_file_name_is_ascii_and_period_specific(): void
    {
        $payload = new ExportPayload(
            kind: ReportKind::Weekly,
            title: 'تقرير',
            subtitle: 'فترة',
            periodKey: '2026-10-24_2026-10-30',
            meta: [],
            sections: [],
            summary: [],
            generatedAt: '2026-11-05 10:00',
        );

        $this->assertSame(
            'tahfeez-weekly-2026-10-24_2026-10-30.pdf',
            $payload->filename(ExportFormat::Pdf),
        );
        $this->assertSame(
            'tahfeez-weekly-2026-10-24_2026-10-30.xlsx',
            $payload->filename(ExportFormat::Xlsx),
        );
    }

    public function test_an_unsafe_period_key_is_stripped_from_the_file_name(): void
    {
        $payload = new ExportPayload(
            kind: ReportKind::Monthly,
            title: 'تقرير',
            subtitle: 'فترة',
            periodKey: '2026-10/../etc',
            meta: [],
            sections: [],
            summary: [],
            generatedAt: '2026-11-05 10:00',
        );

        $this->assertSame('tahfeez-monthly-2026-10etc.pdf', $payload->filename(ExportFormat::Pdf));
    }

    public function test_the_content_type_and_disposition_belong_to_the_format(): void
    {
        $this->assertSame('application/pdf', ExportFormat::Pdf->contentType());
        $this->assertSame('attachment', ExportFormat::Pdf->disposition());

        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ExportFormat::Xlsx->contentType(),
        );
        $this->assertSame('attachment', ExportFormat::Xlsx->disposition());

        $this->assertSame('text/html; charset=UTF-8', ExportFormat::Html->contentType());
        $this->assertSame('inline', ExportFormat::Html->disposition());
    }

    public function test_a_sheet_title_is_shortened_and_cleaned(): void
    {
        $section = new ExportSection('الحضور/الحفظ: المراجعة [كامل]', [], []);

        $this->assertSame('الحضور الحفظ  المراجعة  كامل', $section->sheetTitle());
        $this->assertSame(31, mb_strlen((new ExportSection(str_repeat('ا', 60), [], []))->sheetTitle()));
    }

    public function test_free_text_isolates_numbers_without_allowing_markup(): void
    {
        $this->assertSame(
            'من <span dir="ltr">2026-10-24</span> إلى <span dir="ltr">2026-10-30</span>',
            ExportText::isolate('من 2026-10-24 إلى 2026-10-30'),
        );

        $this->assertStringNotContainsString(
            '<script>',
            ExportText::isolate('<script>alert(1)</script>'),
        );
    }
}
