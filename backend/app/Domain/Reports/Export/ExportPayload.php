<?php

namespace App\Domain\Reports\Export;

/**
 * A report rendered to a presentation-neutral document.
 *
 * This is the seam between "what the report says" and "how it is written": a
 * builder produces the payload from a report DTO, and every renderer (HTML,
 * PDF, XLSX) reads only this structure. No renderer touches the domain.
 */
final readonly class ExportPayload
{
    /**
     * @param  array<int, array{label: string, value: string}>  $meta
     * @param  array<int, ExportSection>  $sections
     * @param  array<int, array{label: string, value: string}>  $summary
     */
    public function __construct(
        public ReportKind $kind,
        public string $title,
        public string $subtitle,
        public string $periodKey,
        public array $meta,
        public array $sections,
        public array $summary,
        public string $generatedAt,
    ) {}

    /**
     * An ASCII file name: the header is not the place to test a client's
     * filename encoding.
     */
    public function filename(ExportFormat $format): string
    {
        $period = preg_replace('/[^0-9A-Za-z_-]/', '', $this->periodKey) ?? $this->periodKey;

        return sprintf('tahfeez-%s-%s.%s', $this->kind->value, $period, $format->extension());
    }
}
