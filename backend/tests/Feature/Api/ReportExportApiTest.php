<?php

namespace Tests\Feature\Api;

use App\Domain\Groups\Actions\AddStudentToGroup;
use App\Domain\Groups\Models\Group;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Smalot\PdfParser\Parser;
use Tests\Support\ReportExportScenario;
use Tests\TestCase;

/**
 * Report exports: PDF, XLSX and the print view.
 *
 * The assertions check the produced file, not only the HTTP envelope: the PDF
 * is parsed back and the workbook is loaded and read cell by cell.
 */
class ReportExportApiTest extends TestCase
{
    use RefreshDatabase;
    use ReportExportScenario;

    private const WEEK = 'week_key=2026-10-26';

    /**
     * @return array<string, mixed>
     */
    private function pdf(string $contents): array
    {
        $path = tempnam(sys_get_temp_dir(), 'tahfeez-pdf-');
        file_put_contents($path, $contents);

        try {
            $document = (new Parser)->parseFile($path);

            return [
                'text' => $document->getText(),
                'pages' => count($document->getPages()),
            ];
        } finally {
            @unlink($path);
        }
    }

    private function workbook(string $contents): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'tahfeez-xlsx-');
        file_put_contents($path, $contents);

        try {
            return IOFactory::load($path);
        } finally {
            @unlink($path);
        }
    }

    private function export(string $url, User $user): TestResponse
    {
        // The API is stateless JSON in and out; asking for JSON keeps a
        // validation failure a 422 instead of a redirect.
        return $this->withHeaders([...$this->auth($user), 'Accept' => 'application/json'])->get($url);
    }

    public function test_a_guest_cannot_export(): void
    {
        $this->get('/api/v1/reports/weekly/export?'.self::WEEK.'&format=pdf')
            ->assertUnauthorized();
    }

    public function test_the_weekly_report_exports_to_a_valid_pdf(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-25', '1.5000');

        $response = $this->export('/api/v1/reports/weekly/export?'.self::WEEK.'&format=pdf', $s['manager'])
            ->assertOk();

        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader(
            'Content-Disposition',
            'attachment; filename="tahfeez-weekly-2026-10-24_2026-10-30.pdf"',
        );

        $contents = (string) $response->getContent();

        $this->assertStringStartsWith('%PDF-', $contents);
        $this->assertStringContainsString('%%EOF', $contents);

        $pdf = $this->pdf($contents);

        $this->assertSame(1, $pdf['pages']);
        $this->assertNotSame('', trim($pdf['text']));
    }

    public function test_the_pdf_contains_the_report_values_and_arabic_text(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-25', '1.5000');
        $this->review($s, '2026-10-26', '0.7500');
        $this->recitation($s, '2026-10-27', 'good', 2);
        $this->attendance($s, '2026-10-25', 'present');

        $contents = (string) $this->export('/api/v1/reports/weekly/export?'.self::WEEK.'&format=pdf', $s['manager'])
            ->assertOk()
            ->getContent();

        $text = $this->pdf($contents)['text'];

        // Latin values survive bidi: dates and decimals are wrapped in dir="ltr".
        $this->assertStringContainsString('2026-10-24', $text);
        $this->assertStringContainsString('2026-10-30', $text);
        $this->assertStringContainsString('1.5000', $text);
        $this->assertStringContainsString('150.00%', $text);
        $this->assertStringContainsString('0.7500', $text);

        // Arabic is really shaped, not dropped: the text uses the presentation
        // forms block (contextual glyphs) and the PDF embeds a TrueType subset.
        $this->assertMatchesRegularExpression('/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
        $this->assertStringContainsString('/FontFile2', $contents);
        $this->assertStringNotContainsString("\u{FFFD}", $text);
    }

    public function test_the_weekly_report_exports_to_a_readable_xlsx(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-25', '1.5000');
        $this->memorization($s, '2026-10-26', '0.2500');
        $this->review($s, '2026-10-26', '0.7500');
        $this->recitation($s, '2026-10-27', 'good', 2);
        $this->attendance($s, '2026-10-25', 'present');
        $this->attendance($s, '2026-10-26', 'late');

        $response = $this->export('/api/v1/reports/weekly/export?'.self::WEEK.'&format=xlsx', $s['manager'])
            ->assertOk();

        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $response->assertHeader(
            'Content-Disposition',
            'attachment; filename="tahfeez-weekly-2026-10-24_2026-10-30.xlsx"',
        );

        $book = $this->workbook((string) $response->getContent());

        $this->assertSame(
            ['ملخص', 'الحضور والحفظ والمراجعة', 'التسميع', 'التقييم الأسبوعي (لقطة محفوظة)'],
            $book->getSheetNames(),
        );

        $sheet = $book->getSheetByName('الحضور والحفظ والمراجعة');
        $this->assertNotNull($sheet);
        $this->assertTrue($sheet->getRightToLeft());

        $this->assertSame('الطالب', $sheet->getCell('A1')->getValue());
        $this->assertSame('المحفوظ (صفحة)', $sheet->getCell('I1')->getValue());
        $this->assertSame('نسبة الإنجاز', $sheet->getCell('J1')->getValue());

        $this->assertSame('عبد الله محمد', $sheet->getCell('A2')->getValue());
        $this->assertSame(1.75, (float) $sheet->getCell('I2')->getValue());
        $this->assertSame('1.7500', $sheet->getCell('I2')->getFormattedValue());
        $this->assertSame('0.0000', $sheet->getStyle('I2')->getNumberFormat()->getFormatCode());
        $this->assertSame('175.00%', $sheet->getCell('J2')->getFormattedValue());
        $this->assertSame(1, (int) $sheet->getCell('D2')->getValue());
        $this->assertSame(1, (int) $sheet->getCell('E2')->getValue());

        $recitation = $book->getSheetByName('التسميع');
        $this->assertSame('good: 1', $recitation->getCell('F2')->getValue());
        $this->assertSame(2, (int) $recitation->getCell('C2')->getValue());
    }

    public function test_the_weekly_report_exports_to_a_printable_html_page(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-25', '1.5000');

        $response = $this->export('/api/v1/reports/weekly/export?'.self::WEEK.'&format=html', $s['manager'])
            ->assertOk();

        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->assertHeader(
            'Content-Disposition',
            'inline; filename="tahfeez-weekly-2026-10-24_2026-10-30.html"',
        );

        $html = (string) $response->getContent();

        $this->assertStringContainsString('<html lang="ar" dir="rtl">', $html);
        $this->assertStringContainsString('@media print', $html);
        $this->assertStringContainsString('class="toolbar no-print"', $html);
        $this->assertStringContainsString('size: A4 landscape', $html);
        $this->assertStringContainsString('تقرير المركز الأسبوعي', $html);
        $this->assertStringContainsString('عبد الله محمد', $html);
        $this->assertStringContainsString('150.00%', $html);

        // A numeric value is isolated so bidi cannot reorder it.
        $this->assertStringContainsString('<span dir="ltr">1.5000</span>', $html);
    }

    public function test_html_is_the_default_format(): void
    {
        $s = $this->scenario();

        $this->export('/api/v1/reports/weekly/export?'.self::WEEK, $s['manager'])
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function test_an_unsupported_format_is_rejected(): void
    {
        $s = $this->scenario();

        $this->export('/api/v1/reports/weekly/export?'.self::WEEK.'&format=csv', $s['manager'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['format']);
    }

    public function test_the_weekly_export_keeps_the_report_period_rules(): void
    {
        $s = $this->scenario();

        $this->export('/api/v1/reports/weekly/export?format=pdf', $s['manager'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['week_key']);

        $this->export(
            '/api/v1/reports/weekly/export?week_key=2026-10-26&week_start_date=2026-10-24&format=pdf',
            $s['manager'],
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['week_start_date']);
    }

    public function test_a_week_start_date_is_honoured_in_the_file_name(): void
    {
        $s = $this->scenario();

        $this->export('/api/v1/reports/weekly/export?week_start_date=2026-10-24&format=xlsx', $s['manager'])
            ->assertOk()
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename="tahfeez-weekly-2026-10-24_2026-10-30.xlsx"',
            );
    }

    public function test_the_monthly_report_exports_to_pdf_xlsx_and_html(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-05', '2.0000');

        $pdf = $this->export('/api/v1/reports/monthly/export?month=2026-10&format=pdf', $s['manager'])
            ->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');
        $pdf->assertHeader(
            'Content-Disposition',
            'attachment; filename="tahfeez-monthly-2026-10.pdf"',
        );
        $this->assertStringStartsWith('%PDF-', (string) $pdf->getContent());

        $xlsx = $this->export('/api/v1/reports/monthly/export?month=2026-10&format=xlsx', $s['manager'])
            ->assertOk();
        $xlsx->assertHeader(
            'Content-Disposition',
            'attachment; filename="tahfeez-monthly-2026-10.xlsx"',
        );

        $book = $this->workbook((string) $xlsx->getContent());
        $sheet = $book->getSheetByName('الحضور والحفظ والمراجعة');
        $this->assertSame(2.0, (float) $sheet->getCell('I2')->getValue());

        $this->export('/api/v1/reports/monthly/export?month=2026-10&format=html', $s['manager'])
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename="tahfeez-monthly-2026-10.html"');
    }

    public function test_the_monthly_report_requires_a_month(): void
    {
        $s = $this->scenario();

        $this->export('/api/v1/reports/monthly/export?format=pdf', $s['manager'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month']);
    }

    public function test_the_dashboard_exports_to_pdf_and_xlsx(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-05', '2.0000');
        $this->attendance($s, '2026-10-05', 'present');

        $pdf = $this->export('/api/v1/dashboard/export?month=2026-10&format=pdf', $s['manager'])
            ->assertOk();
        $pdf->assertHeader(
            'Content-Disposition',
            'attachment; filename="tahfeez-dashboard-2026-10.pdf"',
        );
        $this->assertStringStartsWith('%PDF-', (string) $pdf->getContent());

        $xlsx = $this->export('/api/v1/dashboard/export?month=2026-10&format=xlsx', $s['manager'])
            ->assertOk();

        $book = $this->workbook((string) $xlsx->getContent());
        $this->assertSame(['ملخص', 'الملخص العام', 'الحضور', 'النشاط والتقييم'], $book->getSheetNames());

        $activity = $book->getSheetByName('النشاط والتقييم');
        $this->assertSame('صفحات الحفظ', $activity->getCell('A2')->getValue());
        $this->assertSame('2.0000', $activity->getCell('B2')->getValue());

        $students = $book->getSheetByName('الملخص العام');
        $this->assertSame('1', $students->getCell('B2')->getValue());
    }

    public function test_the_exported_values_match_the_report_response(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-25', '1.5000');
        $this->review($s, '2026-10-26', '0.7500');
        $this->recitation($s, '2026-10-27', 'good', 2);
        $this->attendance($s, '2026-10-25', 'present');

        $json = $this->withHeaders($this->auth($s['manager']))
            ->getJson('/api/v1/reports/weekly?'.self::WEEK)
            ->assertOk()
            ->json('data.students.0');

        $book = $this->workbook((string) $this->export(
            '/api/v1/reports/weekly/export?'.self::WEEK.'&format=xlsx',
            $s['manager'],
        )->assertOk()->getContent());

        $sheet = $book->getSheetByName('الحضور والحفظ والمراجعة');

        $this->assertSame($json['student']['name'], $sheet->getCell('A2')->getValue());
        $this->assertSame($json['group']['name'], $sheet->getCell('B2')->getValue());
        $this->assertSame($json['teacher']['name'], $sheet->getCell('C2')->getValue());
        $this->assertSame($json['memorization']['actual_pages'], $sheet->getCell('I2')->getFormattedValue());
        $this->assertSame(
            $json['memorization']['achievement_percentage'].'%',
            $sheet->getCell('J2')->getFormattedValue(),
        );
        $this->assertSame($json['review']['pages'], $sheet->getCell('L2')->getFormattedValue());
    }

    public function test_the_totals_row_is_the_sum_of_the_rows(): void
    {
        $s = $this->scenario();
        $second = $this->addStudent($s);

        $this->memorization($s, '2026-10-25', '1.5000');
        $this->memorization($s, '2026-10-25', '2.2500', $second);
        $this->review($s, '2026-10-26', '0.7500');
        $this->attendance($s, '2026-10-25', 'present');
        $this->attendance($s, '2026-10-26', 'present');
        $this->attendance($s, '2026-10-25', 'late', $second);

        $book = $this->workbook((string) $this->export(
            '/api/v1/reports/weekly/export?'.self::WEEK.'&format=xlsx',
            $s['manager'],
        )->assertOk()->getContent());

        $sheet = $book->getSheetByName('الحضور والحفظ والمراجعة');

        // Header on row 1, two students on rows 2–3, totals on row 4.
        $this->assertSame('الإجمالي', $sheet->getCell('A4')->getValue());
        $this->assertSame(2, (int) $sheet->getCell('D4')->getValue());
        $this->assertSame(1, (int) $sheet->getCell('E4')->getValue());
        $this->assertSame('3.7500', $sheet->getCell('I4')->getFormattedValue());
        $this->assertSame('2.00', $sheet->getCell('H4')->getFormattedValue());
        $this->assertSame('0.7500', $sheet->getCell('L4')->getFormattedValue());

        // No aggregate achievement: an average of uncapped ratios is undefined,
        // so the cell is left empty rather than filled with a made-up figure.
        $this->assertNull($sheet->getCell('J4')->getValue());

        $summary = $book->getSheetByName('ملخص');
        $this->assertSame('3.7500', $summary->getCell('B10')->getValue());
    }

    public function test_an_achievement_above_one_hundred_percent_is_exported_uncapped(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-25', '2.5000');

        $book = $this->workbook((string) $this->export(
            '/api/v1/reports/weekly/export?'.self::WEEK.'&format=xlsx',
            $s['manager'],
        )->assertOk()->getContent());

        $sheet = $book->getSheetByName('الحضور والحفظ والمراجعة');

        $this->assertSame('250.00%', $sheet->getCell('J2')->getFormattedValue());
        $this->assertSame('250.00%', $sheet->getCell('J2')->getFormattedValue());
    }

    public function test_the_weighted_score_is_exported_in_its_own_table(): void
    {
        $s = $this->scenario();
        $this->profileVersion($s);
        $this->memorization($s, '2026-10-25', '1.5000');

        $created = $this->withHeaders($this->auth($s['manager']))->postJson('/api/v1/weekly-evaluations', [
            'student_id' => $s['student']->id,
            'week_date' => '2026-10-26',
        ])->assertCreated();

        $book = $this->workbook((string) $this->export(
            '/api/v1/reports/weekly/export?'.self::WEEK.'&format=xlsx',
            $s['manager'],
        )->assertOk()->getContent());

        $activity = $book->getSheetByName('الحضور والحفظ والمراجعة');
        $evaluation = $book->getSheetByName('التقييم الأسبوعي (لقطة محفوظة)');

        // Achievement lives in the activity table, the score in the evaluation
        // table: they are never written into the same column.
        $this->assertSame('150.00%', $activity->getCell('J2')->getFormattedValue());
        $this->assertSame('التقييم المرجح (0-100)', $evaluation->getCell('B1')->getValue());

        $this->assertSame(
            (string) $created->json('data.weighted_score'),
            $evaluation->getCell('B2')->getFormattedValue(),
        );
        $this->assertSame('150.00%', $evaluation->getCell('C2')->getFormattedValue());
        $this->assertNotSame(
            $activity->getCell('J2')->getFormattedValue(),
            $evaluation->getCell('B2')->getFormattedValue(),
        );
    }

    public function test_a_teacher_without_the_export_permission_is_forbidden(): void
    {
        $s = $this->scenario();

        $this->export('/api/v1/reports/weekly/export?'.self::WEEK.'&format=pdf', $s['teacher'])
            ->assertForbidden();

        $this->export('/api/v1/reports/monthly/export?month=2026-10&format=pdf', $s['teacher'])
            ->assertForbidden();

        $this->export('/api/v1/dashboard/export?month=2026-10&format=pdf', $s['teacher'])
            ->assertForbidden();
    }

    public function test_a_teacher_with_the_export_permission_only_exports_their_groups(): void
    {
        $s = $this->scenario();

        $otherTeacher = User::factory()->teacher()->for($s['center'])->create();
        $otherGroup = Group::factory()->for($s['center'])->create([
            'teacher_id' => $otherTeacher->id,
        ]);
        $foreignStudent = $this->addStudent($s, $otherGroup, 'طالب معلم آخر');

        $this->memorization($s, '2026-10-25', '1.0000');
        $this->memorization($s, '2026-10-25', '4.0000', $foreignStudent);

        $s['teacher']->update(['permissions' => ['reports.export']]);

        $book = $this->workbook((string) $this->export(
            '/api/v1/reports/weekly/export?'.self::WEEK.'&format=xlsx',
            $s['teacher'],
        )->assertOk()->getContent());

        $sheet = $book->getSheetByName('الحضور والحفظ والمراجعة');

        $this->assertSame('عبد الله محمد', $sheet->getCell('A2')->getValue());
        $this->assertSame('الإجمالي', $sheet->getCell('A3')->getValue());
        $this->assertSame('1.0000', $sheet->getCell('I2')->getFormattedValue());
    }

    public function test_a_teacher_cannot_export_another_teachers_group_through_the_filter(): void
    {
        $s = $this->scenario();

        $otherTeacher = User::factory()->teacher()->for($s['center'])->create();
        $otherGroup = Group::factory()->for($s['center'])->create([
            'teacher_id' => $otherTeacher->id,
        ]);
        $foreignStudent = $this->addStudent($s, $otherGroup, 'طالب معلم آخر');

        $s['teacher']->update(['permissions' => ['reports.export']]);

        $book = $this->workbook((string) $this->export(
            '/api/v1/reports/weekly/export?'.self::WEEK.'&group_id='.$otherGroup->id.'&format=xlsx',
            $s['teacher'],
        )->assertOk()->getContent());

        $sheet = $book->getSheetByName('الحضور والحفظ والمراجعة');

        $this->assertSame('الإجمالي', $sheet->getCell('A2')->getValue());

        $this->export(
            '/api/v1/reports/weekly/export?'.self::WEEK.'&student_id='.$foreignStudent->id.'&format=pdf',
            $s['teacher'],
        )->assertOk();
    }

    public function test_another_centers_data_is_never_exported(): void
    {
        $s = $this->scenario();

        $otherCenter = Center::factory()->create(['timezone' => 'Asia/Riyadh']);
        $otherManager = User::factory()->manager()->for($otherCenter)->create();
        $otherTeacher = User::factory()->teacher()->for($otherCenter)->create();
        $otherGroup = Group::factory()->for($otherCenter)->create([
            'teacher_id' => $otherTeacher->id,
        ]);
        $foreignStudent = Student::factory()->for($otherCenter)->create([
            'enrolled_at' => '2026-01-01',
            'full_name' => 'طالب مركز آخر',
        ]);
        app(AddStudentToGroup::class)
            ->execute($otherGroup, $otherManager, $foreignStudent->id);

        $this->memorization($s, '2026-10-25', '1.0000');

        $book = $this->workbook((string) $this->export(
            '/api/v1/reports/weekly/export?'.self::WEEK.'&format=xlsx',
            $s['manager'],
        )->assertOk()->getContent());

        $sheet = $book->getSheetByName('الحضور والحفظ والمراجعة');
        $this->assertSame('عبد الله محمد', $sheet->getCell('A2')->getValue());
        $this->assertSame('الإجمالي', $sheet->getCell('A3')->getValue());

        // A foreign group id is an empty filter, never a leak.
        $foreign = $this->workbook((string) $this->export(
            '/api/v1/reports/weekly/export?'.self::WEEK.'&group_id='.$otherGroup->id.'&format=xlsx',
            $s['manager'],
        )->assertOk()->getContent());

        $this->assertSame('الإجمالي', $foreign->getSheetByName('الحضور والحفظ والمراجعة')->getCell('A2')->getValue());
    }

    public function test_the_group_and_student_filters_narrow_the_export(): void
    {
        $s = $this->scenario();
        $second = $this->addStudent($s);

        $this->memorization($s, '2026-10-25', '1.0000');
        $this->memorization($s, '2026-10-25', '3.0000', $second);

        $byStudent = $this->workbook((string) $this->export(
            '/api/v1/reports/weekly/export?'.self::WEEK.'&student_id='.$second->id.'&format=xlsx',
            $s['manager'],
        )->assertOk()->getContent());

        $sheet = $byStudent->getSheetByName('الحضور والحفظ والمراجعة');
        $this->assertSame('سارة أحمد', $sheet->getCell('A2')->getValue());
        $this->assertSame('الإجمالي', $sheet->getCell('A3')->getValue());
        $this->assertSame('3.0000', $sheet->getCell('I2')->getFormattedValue());

        $byGroup = $this->workbook((string) $this->export(
            '/api/v1/reports/weekly/export?'.self::WEEK.'&group_id='.$s['group']->id.'&format=xlsx',
            $s['manager'],
        )->assertOk()->getContent());

        $this->assertSame('3.0000', $byGroup->getSheetByName('الحضور والحفظ والمراجعة')->getCell('I3')->getFormattedValue());
    }

    public function test_an_empty_report_still_produces_a_valid_file(): void
    {
        $center = Center::factory()->create([
            'weekly_evaluation_start_day' => 'saturday',
            'timezone' => 'Asia/Riyadh',
        ]);
        $manager = User::factory()->manager()->for($center)->create();

        $pdf = $this->export('/api/v1/reports/weekly/export?'.self::WEEK.'&format=pdf', $manager)
            ->assertOk();
        $this->assertStringStartsWith('%PDF-', (string) $pdf->getContent());

        $xlsx = $this->export('/api/v1/reports/weekly/export?'.self::WEEK.'&format=xlsx', $manager)
            ->assertOk();

        $book = $this->workbook((string) $xlsx->getContent());
        $sheet = $book->getSheetByName('الحضور والحفظ والمراجعة');

        $this->assertSame('الإجمالي', $sheet->getCell('A2')->getValue());
        $this->assertSame('0.0000', $sheet->getCell('I2')->getFormattedValue());

        $html = (string) $this->export('/api/v1/reports/weekly/export?'.self::WEEK.'&format=html', $manager)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('لا توجد بيانات للفترة المحددة.', $html);
    }

    public function test_decimal_precision_is_preserved(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-25', '0.1234');
        $this->review($s, '2026-10-26', '0.0001');

        $book = $this->workbook((string) $this->export(
            '/api/v1/reports/weekly/export?'.self::WEEK.'&format=xlsx',
            $s['manager'],
        )->assertOk()->getContent());

        $sheet = $book->getSheetByName('الحضور والحفظ والمراجعة');

        $this->assertSame('0.1234', $sheet->getCell('I2')->getFormattedValue());
        $this->assertSame('0.0001', $sheet->getCell('L2')->getFormattedValue());
        $this->assertSame('12.34%', $sheet->getCell('J2')->getFormattedValue());
    }

    public function test_arabic_names_are_exported_unchanged(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-25', '1.0000');

        $book = $this->workbook((string) $this->export(
            '/api/v1/reports/weekly/export?'.self::WEEK.'&format=xlsx',
            $s['manager'],
        )->assertOk()->getContent());

        $this->assertSame(
            'عبد الله محمد',
            $book->getSheetByName('الحضور والحفظ والمراجعة')->getCell('A2')->getValue(),
        );

        $html = (string) $this->export('/api/v1/reports/weekly/export?'.self::WEEK.'&format=html', $s['manager'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('عبد الله محمد', $html);
        $this->assertStringContainsString('الحضور والحفظ والمراجعة', $html);
    }

    public function test_the_export_query_count_does_not_grow_with_the_number_of_students(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-25', '1.0000');
        $this->weeklyEvaluation($s, $s['student']);

        $one = $this->queryCountForExport($s);

        for ($i = 0; $i < 8; $i++) {
            $student = $this->addStudent($s, null, 'طالب '.$i);
            $this->memorization($s, '2026-10-25', '1.0000', $student);
            $this->weeklyEvaluation($s, $student);
        }

        $nine = $this->queryCountForExport($s);

        for ($i = 0; $i < 40; $i++) {
            $student = $this->addStudent($s, null, 'طالب إضافي '.$i);
            $this->memorization($s, '2026-10-25', '1.0000', $student);
            $this->weeklyEvaluation($s, $student);
        }

        $fortyNine = $this->queryCountForExport($s);

        $this->assertSame($one, $nine, 'One query per student leaked into the 9-student export.');
        $this->assertSame($one, $fortyNine, 'One query per student leaked into the 49-student export.');
    }

    public function test_a_report_at_the_row_limit_is_exported(): void
    {
        $s = $this->scenario();
        $this->topUpStudents($s, 200);

        $book = $this->workbook((string) $this->export(
            '/api/v1/reports/weekly/export?'.self::WEEK.'&format=xlsx',
            $s['manager'],
        )->assertOk()->getContent());

        $sheet = $book->getSheetByName('الحضور والحفظ والمراجعة');

        // Header on row 1, 200 students on rows 2–201, totals on row 202.
        $this->assertSame('الإجمالي', $sheet->getCell('A202')->getValue());
        $this->assertNull($sheet->getCell('A203')->getValue());
    }

    public function test_a_report_over_the_row_limit_is_rejected_with_a_clear_error(): void
    {
        $s = $this->scenario();
        $this->topUpStudents($s, 201);

        $response = $this->export('/api/v1/reports/weekly/export?'.self::WEEK.'&format=pdf', $s['manager'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'export_too_large')
            ->assertJsonPath('limits.reason', 'rows')
            ->assertJsonPath('limits.actual', 201)
            ->assertJsonPath('limits.limit', 200);

        $this->assertStringContainsString('200', (string) $response->json('message'));
        $this->assertStringContainsString('group_id', (string) $response->json('message'));
    }

    public function test_every_format_refuses_a_report_over_the_limit(): void
    {
        $s = $this->scenario();
        $this->topUpStudents($s, 201);

        foreach (['pdf', 'xlsx', 'html'] as $format) {
            $this->export('/api/v1/reports/weekly/export?'.self::WEEK.'&format='.$format, $s['manager'])
                ->assertStatus(422)
                ->assertJsonPath('error', 'export_too_large');
        }
    }

    public function test_a_document_over_the_html_budget_is_a_capacity_error_not_a_crash(): void
    {
        $s = $this->scenario();
        $this->memorization($s, '2026-10-25', '1.0000');

        // A host with a small pcre limit: the row ceiling passes, so the byte
        // budget is the guard that has to catch the document before mPDF does.
        $previous = (string) ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '5000');

        try {
            $this->export('/api/v1/reports/weekly/export?'.self::WEEK.'&format=pdf', $s['manager'])
                ->assertStatus(422)
                ->assertJsonPath('error', 'export_too_large')
                ->assertJsonPath('limits.reason', 'html_bytes');
        } finally {
            ini_set('pcre.backtrack_limit', $previous);
        }

        // The budget is a PDF constraint; the other formats are unaffected.
        $this->export('/api/v1/reports/weekly/export?'.self::WEEK.'&format=html', $s['manager'])
            ->assertOk();
        $this->export('/api/v1/reports/weekly/export?'.self::WEEK.'&format=xlsx', $s['manager'])
            ->assertOk();
    }

    public function test_the_row_guard_adds_no_queries(): void
    {
        $s = $this->scenario();

        $one = $this->queryCountForExport($s);

        $this->topUpStudents($s, 200);

        $two = $this->queryCountForExport($s);

        $this->assertSame($one, $two, 'The export ceiling must not add a query per student.');
    }

    private function queryCountForExport(array $s, string $format = 'xlsx'): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->export('/api/v1/reports/weekly/export?'.self::WEEK.'&format='.$format, $s['manager'])
            ->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
