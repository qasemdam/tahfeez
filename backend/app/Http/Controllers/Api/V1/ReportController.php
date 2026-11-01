<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Evaluation\Enums\WeekStartDay;
use App\Domain\Evaluation\Services\WeekPeriodResolver;
use App\Domain\Evaluation\ValueObjects\WeekPeriod;
use App\Domain\Reports\DTOs\WeeklyReport;
use App\Domain\Reports\Queries\WeeklyReportQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WeeklyReportRequest;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
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
        WeeklyReportQuery $query,
    ): JsonResponse {
        $actor = $request->user();
        Gate::authorize('viewAny', WeeklyReport::class);

        $center = $actor->center;
        $period = $this->resolvePeriod($request, $resolver, $center->timezone, $center->weekly_evaluation_start_day);

        $report = $query->execute(
            actor: $actor,
            center: $center,
            period: $period,
            groupId: $request->filled('group_id') ? (int) $request->input('group_id') : null,
            studentId: $request->filled('student_id') ? (int) $request->input('student_id') : null,
        );

        return response()->json(['data' => $report->toArray()]);
    }

    private function resolvePeriod(
        WeeklyReportRequest $request,
        WeekPeriodResolver $resolver,
        string $timezone,
        WeekStartDay $startDay,
    ): WeekPeriod {
        if ($request->filled('week_start_date')) {
            $start = CarbonImmutable::createFromFormat(
                'Y-m-d',
                (string) $request->input('week_start_date'),
                $timezone,
            )->startOfDay();

            return new WeekPeriod(
                start: $start->toDateString(),
                end: $start->addDays(6)->toDateString(),
                key: $start->toDateString(),
                startDay: $startDay,
            );
        }

        return $resolver->resolve(
            new DateTimeImmutable((string) $request->input('week_key'), new DateTimeZone($timezone)),
            $startDay,
        );
    }
}
