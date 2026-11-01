<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Groups\Actions\AddStudentToGroup;
use App\Domain\Groups\Actions\CreateGroup;
use App\Domain\Groups\Actions\DeleteGroup;
use App\Domain\Groups\Actions\RemoveStudentFromGroup;
use App\Domain\Groups\Actions\UpdateGroup;
use App\Domain\Groups\Models\Group;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddGroupStudentRequest;
use App\Http\Requests\Api\V1\StoreGroupRequest;
use App\Http\Requests\Api\V1\UpdateGroupRequest;
use App\Http\Resources\GroupResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class GroupController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Group::class);

        $groups = Group::query()
            ->visibleTo($request->user())
            ->with('teacher')
            ->withCount('activeStudents')
            ->orderBy('name')
            ->paginate(50);

        return GroupResource::collection($groups);
    }

    public function store(StoreGroupRequest $request, CreateGroup $create): JsonResponse
    {
        $group = $create->execute($request->user(), $request->validated());
        $group->load('teacher')->loadCount('activeStudents');

        return (new GroupResource($group))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $group): GroupResource
    {
        $model = $this->resolveVisible($request, $group);
        Gate::authorize('view', $model);

        $model->load(['teacher', 'activeStudents'])->loadCount('activeStudents');

        return new GroupResource($model);
    }

    public function update(UpdateGroupRequest $request, int $group, UpdateGroup $update): GroupResource
    {
        $model = $this->resolveVisible($request, $group);
        Gate::authorize('update', $model);

        $updated = $update->execute($model, $request->user(), $request->validated());
        $updated->load(['teacher', 'activeStudents'])->loadCount('activeStudents');

        return new GroupResource($updated);
    }

    public function destroy(Request $request, int $group, DeleteGroup $delete): Response
    {
        $model = $this->resolveVisible($request, $group);
        Gate::authorize('delete', $model);

        $delete->execute($model);

        return response()->noContent();
    }

    public function addStudent(AddGroupStudentRequest $request, int $group, AddStudentToGroup $add): GroupResource
    {
        $model = $this->resolveVisible($request, $group);
        Gate::authorize('manageStudents', $model);

        $add->execute($model, $request->user(), (int) $request->validated()['student_id']);

        $model->load(['teacher', 'activeStudents'])->loadCount('activeStudents');

        return new GroupResource($model);
    }

    public function removeStudent(Request $request, int $group, int $student, RemoveStudentFromGroup $remove): Response
    {
        $model = $this->resolveVisible($request, $group);
        Gate::authorize('manageStudents', $model);

        $remove->execute($model, $student);

        return response()->noContent();
    }

    /**
     * Resolves a group inside the actor's visibility scope. Out-of-scope ids
     * return 404, not 403 (BR-TEN-02 / BR-AUT-02).
     */
    private function resolveVisible(Request $request, int $groupId): Group
    {
        return Group::query()
            ->visibleTo($request->user())
            ->findOrFail($groupId);
    }
}
