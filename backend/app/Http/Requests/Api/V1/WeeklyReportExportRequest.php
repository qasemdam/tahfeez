<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Reports\DTOs\WeeklyReport;
use App\Domain\Reports\Export\ExportFormat;
use Illuminate\Validation\Rule;

/**
 * The weekly export accepts exactly the filters of the weekly report, plus the
 * output format. The period rules are inherited, not restated: an export can
 * never accept a parameter the report rejects.
 */
class WeeklyReportExportRequest extends WeeklyReportRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('export', WeeklyReport::class) ?? false;
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
