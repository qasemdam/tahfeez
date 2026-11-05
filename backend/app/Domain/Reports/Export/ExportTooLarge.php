<?php

namespace App\Domain\Reports\Export;

use RuntimeException;

/**
 * The report is too large to be exported in the current request.
 *
 * This is a capacity limit, not a validation error: the request was well formed
 * and the actor is allowed to export — the result simply does not fit in a
 * synchronous response. It is reported to the client as a 422 with a stable
 * `error` code so a client can tell it apart from a bad filter.
 */
final class ExportTooLarge extends RuntimeException
{
    public const REASON_ROWS = 'rows';

    public const REASON_HTML_BYTES = 'html_bytes';

    private function __construct(
        public readonly string $reason,
        public readonly ExportFormat $format,
        public readonly int $actual,
        public readonly int $limit,
    ) {
        parent::__construct(sprintf(
            'Export exceeds the synchronous limit (%s: %d > %d).',
            $reason,
            $actual,
            $limit,
        ));
    }

    public static function rows(ExportFormat $format, int $rows, int $maxRows): self
    {
        return new self(self::REASON_ROWS, $format, $rows, $maxRows);
    }

    public static function htmlBytes(ExportFormat $format, int $bytes, int $maxBytes): self
    {
        return new self(self::REASON_HTML_BYTES, $format, $bytes, $maxBytes);
    }

    /**
     * The client-facing message: what happened and what to do about it.
     */
    public function userMessage(): string
    {
        if ($this->reason === self::REASON_ROWS) {
            return sprintf(
                'التقرير أكبر من حد التصدير المتزامن (%d صفًا، الحد %d). ضيّق النطاق عبر group_id أو student_id.',
                $this->actual,
                $this->limit,
            );
        }

        return 'حجم التقرير أكبر من حد التصدير المتزامن. ضيّق النطاق عبر group_id أو student_id.';
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'reason' => $this->reason,
            'actual' => $this->actual,
            'limit' => $this->limit,
        ];
    }
}
