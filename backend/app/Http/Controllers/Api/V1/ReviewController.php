<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Quran\Contracts\QuranDataProvider;
use App\Domain\Review\Actions\DeleteReview;
use App\Domain\Review\Actions\RecordReview;
use App\Domain\Review\Actions\UpdateReview;
use App\Domain\Review\Models\Review;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReviewRequest;
use App\Http\Requests\Api\V1\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Http\Support\AttachesSurahNames;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    use AttachesSurahNames;

    public function index(Request $request, QuranDataProvider $quran): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Review::class);

        $query = Review::query()
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
            $query->where('review_date', (string) $request->query('date'));
        }

        $records = $query->orderByDesc('review_date')->orderByDesc('id')->paginate(50);

        $this->attachSurahNames($records->getCollection(), $quran);

        return ReviewResource::collection($records);
    }

    public function store(
        StoreReviewRequest $request,
        RecordReview $record,
        QuranDataProvider $quran,
    ): JsonResponse {
        [$model, $created] = $record->execute($request->user(), $request->validated());

        $model->load(['student', 'group', 'teacher']);
        $this->attachSurahNames(collect([$model]), $quran);

        return (new ReviewResource($model))
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }

    public function show(Request $request, int $review, QuranDataProvider $quran): ReviewResource
    {
        $model = $this->resolveVisible($request, $review);
        Gate::authorize('view', $model);

        $model->load(['student', 'group', 'teacher']);
        $this->attachSurahNames(collect([$model]), $quran);

        return new ReviewResource($model);
    }

    public function update(
        UpdateReviewRequest $request,
        int $review,
        UpdateReview $update,
        QuranDataProvider $quran,
    ): ReviewResource {
        $model = $this->resolveVisible($request, $review);
        Gate::authorize('update', $model);

        $update->execute($model, $request->validated());
        $model->load(['student', 'group', 'teacher']);
        $this->attachSurahNames(collect([$model]), $quran);

        return new ReviewResource($model);
    }

    public function destroy(Request $request, int $review, DeleteReview $delete): Response
    {
        $model = $this->resolveVisible($request, $review);
        Gate::authorize('delete', $model);

        $delete->execute($model);

        return response()->noContent();
    }

    /**
     * Resolves a record inside the actor's visibility scope; out-of-scope ids
     * return 404, not 403 (BR-TEN-02 / BR-AUT-02).
     */
    private function resolveVisible(Request $request, int $reviewId): Review
    {
        return Review::query()
            ->visibleTo($request->user())
            ->findOrFail($reviewId);
    }
}
