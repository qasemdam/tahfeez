<?php

namespace App\Domain\Reports\Export\Builders;

use App\Domain\Reports\DTOs\MonthlyReport;
use App\Domain\Reports\DTOs\StudentMonthlyRow;
use App\Domain\Reports\Export\ExportColumn;
use App\Domain\Reports\Export\ExportPayload;
use App\Domain\Reports\Export\ExportSection;
use App\Domain\Reports\Export\ReportKind;
use App\Domain\Reports\Export\Support\DecimalTotal;
use App\Domain\Tenancy\Models\Center;

/**
 * Turns a monthly report DTO into an export payload.
 *
 * The month's requirement is whatever the DTO carries — the sum frozen in the
 * stored evaluations. Nothing is re-derived from the current profile, and an
 * undefined achievement stays an em dash.
 */
final class MonthlyReportExportBuilder
{
    public function build(
        MonthlyReport $report,
        Center $center,
        ?int $groupId = null,
        ?int $studentId = null,
    ): ExportPayload {
        $rows = $report->students;

        return new ExportPayload(
            kind: ReportKind::Monthly,
            title: 'تقرير المركز الشهري',
            subtitle: sprintf('شهر %s (%s إلى %s)', $report->month->key, $report->month->start, $report->month->end),
            periodKey: $report->month->key,
            meta: $this->meta($report, $center, $rows, $groupId, $studentId),
            sections: [
                $this->activitySection($rows),
                $this->recitationSection($rows),
                $this->evaluationSection($rows),
            ],
            summary: $this->summary($rows),
            generatedAt: now($center->timezone)->format('Y-m-d H:i'),
        );
    }

    /**
     * @param  array<int, StudentMonthlyRow>  $rows
     * @return array<int, array{label: string, value: string}>
     */
    private function meta(
        MonthlyReport $report,
        Center $center,
        array $rows,
        ?int $groupId,
        ?int $studentId,
    ): array {
        $meta = [
            ['label' => 'المركز', 'value' => (string) $center->name],
            ['label' => 'الشهر', 'value' => $report->month->key],
            ['label' => 'من', 'value' => $report->month->start],
            ['label' => 'إلى', 'value' => $report->month->end],
        ];

        if ($groupId !== null && $rows !== []) {
            $meta[] = ['label' => 'المجموعة', 'value' => (string) $rows[0]->groupName];
        }

        if ($studentId !== null && $rows !== []) {
            $meta[] = ['label' => 'الطالب', 'value' => $rows[0]->studentName];
        }

        return $meta;
    }

    /**
     * @param  array<int, StudentMonthlyRow>  $rows
     */
    private function activitySection(array $rows): ExportSection
    {
        $columns = [
            ExportColumn::text('الطالب'),
            ExportColumn::text('المجموعة'),
            ExportColumn::text('المعلم'),
            ExportColumn::integer('حاضر'),
            ExportColumn::integer('متأخر'),
            ExportColumn::integer('غياب بعذر'),
            ExportColumn::integer('غياب بدون عذر'),
            ExportColumn::decimal('المطلوب (صفحة)', 2),
            ExportColumn::decimal('المحفوظ (صفحة)', 4),
            ExportColumn::percentage('نسبة الإنجاز'),
            ExportColumn::integer('جلسات الحفظ'),
            ExportColumn::decimal('المراجعة (صفحة)', 4),
            ExportColumn::integer('جلسات المراجعة'),
        ];

        $body = array_map(static fn (StudentMonthlyRow $row): array => [
            $row->studentName,
            $row->groupName,
            $row->teacherName,
            (string) $row->attendance['present'],
            (string) $row->attendance['late'],
            (string) $row->attendance['excused_absence'],
            (string) $row->attendance['unexcused_absence'],
            $row->memorization['required_pages'],
            $row->memorization['actual_pages'],
            $row->memorization['achievement_percentage'],
            (string) $row->memorization['sessions'],
            $row->review['pages'],
            (string) $row->review['sessions'],
        ], $rows);

        $totals = [
            'الإجمالي',
            null,
            null,
            (string) $this->sumIntegers($rows, static fn (StudentMonthlyRow $row): int => $row->attendance['present']),
            (string) $this->sumIntegers($rows, static fn (StudentMonthlyRow $row): int => $row->attendance['late']),
            (string) $this->sumIntegers($rows, static fn (StudentMonthlyRow $row): int => $row->attendance['excused_absence']),
            (string) $this->sumIntegers($rows, static fn (StudentMonthlyRow $row): int => $row->attendance['unexcused_absence']),
            DecimalTotal::of(array_map(static fn (StudentMonthlyRow $row): string => $row->memorization['required_pages'], $rows), 2),
            DecimalTotal::of(array_map(static fn (StudentMonthlyRow $row): string => $row->memorization['actual_pages'], $rows), 4),
            null,
            (string) $this->sumIntegers($rows, static fn (StudentMonthlyRow $row): int => $row->memorization['sessions']),
            DecimalTotal::of(array_map(static fn (StudentMonthlyRow $row): string => $row->review['pages'], $rows), 4),
            (string) $this->sumIntegers($rows, static fn (StudentMonthlyRow $row): int => $row->review['sessions']),
        ];

        return new ExportSection('الحضور والحفظ والمراجعة', $columns, $body, $totals);
    }

