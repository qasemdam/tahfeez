<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Attendance\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attendance_date' => ['required', 'date_format:Y-m-d'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'integer', 'distinct'],
            'records.*.status' => ['required', Rule::in(Attendance::STATUSES)],
            'records.*.late_minutes' => ['nullable', 'integer', 'min:0'],
            'records.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'records.required' => 'لا توجد سجلات حضور.',
            'records.*.student_id.distinct' => 'تكرر الطالب في القائمة.',
            'records.*.status.required' => 'حالة كل طالب مطلوبة.',
        ];
    }
}
