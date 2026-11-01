<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Memorization\Actions\DeleteMemorization;
use App\Domain\Memorization\Actions\RecordMemorization;
use App\Domain\Memorization\Actions\UpdateMemorization;
use App\Domain\Memorization\Models\Memorization;
use App\Domain\Quran\Contracts\QuranDataProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMemorizationRequest;
use App\Http\Requests\Api\V1\UpdateMemorizationRequest;
use App\Http\Resources\MemorizationResource;
use App\Http\Support\AttachesSurahNames;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class MemorizationController extends Controller
{
    use AttachesSurahNames;

    public function index(Request $request, QuranDataProvider $quran): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Memorization::class);

        $query = Memorization::query()
            ->visibleTo($request->user())
            ->with(['student', 'group', 'teacher']);

        if ($request->filled('student_id')) {
            $query->where('student_id', (int) $request->query('student_id'));
        }

        if ($request->filled('group_id')) {
            $query->where('group_id', (int) $request->query('group_id'));
        }

        if ($request->filled('date')) {
            $request->validate(['date' => ['date_format:Y-m-d']]);
            $query->where('memorization_date', (string) $request->query('date'));
        }

        $records = $query->orderByDesc('memorization_date')->orderByDesc('id')->paginate(50);

        $this->attachSurahNames($records->getCollection(), $quran);

        return MemorizationResource::collection($records);
    }

    public function store(
        StoreMemorizationRequest $request,
        RecordMemorization $record,
        QuranDataProvider $quran,
    ): JsonResponse {
        [$model, $created] = $record->execute($request->user(), $request->validated());

        $model->load(['student', 'group', 'teacher']);
        $this->attachSurahNames(collect([$model]), $quran);

        return (new MemorizationResource($model))
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }

    public function show(Request $request, int $memorization, QuranDataProvider $quran): MemorizationResource
    {
        $model = $this->resolveVisible($request, $memorization);
        Gate::authorize('view', $model);

        $model->load(['student', 'group', 'teacher']);
        $this->attachSurahNames(collect([$model]), $quran);

        return new MemorizationResource($model);
    }

    public function update(
        UpdateMemorizationRequest $request,
        int $memorization,
        UpdateMemorization $update,
        QuranDataProvider $quran,
    ): MemorizationResource {
        $model = $this->resolveVisible($request, $memorization);
        Gate::authorize('update', $model);

        $update->execute($model, $request->validated());
        $model->load(['student', 'group', 'teacher']);
        $this->attachSurahNames(collect([$model]), $quran);

        return new MemorizationResource($model);
    }

    public function destroy(Request $request, int $memorization, DeleteMemorization $delete): Response
    {
        $model = $this->resolveVisible($request, $memorization);
        Gate::authorize('delete', $model);

        $delete->execute($model);

        return response()->noContent();
    }

    /**
     * Resolves a record inside the actor's visibility scope; out-of-scope ids
     * return 404, not 403 (BR-TEN-02 / BR-AUT-02).
     */
    private function resolveVisible(Request $request, int $memorizationId): Memorization
    {
        return Memorization::query()
            ->visibleTo($request->user())
            ->findOrFail($memorizationId);
    }
}
