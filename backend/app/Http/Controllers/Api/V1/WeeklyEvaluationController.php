<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Evaluation\Actions\ApproveWeeklyEvaluation;
use App\Domain\Evaluation\Actions\CreateWeeklyEvaluation;
use App\Domain\Evaluation\Models\WeeklyEvaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWeeklyEvaluationRequest;
use App\Http\Requests\Api\V1\UpdateWeeklyEvaluationRequest;
use App\Http\Resources\WeeklyEvaluationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WeeklyEvaluationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', WeeklyEvaluation::class);

        $query = WeeklyEvaluation::query()
            ->visibleTo($request->user())
            ->with(['student', 'group', 'teacher']);

        if ($request->filled('student_id')) {
            $query->where('student_id', (int) $request->query('student_id'));
        }

        if ($request->filled('group_id')) {
            $query->where('group_id', (int) $request->query('group_id'));
        }

        if ($request->filled('week_key')) {
            $request->validate(['week_key' => ['date_format:Y-m-d']]);
            $query->where('week_key', (string) $request->query('week_key'));
        }

        if ($request->filled('week_start_date')) {
            $request->validate(['week_start_date' => ['date_format:Y-m-d']]);
            $query->where('week_start_date', (string) $request->query('week_start_date'));
        }

        $evaluations = $query->orderByDesc('week_start_date')->orderByDesc('id')->paginate(50);

        return WeeklyEvaluationResource::collection($evaluations);
    }

    public function store(
        StoreWeeklyEvaluationRequest $request,
        CreateWeeklyEvaluation $create,
    ): JsonResponse {
        [$evaluation, $created] = $create->execute($request->user(), $request->validated());

        $evaluation->load(['student', 'group', 'teacher']);

        return (new WeeklyEvaluationResource($evaluation))
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }

    public function show(Request $request, int $weeklyEvaluation): WeeklyEvaluationResource
    {
        $model = $this->resolveVisible($request, $weeklyEvaluation);
        Gate::authorize('view', $model);

        $model->load(['student', 'group', 'teacher']);

        return new WeeklyEvaluationResource($model);
    }

    public function update(
        UpdateWeeklyEvaluationRequest $request,
        int $weeklyEvaluation,
        ApproveWeeklyEvaluation $approve,
    ): WeeklyEvaluationResource {
        $model = $this->resolveVisible($request, $weeklyEvaluation);
        Gate::authorize('update', $model);

        $approve->execute($model, (string) $request->validated()['status']);
        $model->load(['student', 'group', 'teacher']);

        return new WeeklyEvaluationResource($model);
    }

    /**
     * Resolves a record inside the actor's visibility scope; out-of-scope ids
     * return 404, not 403 (BR-TEN-02 / BR-AUT-02).
     */
    private function resolveVisible(Request $request, int $evaluationId): WeeklyEvaluation
    {
        return WeeklyEvaluation::query()
            ->visibleTo($request->user())
            ->findOrFail($evaluationId);
    }
}
