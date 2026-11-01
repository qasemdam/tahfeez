<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Attendance\Actions\RecordAttendance;
use App\Domain\Attendance\Actions\UpdateAttendance;
use App\Domain\Attendance\Models\Attendance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAttendanceRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AttendanceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Attendance::class);

        $query = Attendance::query()
            ->visibleTo($request->user())
            ->with(['student', 'group', 'teacher']);

        if ($request->filled('group_id')) {
            $query->where('group_id', (int) $request->query('group_id'));
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', (int) $request->query('student_id'));
        }

        if ($request->filled('date')) {
            $request->validate(['date' => ['date_format:Y-m-d']]);
            $query->where('attendance_date', (string) $request->query('date'));
        }

        return AttendanceResource::collection(
            $query->orderByDesc('attendance_date')->orderBy('student_id')->paginate(100)
        );
    }

    public function store(StoreAttendanceRequest $request, RecordAttendance $record): JsonResponse
    {
        [$attendance, $created] = $record->execute($request->user(), $request->validated());
        $attendance->load(['student', 'group', 'teacher']);

        return (new AttendanceResource($attendance))
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }

    public function show(Request $request, int $attendance): AttendanceResource
    {
        $model = $this->resolveVisible($request, $attendance);
        Gate::authorize('view', $model);

        $model->load(['student', 'group', 'teacher']);

        return new AttendanceResource($model);
    }

    public function update(
        UpdateAttendanceRequest $request,
        int $attendance,
        UpdateAttendance $update,
    ): AttendanceResource {
        $model = $this->resolveVisible($request, $attendance);
        Gate::authorize('update', $model);

        $update->execute($model, $request->validated());
        $model->load(['student', 'group', 'teacher']);

        return new AttendanceResource($model);
    }

    /**
     * Resolves a record inside the actor's visibility scope; out-of-scope ids
     * return 404, not 403 (BR-TEN-02 / BR-AUT-02).
     */
    private function resolveVisible(Request $request, int $attendanceId): Attendance
    {
        return Attendance::query()
            ->visibleTo($request->user())
            ->findOrFail($attendanceId);
    }
}
