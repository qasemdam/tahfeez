<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AddGroupStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'اختر الطالب.',
        ];
    }
}