    /**
     * @param  array<int, StudentMonthlyRow>  $rows
     */
    private function recitationSection(array $rows): ExportSection
    {
        $columns = [
            ExportColumn::text('الطالب'),
            ExportColumn::integer('جلسات التسميع'),
            ExportColumn::integer('أخطاء الحفظ'),
            ExportColumn::integer('أخطاء التجويد'),
            ExportColumn::integer('أخطاء القراءة'),
            ExportColumn::text('التقديرات'),
        ];

        $body = array_map(static fn (StudentMonthlyRow $row): array => [
            $row->studentName,
            (string) $row->recitation['sessions'],
            (string) $row->recitation['errors']['hifz'],
            (string) $row->recitation['errors']['tajweed'],
            (string) $row->recitation['errors']['reading'],
            self::grades($row->recitation['grades']),
        ], $rows);

        $totals = [
            'الإجمالي',
            (string) $this->sumIntegers($rows, static fn (StudentMonthlyRow $row): int => $row->recitation['sessions']),
            (string) $this->sumIntegers($rows, static fn (StudentMonthlyRow $row): int => $row->recitation['errors']['hifz']),
            (string) $this->sumIntegers($rows, static fn (StudentMonthlyRow $row): int => $row->recitation['errors']['tajweed']),
            (string) $this->sumIntegers($rows, static fn (StudentMonthlyRow $row): int => $row->recitation['errors']['reading']),
            null,
        ];

        return new ExportSection('التسميع', $columns, $body, $totals);
    }

    /**
     * @param  array<int, StudentMonthlyRow>  $rows
     */
    private function evaluationSection(array $rows): ExportSection
    {
        $columns = [
            ExportColumn::text('الطالب'),
            ExportColumn::integer('عدد التقييمات'),
            ExportColumn::integer('مكتملة'),
            ExportColumn::integer('معتمدة'),
            ExportColumn::integer('محسوبة'),
        ];

        $body = array_map(static fn (StudentMonthlyRow $row): array => [
            $row->studentName,
            (string) $row->weeklyEvaluations['count'],
            (string) $row->weeklyEvaluations['completed'],
            (string) $row->weeklyEvaluations['approved'],
            (string) $row->weeklyEvaluations['computed'],
        ], $rows);

        $totals = [
            'الإجمالي',
            (string) $this->sumIntegers($rows, static fn (StudentMonthlyRow $row): int => $row->weeklyEvaluations['count']),
            (string) $this->sumIntegers($rows, static fn (StudentMonthlyRow $row): int => $row->weeklyEvaluations['completed']),
            (string) $this->sumIntegers($rows, static fn (StudentMonthlyRow $row): int => $row->weeklyEvaluations['approved']),
            (string) $this->sumIntegers($rows, static fn (StudentMonthlyRow $row): int => $row->weeklyEvaluations['computed']),
        ];

        return new ExportSection('التقييمات الأسبوعية', $columns, $body, $totals);
    }

    /**
     * @param  array<int, StudentMonthlyRow>  $rows
     * @return array<int, array{label: string, value: string}>
     */
    private function summary(array $rows): array
    {
        $absences = $this->sumIntegers($rows, static fn (StudentMonthlyRow $row): int => $row->attendance['excused_absence'])
            + $this->sumIntegers($rows, static fn (StudentMonthlyRow $row): int => $row->attendance['unexcused_absence']);

        return [
            ['label' => 'عدد الطلاب', 'value' => (string) count($rows)],
            ['label' => 'إجمالي صفحات الحفظ', 'value' => DecimalTotal::of(array_map(static fn (StudentMonthlyRow $row): string => $row->memorization['actual_pages'], $rows), 4)],
            ['label' => 'إجمالي صفحات المراجعة', 'value' => DecimalTotal::of(array_map(static fn (StudentMonthlyRow $row): string => $row->review['pages'], $rows), 4)],
            ['label' => 'إجمالي جلسات التسميع', 'value' => (string) $this->sumIntegers($rows, static fn (StudentMonthlyRow $row): int => $row->recitation['sessions'])],
            ['label' => 'إجمالي التقييمات الأسبوعية', 'value' => (string) $this->sumIntegers($rows, static fn (StudentMonthlyRow $row): int => $row->weeklyEvaluations['count'])],
            ['label' => 'إجمالي الحضور', 'value' => (string) $this->sumIntegers($rows, static fn (StudentMonthlyRow $row): int => $row->attendance['present'])],
            ['label' => 'إجمالي الغياب', 'value' => (string) $absences],
        ];
    }

    /**
     * @param  array<string, int>  $grades
     */
    private static function grades(array $grades): ?string
    {
        if ($grades === []) {
            return null;
        }

        $parts = [];

        foreach ($grades as $grade => $count) {
            $parts[] = sprintf('%s: %d', $grade, $count);
        }

        return implode('، ', $parts);
    }

    /**
     * @param  array<int, StudentMonthlyRow>  $rows
     * @param  callable(StudentMonthlyRow): int  $pick
     */
    private function sumIntegers(array $rows, callable $pick): int
    {
        $sum = 0;

        foreach ($rows as $row) {
            $sum += $pick($row);
        }

        return $sum;
    }
}
