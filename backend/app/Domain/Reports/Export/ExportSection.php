<?php

namespace App\Domain\Reports\Export;

/**
 * One table of an export: a heading, the column definitions and the rows.
 *
 * A section keeps a single kind of figure together — attendance, memorization
 * and review in one, recitation in another, stored evaluations in a third — so
 * indicators that must not be mixed are never read from the same table.
 */
final readonly class ExportSection
{
    /**
     * @param  array<int, ExportColumn>  $columns
     * @param  array<int, array<int, string|null>>  $rows
     * @param  array<int, string|null>|null  $totals
     */
    public function __construct(
        public string $heading,
        public array $columns,
        public array $rows,
        public ?array $totals = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    /**
     * A sheet name is limited to 31 characters and may not contain these.
     */
    public function sheetTitle(): string
    {
        $title = str_replace(['\\', '/', '*', '[', ']', ':', '?'], ' ', $this->heading);

        return mb_substr(trim($title), 0, 31);
    }
}
