<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Evaluation\Services\WeekPeriodResolver;
use App\Domain\Reports\DTOs\DashboardSummary;
use App\Domain\Reports\DTOs\WeeklyReport;
use App\Domain\Reports\Queries\DashboardQuery;
use App\Domain\Reports\Queries\MonthlyReportQuery;
use App\Domain\Reports\Queries\WeeklyReportQuery;
use App\Domain\Reports\Support\ReportPeriodResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DashboardRequest;
use App\Http\Requests\Api\V1\MonthlyReportRequest;
use App\Http\Requests\Api\V1\WeeklyReportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only reports. The controller resolves the period and delegates the
 * aggregation to a query object; it holds no report logic itself.
 */
class ReportController extends Controller
{
    public function weekly(
        WeeklyReportRequest $request,
        WeekPeriodResolver $resolver,
        ReportPeriodResolver $periods,
        WeeklyReportQuery $query,
    ): JsonResponse {
        $actor = $request->user();
        Gate::authorize('viewAny', WeeklyReport::class);

        $center = $actor->center;

        $period = $periods->week(
            $request->input('week_key'),
            $request->input('week_start_date'),
            $resolver,
            $center->timezone,
            $center->weekly_evaluation_start_day,
        );

        $report = $query->execute(
            actor: $actor,
            center: $center,
            period: $period,
            groupId: $request->filled('group_id') ? (int) $request->input('group_id') : null,
            studentId: $request->filled('student_id') ? (int) $request->input('student_id') : null,
        );

        return response()->json(['data' => $report->toArray()]);
    }

    public function monthly(
        MonthlyReportRequest $request,
        ReportPeriodResolver $periods,
        MonthlyReportQuery $query,
    ): JsonResponse {
        $actor = $request->user();
        Gate::authorize('viewAny', WeeklyReport::class);

        $center = $actor->center;
        $month = $periods->month((string) $request->input('month'), $center->timezone);

        $report = $query->execute(
            actor: $actor,
            center: $center,
            month: $month,
            groupId: $request->filled('group_id') ? (int) $request->input('group_id') : null,
            studentId: $request->filled('student_id') ? (int) $request->input('student_id') : null,
        );

        return response()->json(['data' => $report->toArray()]);
    }

    public function dashboard(
        DashboardRequest $request,
        ReportPeriodResolver $periods,
        DashboardQuery $query,
    ): JsonResponse {
        $actor = $request->user();
        Gate::authorize('viewAny', DashboardSummary::class);

        $center = $actor->center;
        $month = $periods->month((string) $request->input('month'), $center->timezone);

        $summary = $query->execute(
            actor: $actor,
            center: $center,
            month: $month,
            groupId: $request->filled('group_id') ? (int) $request->input('group_id') : null,
        );

        return response()->json(['data' => $summary->toArray()]);
    }
}
