<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Quran\Contracts\QuranDataProvider;
use App\Domain\Recitation\Actions\DeleteRecitation;
use App\Domain\Recitation\Actions\RecordRecitation;
use App\Domain\Recitation\Actions\UpdateRecitation;
use App\Domain\Recitation\Models\Recitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRecitationRequest;
use App\Http\Requests\Api\V1\UpdateRecitationRequest;
use App\Http\Resources\RecitationResource;
use App\Http\Support\AttachesSurahNames;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class RecitationController extends Controller
{
    use AttachesSurahNames;

    public function index(Request $request, QuranDataProvider $quran): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Recitation::class);

        $query = Recitation::query()
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
            $query->where('recitation_date', (string) $request->query('date'));
        }

        $records = $query->orderByDesc('recitation_date')->orderByDesc('id')->paginate(50);

        $this->attachSurahNames($records->getCollection(), $quran);

        return RecitationResource::collection($records);
    }

    public function store(
        StoreRecitationRequest $request,
        RecordRecitation $record,
        QuranDataProvider $quran,
    ): JsonResponse {
        [$model, $created] = $record->execute($request->user(), $request->validated());

        $model->load(['student', 'group', 'teacher']);
        $this->attachSurahNames(collect([$model]), $quran);

        return (new RecitationResource($model))
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }

    public function show(Request $request, int $recitation, QuranDataProvider $quran): RecitationResource
    {
        $model = $this->resolveVisible($request, $recitation);
        Gate::authorize('view', $model);

        $model->load(['student', 'group', 'teacher']);
        $this->attachSurahNames(collect([$model]), $quran);

        return new RecitationResource($model);
    }

    public function update(
        UpdateRecitationRequest $request,
        int $recitation,
        UpdateRecitation $update,
        QuranDataProvider $quran,
    ): RecitationResource {
        $model = $this->resolveVisible($request, $recitation);
        Gate::authorize('update', $model);

        $update->execute($model, $request->validated());
        $model->load(['student', 'group', 'teacher']);
        $this->attachSurahNames(collect([$model]), $quran);

        return new RecitationResource($model);
    }

    public function destroy(Request $request, int $recitation, DeleteRecitation $delete): Response
    {
        $model = $this->resolveVisible($request, $recitation);
        Gate::authorize('delete', $model);

        $delete->execute($model);

        return response()->noContent();
    }

    /**
     * Resolves a record inside the actor's visibility scope; out-of-scope ids
     * return 404, not 403 (BR-TEN-02 / BR-AUT-02).
     */
    private function resolveVisible(Request $request, int $recitationId): Recitation
    {
        return Recitation::query()
            ->visibleTo($request->user())
            ->findOrFail($recitationId);
    }
}
