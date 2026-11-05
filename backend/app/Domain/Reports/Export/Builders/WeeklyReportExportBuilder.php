<?php

namespace App\Domain\Reports\Export\Builders;

use App\Domain\Reports\DTOs\StudentWeeklyRow;
use App\Domain\Reports\DTOs\WeeklyReport;
use App\Domain\Reports\Export\ExportColumn;
use App\Domain\Reports\Export\ExportPayload;
use App\Domain\Reports\Export\ExportSection;
use App\Domain\Reports\Export\ReportKind;
use App\Domain\Reports\Export\Support\DecimalTotal;
use App\Domain\Tenancy\Models\Center;

/**
 * Turns a weekly report DTO into an export payload.
 *
 * It adds no business meaning: every figure is copied from the DTO as-is. The
 * only work done here is layout — choosing which section a value belongs to so
 * that achievement, weighted score and recitation never share a table.
 */
final class WeeklyReportExportBuilder
{
    public function build(
        WeeklyReport $report,
        Center $center,
        ?int $groupId = null,
        ?int $studentId = null,
    ): ExportPayload {
        $rows = $report->students;

        return new ExportPayload(
            kind: ReportKind::Weekly,
            title: 'تقرير المركز الأسبوعي',
            subtitle: sprintf('من %s إلى %s', $report->period->start, $report->period->end),
            periodKey: sprintf('%s_%s', $report->period->start, $report->period->end),
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
     * @param  array<int, StudentWeeklyRow>  $rows
     * @return array<int, array{label: string, value: string}>
     */
    private function meta(
        WeeklyReport $report,
        Center $center,
        array $rows,
        ?int $groupId,
        ?int $studentId,
    ): array {
        $meta = [
            ['label' => 'المركز', 'value' => (string) $center->name],
            ['label' => 'الأسبوع', 'value' => $report->period->key],
            ['label' => 'نوع الأسبوع', 'value' => $report->period->startDay->value],
        ];

        // A filter is echoed only when a row proves its label; an out-of-scope
        // filter yields no rows and therefore no claim about what it was.
        if ($groupId !== null && $rows !== []) {
            $meta[] = ['label' => 'المجموعة', 'value' => (string) $rows[0]->groupName];
        }

        if ($studentId !== null && $rows !== []) {
            $meta[] = ['label' => 'الطالب', 'value' => $rows[0]->studentName];
        }

        return $meta;
    }

    /**
     * @param  array<int, StudentWeeklyRow>  $rows
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

        $body = array_map(static fn (StudentWeeklyRow $row): array => [
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

        // No aggregate achievement is added: an average of uncapped ratios is a
        // new figure that no business rule defines.
        $totals = [
            'الإجمالي',
            null,
            null,
            (string) $this->sumIntegers($rows, static fn (StudentWeeklyRow $row): int => $row->attendance['present']),
            (string) $this->sumIntegers($rows, static fn (StudentWeeklyRow $row): int => $row->attendance['late']),
            (string) $this->sumIntegers($rows, static fn (StudentWeeklyRow $row): int => $row->attendance['excused_absence']),
            (string) $this->sumIntegers($rows, static fn (StudentWeeklyRow $row): int => $row->attendance['unexcused_absence']),
            DecimalTotal::of(array_map(static fn (StudentWeeklyRow $row): string => $row->memorization['required_pages'], $rows), 2),
            DecimalTotal::of(array_map(static fn (StudentWeeklyRow $row): string => $row->memorization['actual_pages'], $rows), 4),
            null,
            (string) $this->sumIntegers($rows, static fn (StudentWeeklyRow $row): int => $row->memorization['sessions']),
            DecimalTotal::of(array_map(static fn (StudentWeeklyRow $row): string => $row->review['pages'], $rows), 4),
            (string) $this->sumIntegers($rows, static fn (StudentWeeklyRow $row): int => $row->review['sessions']),
        ];

        return new ExportSection('الحضور والحفظ والمراجعة', $columns, $body, $totals);
    }

    /**
     * @param  array<int, StudentWeeklyRow>  $rows
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

        $body = array_map(static fn (StudentWeeklyRow $row): array => [
            $row->studentName,
            (string) $row->recitation['sessions'],
            (string) $row->recitation['errors']['hifz'],
            (string) $row->recitation['errors']['tajweed'],
            (string) $row->recitation['errors']['reading'],
            self::grades($row->recitation['grades']),
        ], $rows);

        $totals = [
            'الإجمالي',
            (string) $this->sumIntegers($rows, static fn (StudentWeeklyRow $row): int => $row->recitation['sessions']),
            (string) $this->sumIntegers($rows, static fn (StudentWeeklyRow $row): int => $row->recitation['errors']['hifz']),
            (string) $this->sumIntegers($rows, static fn (StudentWeeklyRow $row): int => $row->recitation['errors']['tajweed']),
            (string) $this->sumIntegers($rows, static fn (StudentWeeklyRow $row): int => $row->recitation['errors']['reading']),
            null,
        ];

        return new ExportSection('التسميع', $columns, $body, $totals);
    }

    /**
     * The stored weekly evaluation, in its own table: the weighted score is a
     * 0–100 index and must never be read as an achievement percentage.
     *
     * @param  array<int, StudentWeeklyRow>  $rows
     */
    private function evaluationSection(array $rows): ExportSection
    {
        $columns = [
            ExportColumn::text('الطالب'),
            ExportColumn::score('التقييم المرجح (0-100)'),
            ExportColumn::percentage('نسبة الإنجاز في التقييم'),
            ExportColumn::text('مكتمل'),
            ExportColumn::text('الحالة'),
        ];

        $body = array_map(static fn (StudentWeeklyRow $row): array => [
            $row->studentName,
            $row->evaluation['weighted_score'] ?? null,
            $row->evaluation['achievement_percentage'] ?? null,
            $row->evaluation === null ? null : ($row->evaluation['is_complete'] ? 'نعم' : 'لا'),
            $row->evaluation['status'] ?? null,
        ], $rows);

        // Scores are not summed and not averaged: a total would be a new index.
        return new ExportSection('التقييم الأسبوعي (لقطة محفوظة)', $columns, $body);
    }

    /**
     * @param  array<int, StudentWeeklyRow>  $rows
     * @return array<int, array{label: string, value: string}>
     */
    private function summary(array $rows): array
    {
        $absences = $this->sumIntegers($rows, static fn (StudentWeeklyRow $row): int => $row->attendance['excused_absence'])
            + $this->sumIntegers($rows, static fn (StudentWeeklyRow $row): int => $row->attendance['unexcused_absence']);

        return [
            ['label' => 'عدد الطلاب', 'value' => (string) count($rows)],
            ['label' => 'إجمالي صفحات الحفظ', 'value' => DecimalTotal::of(array_map(static fn (StudentWeeklyRow $row): string => $row->memorization['actual_pages'], $rows), 4)],
            ['label' => 'إجمالي صفحات المراجعة', 'value' => DecimalTotal::of(array_map(static fn (StudentWeeklyRow $row): string => $row->review['pages'], $rows), 4)],
            ['label' => 'إجمالي جلسات التسميع', 'value' => (string) $this->sumIntegers($rows, static fn (StudentWeeklyRow $row): int => $row->recitation['sessions'])],
            ['label' => 'إجمالي الحضور', 'value' => (string) $this->sumIntegers($rows, static fn (StudentWeeklyRow $row): int => $row->attendance['present'])],
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
     * @param  array<int, StudentWeeklyRow>  $rows
     * @param  callable(StudentWeeklyRow): int  $pick
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
