<?php

namespace App\Domain\Reports\Export;

/**
 * One column of an export table: a label plus the type of the values below it.
 *
 * The column formats a value for a text target (HTML, PDF) and exposes the
 * number format for a spreadsheet. It never computes anything.
 */
final readonly class ExportColumn
{
    public function __construct(
        public string $label,
        public ExportValueType $type = ExportValueType::Text,
        public int $scale = 2,
    ) {}

    public static function text(string $label): self
    {
        return new self($label, ExportValueType::Text);
    }

    public static function integer(string $label): self
    {
        return new self($label, ExportValueType::Integer, 0);
    }

    /**
     * A decimal amount, e.g. pages. The scale mirrors the stored precision.
     */
    public static function decimal(string $label, int $scale = 4): self
    {
        return new self($label, ExportValueType::Decimal, $scale);
    }

    public static function percentage(string $label): self
    {
        return new self($label, ExportValueType::Percentage, 2);
    }

    public static function score(string $label): self
    {
        return new self($label, ExportValueType::Score, 2);
    }

    /**
     * A missing value is shown as an em dash, never as a zero: an undefined
     * ratio must stay visibly undefined.
     */
    public function display(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return $this->type === ExportValueType::Percentage ? $value.'%' : $value;
    }

    public function numberFormat(): string
    {
        return $this->type->numberFormat($this->scale);
    }
}
