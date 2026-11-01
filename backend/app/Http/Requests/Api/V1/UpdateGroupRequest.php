<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Groups\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation only; the controller resolves the group in scope (404) and then
 * authorizes `update` (403) before this payload is applied.
 */
class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:120'],
            'teacher_id' => ['sometimes', 'required', 'integer'],
            'status' => ['sometimes', 'required', Rule::in([Group::STATUS_ACTIVE, Group::STATUS_INACTIVE])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
