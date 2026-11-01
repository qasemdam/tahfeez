<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Reports\DTOs\MonthlyReport;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `month` is a calendar month (`YYYY-MM`). It is never defaulted to the current
 * month: a missing or malformed value is a validation error.
 */
class MonthlyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', MonthlyReport::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'month' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'group_id' => ['nullable', 'integer', 'min:1'],
            'student_id' => ['nullable', 'integer', 'min:1'],
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
