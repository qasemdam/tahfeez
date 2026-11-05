<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Reports\DTOs\DashboardSummary;
use App\Domain\Reports\Export\ExportFormat;
use Illuminate\Validation\Rule;

/**
 * The dashboard export accepts exactly the filters of the dashboard, plus the
 * output format.
 */
class DashboardExportRequest extends DashboardRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('export', DashboardSummary::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'format' => ['nullable', Rule::enum(ExportFormat::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'format.enum' => 'صيغة التصدير غير مدعومة، استخدم pdf أو xlsx أو html.',
        ];
    }

    public function exportFormat(): ExportFormat
    {
        return $this->enum('format', ExportFormat::class) ?? ExportFormat::Html;
    }
}
