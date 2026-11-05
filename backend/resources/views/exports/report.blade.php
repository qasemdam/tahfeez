{{--
    Print / PDF document for a report export.

    One template serves both targets: the browser gets a print button and a
    print stylesheet, mPDF gets the same markup without the toolbar. Every
    numeric value is wrapped in `dir="ltr"` so bidi does not reorder a date or a
    decimal inside an RTL paragraph.
--}}
@php
    $landscape = $payload->kind->landscape();
    $pageSize = $landscape ? 'A4 landscape' : 'A4 portrait';
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $payload->title }}</title>
    <style>
        {{-- mPDF mis-paginates when it sees an `@page` size: it breaks the
             document into one page per block. The PDF page size comes from the
             engine instead, so the rule is only emitted for the browser. --}}
        @unless ($forPdf ?? false)
            @page {
                size: {{ $pageSize }};
                margin: 10mm 8mm;
            }
        @endunless

        body {
            font-family: 'DejaVu Sans', 'Segoe UI', Tahoma, Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.5;
            color: #111111;
            margin: 0;
            padding: 0;
        }

        h1 {
            font-size: 16pt;
            margin: 0 0 2mm;
        }

        h2 {
            font-size: 11pt;
            margin: 6mm 0 2mm;
            padding-bottom: 1mm;
            border-bottom: 1px solid #999999;
        }

        .subtitle {
            font-size: 10pt;
            color: #444444;
            margin: 0 0 3mm;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table.meta,
        table.summary {
            margin: 0 0 3mm;
            font-size: 8.5pt;
        }

        table.meta td,
        table.summary td {
            border: 0.5px solid #bbbbbb;
            padding: 1.2mm 2mm;
            vertical-align: top;
        }

        table.meta td.label,
        table.summary td.label {
            width: 32mm;
            background: #f2f2f2;
            font-weight: bold;
        }

        table.data {
            font-size: 8pt;
            margin-bottom: 2mm;
        }

        table.data th,
        table.data td {
            border: 0.5px solid #999999;
            padding: 1.2mm 1.5mm;
            text-align: right;
        }

        table.data thead th {
            background: #e8e8e8;
            font-weight: bold;
            white-space: nowrap;
        }

        table.data thead {
            display: table-header-group;
        }

        table.data tfoot td {
            background: #f6f6f6;
            font-weight: bold;
        }

        td.numeric,
        th.numeric {
            text-align: center;
        }

        td.empty {
            text-align: center;
            color: #666666;
        }

        .doc-footer {
            margin-top: 6mm;
            padding-top: 2mm;
            border-top: 1px solid #cccccc;
            font-size: 7.5pt;
            color: #666666;
            text-align: right;
        }

        .toolbar {
            margin: 0 0 5mm;
            padding: 3mm;
            background: #f2f2f2;
            border: 1px solid #dddddd;
        }

        .toolbar button {
            font-family: inherit;
            font-size: 10pt;
            padding: 2mm 5mm;
            cursor: pointer;
        }

        .toolbar .hint {
            font-size: 8pt;
            color: #555555;
            margin-inline-start: 3mm;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                font-size: 8.5pt;
            }

            h2 {
                page-break-after: avoid;
            }

            table.data {
                page-break-inside: auto;
            }

            table.data tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
@unless ($forPdf ?? false)
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">طباعة</button>
        <span class="hint">تُخفى هذه الأزرار عند الطباعة.</span>
    </div>
@endunless

<h1>{{ $payload->title }}</h1>
<p class="subtitle">{!! \App\Domain\Reports\Export\Support\ExportText::isolate($payload->subtitle) !!}</p>

<table class="meta">
    <tbody>
    @foreach ($payload->meta as $index => $item)
        @if ($index % 2 === 0)
            <tr>
                <td class="label">{{ $item['label'] }}</td>
                <td>{!! \App\Domain\Reports\Export\Support\ExportText::isolate($item['value']) !!}</td>
                @if (isset($payload->meta[$index + 1]))
                    <td class="label">{{ $payload->meta[$index + 1]['label'] }}</td>
                    <td>{!! \App\Domain\Reports\Export\Support\ExportText::isolate($payload->meta[$index + 1]['value']) !!}</td>
                @else
                    <td class="label"></td>
                    <td></td>
                @endif
            </tr>
        @endif
    @endforeach
    </tbody>
</table>

@if ($payload->summary !== [])
    <table class="summary">
        <tbody>
        @foreach ($payload->summary as $index => $item)
            @if ($index % 3 === 0)
                <tr>
                    <td class="label">{{ $item['label'] }}</td>
                    <td><span dir="ltr">{{ $item['value'] }}</span></td>
                    @if (isset($payload->summary[$index + 1]))
                        <td class="label">{{ $payload->summary[$index + 1]['label'] }}</td>
                        <td><span dir="ltr">{{ $payload->summary[$index + 1]['value'] }}</span></td>
                    @else
                        <td class="label"></td>
                        <td></td>
                    @endif
                    @if (isset($payload->summary[$index + 2]))
                        <td class="label">{{ $payload->summary[$index + 2]['label'] }}</td>
                        <td><span dir="ltr">{{ $payload->summary[$index + 2]['value'] }}</span></td>
                    @else
                        <td class="label"></td>
                        <td></td>
                    @endif
                </tr>
            @endif
        @endforeach
        </tbody>
    </table>
@endif

@foreach ($payload->sections as $section)
    <h2>{{ $section->heading }}</h2>

    <table class="data">
        <thead>
        <tr>
            @foreach ($section->columns as $column)
                <th class="{{ $column->type->isNumeric() ? 'numeric' : '' }}">{{ $column->label }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @forelse ($section->rows as $row)
            <tr>
                @foreach ($row as $index => $value)
                    @php($column = $section->columns[$index] ?? null)
                    @if ($column !== null && $column->type->isNumeric())
                        <td class="numeric"><span dir="ltr">{{ $column->display($value) }}</span></td>
                    @else
                        <td>{{ $column?->display($value) ?? '—' }}</td>
                    @endif
                @endforeach
            </tr>
        @empty
            <tr>
                <td class="empty" colspan="{{ count($section->columns) }}">لا توجد بيانات للفترة المحددة.</td>
            </tr>
        @endforelse
        </tbody>
        @if ($section->totals !== null)
            <tfoot>
            <tr>
                @foreach ($section->totals as $index => $value)
                    @php($column = $section->columns[$index] ?? null)
                    @if ($column !== null && $column->type->isNumeric())
                        <td class="numeric"><span dir="ltr">{{ $column->display($value) }}</span></td>
                    @else
                        <td>{{ $value ?? '' }}</td>
                    @endif
                @endforeach
            </tr>
            </tfoot>
        @endif
    </table>
@endforeach

<p class="doc-footer">أُنشئ في {!! \App\Domain\Reports\Export\Support\ExportText::isolate($payload->generatedAt) !!} — {{ $payload->meta[0]['value'] ?? '' }}</p>
</body>
</html>
