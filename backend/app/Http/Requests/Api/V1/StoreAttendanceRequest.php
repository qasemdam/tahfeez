<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Attendance\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Attendance::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer'],
            'attendance_date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', Rule::in(Attendance::STATUSES)],
            'late_minutes' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'اختر الطالب.',
            'attendance_date.required' => 'تاريخ الحضور مطلوب.',
            'status.required' => 'حالة الحضور مطلوبة.',
        ];
    }
}
