<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Evaluation\Models\WeeklyEvaluation;
use Illuminate\Foundation\Http\FormRequest;

class StoreWeeklyEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WeeklyEvaluation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer'],
            'week_date' => ['required', 'date_format:Y-m-d'],
            'profile_version_id' => ['nullable', 'integer'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'اختر الطالب.',
            'week_date.required' => 'اختر تاريخًا داخل الأسبوع.',
        ];
    }
}
