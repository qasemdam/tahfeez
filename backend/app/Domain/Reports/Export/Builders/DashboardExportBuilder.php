<?php

namespace App\Domain\Reports\Export\Builders;

use App\Domain\Reports\DTOs\DashboardSummary;
use App\Domain\Reports\Export\ExportColumn;
use App\Domain\Reports\Export\ExportPayload;
use App\Domain\Reports\Export\ExportSection;
use App\Domain\Reports\Export\ReportKind;
use App\Domain\Tenancy\Models\Center;

/**
 * Turns the dashboard summary into an export payload.
 *
 * The dashboard is already an aggregate, so it is exported as label/value
 * tables — no per-student rows are rebuilt here and no KPI is recomputed.
 */
final class DashboardExportBuilder
{
    public function build(
        DashboardSummary $summary,
        Center $center,
        ?int $groupId = null,
    ): ExportPayload {
        return new ExportPayload(
            kind: ReportKind::Dashboard,
            title: 'لوحة مؤشرات المركز',
            subtitle: sprintf('شهر %s (%s إلى %s)', $summary->period->key, $summary->period->start, $summary->period->end),
            periodKey: $summary->period->key,
            meta: $this->meta($summary, $center, $groupId),
            sections: [
                $this->studentsSection($summary),
                $this->attendanceSection($summary),
                $this->activitySection($summary),
            ],
            summary: $this->summary($summary),
            generatedAt: now($center->timezone)->format('Y-m-d H:i'),
        );
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function meta(DashboardSummary $summary, Center $center, ?int $groupId): array
    {
        $meta = [
            ['label' => 'المركز', 'value' => (string) $center->name],
            ['label' => 'الشهر', 'value' => $summary->period->key],
            ['label' => 'من', 'value' => $summary->period->start],
            ['label' => 'إلى', 'value' => $summary->period->end],
        ];

        if ($groupId !== null) {
            $meta[] = ['label' => 'المجموعة', 'value' => (string) $groupId];
        }

        return $meta;
    }

    private function studentsSection(DashboardSummary $summary): ExportSection
    {
        return new ExportSection(
            'الملخص العام',
            $this->columns(),
            [
                ['الطلاب', (string) $summary->studentsTotal],
                ['الطلاب النشطون', (string) $summary->studentsActive],
                ['المجموعات', (string) $summary->groupsTotal],
            ],
        );
    }

    private function attendanceSection(DashboardSummary $summary): ExportSection
    {
        return new ExportSection(
            'الحضور',
            $this->columns(),
            [
                ['حاضر', (string) $summary->attendance['present']],
                ['متأخر', (string) $summary->attendance['late']],
                ['غياب بعذر', (string) $summary->attendance['excused_absence']],
                ['غياب بدون عذر', (string) $summary->attendance['unexcused_absence']],
            ],
        );
    }

    /**
     * Memorization, review and recitation stay on separate rows: they are
     * different activity types, not one blended total.
     */
    private function activitySection(DashboardSummary $summary): ExportSection
    {
        return new ExportSection(
            'النشاط والتقييم',
            $this->columns(),
            [
                ['صفحات الحفظ', $summary->memorization['pages']],
                ['جلسات الحفظ', (string) $summary->memorization['sessions']],
                ['صفحات المراجعة', $summary->review['pages']],
                ['جلسات المراجعة', (string) $summary->review['sessions']],
                ['جلسات التسميع', (string) $summary->recitationSessions],
                ['التقييمات الأسبوعية', (string) $summary->weeklyEvaluations['count']],
                ['التقييمات المعتمدة', (string) $summary->weeklyEvaluations['approved']],
                ['التقييمات المحسوبة', (string) $summary->weeklyEvaluations['computed']],
            ],
        );
    }

    /**
     * @return array<int, ExportColumn>
     */
    private function columns(): array
    {
        return [
            ExportColumn::text('المؤشر'),
            ExportColumn::text('القيمة'),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function summary(DashboardSummary $summary): array
    {
        return [
            ['label' => 'عدد الطلاب', 'value' => (string) $summary->studentsTotal],
            ['label' => 'الطلاب النشطون', 'value' => (string) $summary->studentsActive],
            ['label' => 'المجموعات', 'value' => (string) $summary->groupsTotal],
            ['label' => 'صفحات الحفظ', 'value' => $summary->memorization['pages']],
            ['label' => 'صفحات المراجعة', 'value' => $summary->review['pages']],
            ['label' => 'جلسات التسميع', 'value' => (string) $summary->recitationSessions],
        ];
    }
}
