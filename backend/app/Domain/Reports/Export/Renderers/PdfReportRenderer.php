<?php

namespace App\Domain\Reports\Export\Renderers;

use App\Domain\Reports\Export\ExportFormat;
use App\Domain\Reports\Export\ExportPayload;
use App\Domain\Reports\Export\ExportResult;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;

/**
 * Renders the print view to PDF with mPDF.
 *
 * mPDF is used because it shapes Arabic and applies the bidi algorithm, and it
 * ships a font that covers Arabic — TCPDF 7 is a compatibility facade whose
 * font data is not installed, so it cannot embed an Arabic face at all.
 *
 * `autoScriptToLang` + `autoLangToFont` let mPDF pick the face per script, and
 * the document is written RTL so tables and headings mirror correctly.
 */
final class PdfReportRenderer
{
    public function render(ExportPayload $payload): ExportResult
    {
        $html = view('exports.report', [
            'payload' => $payload,
            'forPdf' => true,
        ])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $payload->kind->landscape() ? 'A4-L' : 'A4',
            'directionality' => 'rtl',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'default_font' => 'dejavusans',
            'default_font_size' => 9,
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 10,
            'margin_bottom' => 14,
            'tempDir' => $this->tempDir(),
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->SetHTMLFooter(
            '<div style="text-align:center;font-size:7.5pt;color:#666666;">'
            .'صفحة {PAGENO} من {nbpg}'
            .'</div>',
        );
        $mpdf->WriteHTML($html);

        return new ExportResult(
            ExportFormat::Pdf,
            $payload->filename(ExportFormat::Pdf),
            $mpdf->Output('', 'S'),
        );
    }

    /**
     * mPDF needs a writable scratch directory; it refuses to guess one.
     */
    private function tempDir(): string
    {
        $path = storage_path('framework/cache/mpdf');

        File::ensureDirectoryExists($path);

        return $path;
    }
}
