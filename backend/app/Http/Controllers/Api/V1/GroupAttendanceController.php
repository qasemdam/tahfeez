<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Attendance\Actions\RecordGroupAttendance;
use App\Domain\Attendance\Models\Attendance;
use App\Domain\Groups\Models\Group;
use App\Domain\Students\Models\Student;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreGroupAttendanceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The daily sheet: choose a group + date, get every active student with their
 * status (null = not recorded yet), then save the whole sheet in one call.
 */
class GroupAttendanceController extends Controller
{
    public function sheet(Request $request, int $group): JsonResponse
    {
        $model = $this->resolveVisibleGroup($request, $group);
        Gate::authorize('view', $model);

        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = $data['date'] ?? now($model->center->timezone)->toDateString();

        return response()->json(['data' => $this->sheetPayload($model, $date)]);
    }

    public function store(
        StoreGroupAttendanceRequest $request,
        int $group,
        RecordGroupAttendance $record,
    ): JsonResponse {
        $model = $this->resolveVisibleGroup($request, $group);
        Gate::authorize('recordAttendance', $model);

        $data = $request->validated();
        $record->execute($model, $request->user(), $data['attendance_date'], $data['records']);

        return response()->json([
            'data' => $this->sheetPayload($model, $data['attendance_date']),
        ]);
    }

    private function resolveVisibleGroup(Request $request, int $groupId): Group
    {
        return Group::query()
            ->visibleTo($request->user())
            ->findOrFail($groupId);
    }

    /**
     * @return array<string, mixed>
     */
    private function sheetPayload(Group $group, string $date): array
    {
        $group->load(['teacher', 'activeStudents']);

        $studentIds = $group->activeStudents->pluck('id')->all();

        $records = Attendance::query()
            ->where('center_id', $group->center_id)
            ->whereIn('student_id', $studentIds)
            ->whereDate('attendance_date', $date)
            ->get()
            ->keyBy('student_id');

        return [
            'group_id' => $group->id,
            'group_name' => $group->name,
            'teacher_id' => $group->teacher_id,
            'teacher_name' => $group->teacher?->name,
            'date' => $date,
            'students' => $group->activeStudents->map(function (Student $student) use ($records): array {
                $record = $records->get($student->id);

                return [
                    'student_id' => $student->id,
                    'full_name' => $student->full_name,
                    'grade_level' => $student->grade_level,
                    'attendance_id' => $record?->id,
                    'status' => $record?->status,
                    'late_minutes' => $record?->late_minutes,
                    'notes' => $record?->notes,
                ];
            })->values(),
        ];
    }
}
