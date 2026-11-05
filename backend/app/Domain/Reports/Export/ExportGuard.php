<?php

namespace App\Domain\Reports\Export;

/**
 * Enforces the synchronous export ceiling.
 *
 * Two limits, for two different failure modes:
 *
 * 1. **Rows** — protects memory and request time. A report export is built in
 *    RAM inside the request, so the ceiling is what keeps a large center from
 *    taking the worker down with it.
 * 2. **HTML bytes** — protects mPDF specifically. The engine refuses an HTML
 *    string larger than `pcre.backtrack_limit`, so the budget is a share of
 *    that limit, checked before the engine is called.
 *
 * Both raise `ExportTooLarge`, which the API renders as a 422 — never a 500.
 */
final class ExportGuard
{
    public function maxRows(): int
    {
        return max((int) config('exports.max_rows', 200), 1);
    }

    /**
     * The longest table in the payload is the one that scales with students;
     * the other tables repeat the same rows. Counting the longest avoids
     * multiplying the ceiling by the number of sections.
     */
    public function rowCount(ExportPayload $payload): int
    {
        $rows = 0;

        foreach ($payload->sections as $section) {
            $rows = max($rows, count($section->rows));
        }

        return $rows;
    }

    /**
     * @throws ExportTooLarge
     */
    public function assertWithinLimit(ExportPayload $payload, ExportFormat $format): void
    {
        $rows = $this->rowCount($payload);

        if ($rows > $this->maxRows()) {
            throw ExportTooLarge::rows($format, $rows, $this->maxRows());
        }
    }

    /**
     * @throws ExportTooLarge
     */
    public function assertHtmlFits(ExportPayload $payload, string $html): void
    {
        $budget = $this->htmlBudget();

        if ($budget === null || strlen($html) <= $budget) {
            return;
        }

        throw ExportTooLarge::htmlBytes(
            ExportFormat::Pdf,
            strlen($html),
            $budget,
        );
    }

    /**
     * The share of `pcre.backtrack_limit` one document may use, or null when
     * the host has no limit (-1). A budget is always stricter than the engine's
     * own check, so the engine's exception stays a last resort.
     */
    public function htmlBudget(): ?int
    {
        $limit = (int) ini_get('pcre.backtrack_limit');

        if ($limit <= 0) {
            return null;
        }

        $ratio = (float) config('exports.pdf_html_budget_ratio', 0.8);

        return (int) floor($limit * min(max($ratio, 0.1), 1.0));
    }
}
