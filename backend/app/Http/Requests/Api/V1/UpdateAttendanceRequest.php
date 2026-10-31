<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Attendance\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation only; the controller resolves the record in scope (404) and then
 * authorizes `update` (403) before this payload is applied.
 */
class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::in(Attendance::STATUSES)],
            'late_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
