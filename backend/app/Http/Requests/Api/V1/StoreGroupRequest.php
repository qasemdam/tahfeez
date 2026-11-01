<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Groups\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Group::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'teacher_id' => ['required', 'integer'],
            'status' => ['nullable', Rule::in([Group::STATUS_ACTIVE, Group::STATUS_INACTIVE])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الحلقة مطلوب.',
            'teacher_id.required' => 'معلم الحلقة مطلوب.',
        ];
    }
}
