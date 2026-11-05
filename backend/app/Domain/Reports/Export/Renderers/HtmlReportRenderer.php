<?php

namespace App\Domain\Reports\Export\Renderers;

use App\Domain\Reports\Export\ExportFormat;
use App\Domain\Reports\Export\ExportPayload;
use App\Domain\Reports\Export\ExportResult;

/**
 * Renders the print view.
 *
 * The HTML is the same document mPDF receives, so what a browser prints and
 * what the PDF contains cannot drift apart.
 */
final class HtmlReportRenderer
{
    public function render(ExportPayload $payload): ExportResult
    {
        $html = view('exports.report', [
            'payload' => $payload,
            'forPdf' => false,
        ])->render();

        return new ExportResult(
            ExportFormat::Html,
            $payload->filename(ExportFormat::Html),
            $html,
        );
    }
}
