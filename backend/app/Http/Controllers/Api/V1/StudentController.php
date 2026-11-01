<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Students\Actions\AssignStudentToTeacher;
use App\Domain\Students\Models\Student;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudentController extends Controller
{
    /**
     * A teacher only ever sees actively assigned students (BR-AUT-02); the
     * scope is applied in the query, not in the controller.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Student::query()
            ->visibleTo($request->user())
            ->with('activeAssignment.teacher');

        $search = $request->query('search');
        if (is_string($search) && $search !== '') {
            $query->where('full_name', 'like', '%'.$search.'%');
        }

        return StudentResource::collection(
            $query->orderBy('full_name')->paginate(50)
        );
    }

    public function store(StoreStudentRequest $request, AssignStudentToTeacher $assign): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validated();

        $student = Student::create([
            'center_id' => $actor->center_id,
            'full_name' => $data['full_name'],
            'guardian_name' => $data['guardian_name'],
            'guardian_phone' => $data['guardian_phone'] ?? null,
            'phone' => $data['phone'] ?? null,
            'grade_level' => $data['grade_level'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'age_years' => $data['age_years'] ?? null,
            'enrolled_at' => $data['enrolled_at'],
            'status' => $data['status'] ?? Student::STATUS_ACTIVE,
            'notes' => $data['notes'] ?? null,
        ]);

        if (! empty($data['teacher_id'])) {
            $teacher = User::query()
                ->where('center_id', $actor->center_id)
                ->findOrFail((int) $data['teacher_id']);

            $assign->execute($student, $teacher, $actor);
        }

        return (new StudentResource($student->load('activeAssignment.teacher')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Out-of-scope students return 404, not 403, to avoid enumeration
     * (BR-TEN-02, BR-AUT-02).
     */
    public function show(Request $request, int $student): StudentResource
    {
        $model = Student::query()
            ->visibleTo($request->user())
            ->with('activeAssignment.teacher')
            ->findOrFail($student);

        return new StudentResource($model);
    }
}
