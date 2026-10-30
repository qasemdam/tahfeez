<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Students\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Student::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:160'],
            'guardian_name' => ['required', 'string', 'max:160'],
            'guardian_phone' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:32'],
            'grade_level' => ['nullable', 'string', 'max:64'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'age_years' => ['nullable', 'integer', 'min:4', 'max:100'],
            'enrolled_at' => ['required', 'date'],
            'status' => ['nullable', Rule::in([Student::STATUS_ACTIVE, Student::STATUS_INACTIVE])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'اسم الطالب مطلوب.',
            'guardian_name.required' => 'اسم ولي الأمر مطلوب.',
            'enrolled_at.required' => 'تاريخ التسجيل مطلوب.',
        ];
    }
}
