<?php

namespace App\Domain\Reports\Export\Renderers;

use App\Domain\Reports\Export\ExportFormat;
use App\Domain\Reports\Export\ExportGuard;
use App\Domain\Reports\Export\ExportPayload;
use App\Domain\Reports\Export\ExportResult;
use App\Domain\Reports\Export\ExportTooLarge;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use Mpdf\MpdfException;

/**
 * Renders the print view to PDF with mPDF.
 *
 * mPDF shapes Arabic and applies the bidi algorithm; TCPDF 7 is a compatibility
 * facade whose font data is not installed, so it cannot embed an Arabic face at
 * all.
 *
 * The face is pinned to DejaVu Sans on purpose. `autoLangToFont` would switch
 * Arabic runs to XB Riyaz, a font bundled with mPDF whose own name table
 * declares the GNU General Public License — not a licence this project can rely
 * on for embedding. DejaVu ships with the library under the Bitstream Vera
 * licence, which explicitly permits embedding, and it carries the Arabic GSUB
 * tables mPDF needs to shape the script. `autoScriptToLang` stays on: script
 * detection still drives shaping, it just no longer swaps the font.
 */
final class PdfReportRenderer
{
    public function __construct(private ExportGuard $guard) {}

    public function render(ExportPayload $payload): ExportResult
    {
        $html = view('exports.report', [
            'payload' => $payload,
            'forPdf' => true,
        ])->render();

        // The engine refuses an HTML string larger than `pcre.backtrack_limit`
        // and throws. The budget is checked here so the failure is a typed
        // capacity error, not an engine exception that reaches the client as 500.
        $this->guard->assertHtmlFits($payload, $html);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $payload->kind->landscape() ? 'A4-L' : 'A4',
            'directionality' => 'rtl',
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
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

        try {
            $mpdf->WriteHTML($html);
        } catch (MpdfException $exception) {
            // Last resort: a host with an unusual pcre limit can still trip the
            // engine's own check. It is a capacity problem, so it is reported as
            // one instead of surfacing as an unhandled 500.
            if (str_contains($exception->getMessage(), 'pcre.backtrack_limit')) {
                throw ExportTooLarge::htmlBytes(
                    ExportFormat::Pdf,
                    strlen($html),
                    $this->guard->htmlBudget() ?? strlen($html),
                );
            }

            throw $exception;
        }

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
