<?php

namespace App\Domain\Reports\Export\Renderers;

use App\Domain\Reports\Export\ExportColumn;
use App\Domain\Reports\Export\ExportFormat;
use App\Domain\Reports\Export\ExportPayload;
use App\Domain\Reports\Export\ExportResult;
use App\Domain\Reports\Export\ExportSection;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/**
 * Renders an export payload to a real XLSX workbook.
 *
 * One worksheet per section keeps activity types apart — memorization, review,
 * recitation and stored evaluations are never merged into one grid. Sheets are
 * right-to-left, and numeric cells keep the source scale through an explicit
 * number format, so "1.5000" stays "1.5000" and does not become "1.5".
 */
final class XlsxReportRenderer
{
    private const HEADER_FILL = 'E8E8E8';

    private const BORDER_COLOR = '999999';

    private const LABEL_FILL = 'F2F2F2';

    public function render(ExportPayload $payload): ExportResult
    {
        $book = new Spreadsheet;
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $this->writeSummarySheet($book->getActiveSheet(), $payload);

        foreach ($payload->sections as $section) {
            $this->writeSectionSheet($book->createSheet(), $section);
        }

        return new ExportResult(
            ExportFormat::Xlsx,
            $payload->filename(ExportFormat::Xlsx),
            $this->save($book),
        );
    }

    /**
     * The cover sheet: what the file is, for which period, and the totals.
     */
    private function writeSummarySheet(Worksheet $sheet, ExportPayload $payload): void
    {
        $sheet->setTitle('ملخص');
        $sheet->setRightToLeft(true);

        $sheet->setCellValue('A1', $payload->title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', $payload->subtitle);
        $sheet->getStyle('A2')->getFont()->setSize(11);

        $row = 4;

        foreach ($payload->meta as $item) {
            $sheet->setCellValueExplicit('A'.$row, $item['label'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B'.$row, $item['value'], DataType::TYPE_STRING);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            $sheet->getStyle('A'.$row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB(self::LABEL_FILL);
            $row++;
        }

        if ($payload->summary !== []) {
            $row++;

            $sheet->setCellValueExplicit('A'.$row, 'الإجماليات', DataType::TYPE_STRING);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12);
            $row++;

            foreach ($payload->summary as $item) {
                $sheet->setCellValueExplicit('A'.$row, $item['label'], DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('B'.$row, $item['value'], DataType::TYPE_STRING);
                $sheet->getStyle('A'.$row)->getFont()->setBold(true);
                $sheet->getStyle('A'.$row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB(self::LABEL_FILL);
                $row++;
            }
        }

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(40);
    }

    private function writeSectionSheet(Worksheet $sheet, ExportSection $section): void
    {
        $sheet->setTitle($this->uniqueTitle($sheet, $section->sheetTitle()));
        $sheet->setRightToLeft(true);

        $columnCount = count($section->columns);

        if ($columnCount === 0) {
            return;
        }

        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);

        foreach ($section->columns as $index => $column) {
            $sheet->setCellValueExplicit(
                Coordinate::stringFromColumnIndex($index + 1).'1',
                $column->label,
                DataType::TYPE_STRING,
            );
        }

        $sheet->getStyle('A1:'.$lastColumn.'1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::HEADER_FILL],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => self::BORDER_COLOR],
                ],
            ],
        ]);

        $row = 2;

        foreach ($section->rows as $values) {
            $this->writeRow($sheet, $section->columns, $values, $row);
            $row++;
        }

        $lastRow = $row - 1;

        if ($section->totals !== null) {
            $this->writeRow($sheet, $section->columns, $section->totals, $row, true);
            $lastRow = $row;
        }

        if ($lastRow >= 2) {
            $sheet->getStyle('A2:'.$lastColumn.$lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => self::BORDER_COLOR],
                    ],
                ],
            ]);
        }

        $sheet->freezePane('A2');

        foreach ($section->columns as $index => $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))
                ->setWidth($this->width($section, $index, $column));
        }
    }

    /**
     * @param  array<int, ExportColumn>  $columns
     * @param  array<int, string|null>  $values
     */
    private function writeRow(Worksheet $sheet, array $columns, array $values, int $row, bool $isTotal = false): void
    {
        foreach ($columns as $index => $column) {
            $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($index + 1).$row);
            $this->writeCell($cell, $column, $values[$index] ?? null, $isTotal);

            if ($column->type->isNumeric()) {
                $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            if ($isTotal) {
                $cell->getStyle()->getFont()->setBold(true);
                $cell->getStyle()->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB(self::HEADER_FILL);
            }
        }
    }

    /**
     * A numeric value is stored as a number so Excel can still sum it; the
     * number format is what preserves the decimal scale on screen.
     */
    private function writeCell(Cell $cell, ExportColumn $column, ?string $value, bool $isTotal = false): void
    {
        if ($value === null || $value === '') {
            // A totals row leaves the text columns blank; a data row marks the
            // value as missing so an undefined figure is never read as zero.
            $cell->setValueExplicit($isTotal ? '' : '—', DataType::TYPE_STRING);

            return;
        }

        if ($column->type->isNumeric() && is_numeric($value)) {
            $cell->setValue((float) $value);
            $cell->getStyle()->getNumberFormat()->setFormatCode($column->numberFormat());

            return;
        }

        $cell->setValueExplicit($value, DataType::TYPE_STRING);
    }

    private function width(ExportSection $section, int $index, ExportColumn $column): float
    {
        $length = mb_strlen($column->label);

        foreach ($section->rows as $row) {
            $length = max($length, mb_strlen((string) ($row[$index] ?? '')));
        }

        if ($section->totals !== null) {
            $length = max($length, mb_strlen((string) ($section->totals[$index] ?? '')));
        }

        return (float) min(max($length + 4, 10), 38);
    }

    /**
     * Excel sheet names are unique, at most 31 characters, and never blank.
     */
    private function uniqueTitle(Worksheet $sheet, string $title): string
    {
        $base = $title === '' ? 'ورقة' : $title;
        $candidate = $base;
        $suffix = 2;

        while ($sheet->getParent()->sheetNameExists($candidate)) {
            $tail = ' ('.$suffix.')';
            $candidate = mb_substr($base, 0, 31 - mb_strlen($tail)).$tail;
            $suffix++;
        }

        return $candidate;
    }

    private function save(Spreadsheet $book): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tahfeez-xlsx-');

        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary file for the XLSX export.');
        }

        try {
            (new Xlsx($book))->save($path);

            $contents = file_get_contents($path);

            if ($contents === false) {
                throw new RuntimeException('Unable to read the generated XLSX export.');
            }

            return $contents;
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }

            $book->disconnectWorksheets();
        }
    }
}
