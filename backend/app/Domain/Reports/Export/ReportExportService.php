<?php

namespace App\Domain\Reports\Export;

use App\Domain\Evaluation\ValueObjects\WeekPeriod;
use App\Domain\Reports\Export\Builders\DashboardExportBuilder;
use App\Domain\Reports\Export\Builders\MonthlyReportExportBuilder;
use App\Domain\Reports\Export\Builders\WeeklyReportExportBuilder;
use App\Domain\Reports\Export\Renderers\HtmlReportRenderer;
use App\Domain\Reports\Export\Renderers\PdfReportRenderer;
use App\Domain\Reports\Export\Renderers\XlsxReportRenderer;
use App\Domain\Reports\Queries\DashboardQuery;
use App\Domain\Reports\Queries\MonthlyReportQuery;
use App\Domain\Reports\Queries\WeeklyReportQuery;
use App\Domain\Reports\ValueObjects\MonthPeriod;
use App\Domain\Tenancy\Models\Center;
use App\Models\User;

/**
 * Orchestrates one export: query → payload → file.
 *
 * The figures come from the same query objects the JSON endpoints use, so an
 * export can never disagree with the report it was exported from. Nothing is
 * recomputed here — the service only picks a builder and a renderer.
 */
final class ReportExportService
{
    public function __construct(
        private WeeklyReportQuery $weeklyQuery,
        private MonthlyReportQuery $monthlyQuery,
        private DashboardQuery $dashboardQuery,
        private WeeklyReportExportBuilder $weeklyBuilder,
        private MonthlyReportExportBuilder $monthlyBuilder,
        private DashboardExportBuilder $dashboardBuilder,
        private HtmlReportRenderer $htmlRenderer,
        private PdfReportRenderer $pdfRenderer,
        private XlsxReportRenderer $xlsxRenderer,
    ) {}

    public function weekly(
        User $actor,
        Center $center,
        WeekPeriod $period,
        ?int $groupId,
        ?int $studentId,
        ExportFormat $format,
    ): ExportResult {
        $report = $this->weeklyQuery->execute($actor, $center, $period, $groupId, $studentId);

        return $this->render(
            $this->weeklyBuilder->build($report, $center, $groupId, $studentId),
            $format,
        );
    }

    public function monthly(
        User $actor,
        Center $center,
        MonthPeriod $month,
        ?int $groupId,
        ?int $studentId,
        ExportFormat $format,
    ): ExportResult {
        $report = $this->monthlyQuery->execute($actor, $center, $month, $groupId, $studentId);

        return $this->render(
            $this->monthlyBuilder->build($report, $center, $groupId, $studentId),
            $format,
        );
    }

    public function dashboard(
        User $actor,
        Center $center,
        MonthPeriod $month,
        ?int $groupId,
        ExportFormat $format,
    ): ExportResult {
        $summary = $this->dashboardQuery->execute($actor, $center, $month, $groupId);

        return $this->render(
            $this->dashboardBuilder->build($summary, $center, $groupId),
            $format,
        );
    }

    private function render(ExportPayload $payload, ExportFormat $format): ExportResult
    {
        return match ($format) {
            ExportFormat::Pdf => $this->pdfRenderer->render($payload),
            ExportFormat::Xlsx => $this->xlsxRenderer->render($payload),
            ExportFormat::Html => $this->htmlRenderer->render($payload),
        };
    }
}
