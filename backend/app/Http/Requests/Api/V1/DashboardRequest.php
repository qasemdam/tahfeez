<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Reports\DTOs\DashboardSummary;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `month` is required: the dashboard never guesses "this month" from the
 * server clock, so the same request always answers for the same period.
 */
class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', DashboardSummary::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'month' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'group_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'month.required' => 'حدد الشهر بصيغة YYYY-MM.',
            'month.regex' => 'صيغة الشهر غير صحيحة، استخدم YYYY-MM.',
        ];
    }
}
