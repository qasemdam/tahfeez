<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Reports\DTOs\WeeklyReport;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The period is never derived on the client.
 *
 * `week_key` is a reference date inside the week; the server resolves the
 * boundaries from the center's start day. `week_start_date` pins the boundary
 * verbatim, which is what a historical report needs after the center's start
 * day has changed.
 */
class WeeklyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', WeeklyReport::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'week_key' => ['nullable', 'date_format:Y-m-d', 'required_without:week_start_date'],
            'week_start_date' => ['nullable', 'date_format:Y-m-d', 'required_without:week_key', 'prohibits:week_key'],
            'group_id' => ['nullable', 'integer', 'min:1'],
            'student_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'week_key.required_without' => 'حدد الأسبوع عبر week_key أو week_start_date.',
            'week_start_date.required_without' => 'حدد الأسبوع عبر week_key أو week_start_date.',
            'week_start_date.prohibits' => 'استخدم week_key أو week_start_date، وليس كليهما.',
        ];
    }
}
