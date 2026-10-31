<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Memorization\Models\Memorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation only; the controller resolves the record in scope (404) and then
 * authorizes `update` (403) before this payload is applied.
 */
class UpdateMemorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount_pages' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'grade' => ['sometimes', 'nullable', Rule::in(Memorization::GRADES)],
            'hifz_errors' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'tajweed_errors' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'reading_errors' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
