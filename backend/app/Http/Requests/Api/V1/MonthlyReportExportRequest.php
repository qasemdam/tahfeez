<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Reports\DTOs\MonthlyReport;
use App\Domain\Reports\Export\ExportFormat;
use Illuminate\Validation\Rule;

/**
 * The monthly export accepts exactly the filters of the monthly report, plus
 * the output format.
 */
class MonthlyReportExportRequest extends MonthlyReportRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('export', MonthlyReport::class) ?? false;
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
