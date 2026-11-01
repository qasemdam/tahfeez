<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Evaluation\Models\WeeklyEvaluation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Only the status transition is allowed. The computed values and snapshots
 * are immutable; a correction/revision flow is out of scope.
 */
class UpdateWeeklyEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([WeeklyEvaluation::STATUS_APPROVED])],
        ];
    }
}
