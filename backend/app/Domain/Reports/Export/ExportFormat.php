<?php

namespace App\Domain\Reports\Export;

/**
 * A file format a report can be exported to (BR-RPT-03).
 *
 * `Html` is the print view: it is served inline so the browser can render and
 * print it, while `Pdf` and `Xlsx` are served as attachments. The format owns
 * its own content type and disposition so no caller has to map strings.
 */
enum ExportFormat: string
{
    case Pdf = 'pdf';
    case Xlsx = 'xlsx';
    case Html = 'html';

    public function extension(): string
    {
        return $this->value;
    }

    public function contentType(): string
    {
        return match ($this) {
            self::Pdf => 'application/pdf',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Html => 'text/html; charset=UTF-8',
        };
    }

    /**
     * A print view is meant to be shown, not saved; a file is meant to be saved.
     */
    public function disposition(): string
    {
        return $this === self::Html ? 'inline' : 'attachment';
    }
}
