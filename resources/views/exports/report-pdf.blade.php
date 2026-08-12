@php
    // dompdf resolves @font-face against the local filesystem, so the
    // Devanagari font is embedded from the repo rather than fetched.
    $fontPath = resource_path('fonts');
    $devanagari = app()->getLocale() === 'ne';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @font-face {
            font-family: 'notosansdevanagari';
            font-style: normal;
            font-weight: 400;
            src: url("{{ $fontPath }}/NotoSansDevanagari-Regular.ttf") format('truetype');
        }
        @font-face {
            font-family: 'notosansdevanagari';
            font-style: normal;
            font-weight: 700;
            src: url("{{ $fontPath }}/NotoSansDevanagari-Bold.ttf") format('truetype');
        }

        @page { margin: 100px 40px 70px 40px; }

        body {
            {{-- Unescaped on purpose: {{ }} would turn the quotes into &#039;
                 and dompdf would silently fall back to Times. --}}
            font-family: {!! $devanagari ? "'notosansdevanagari', 'DejaVu Sans'" : "'DejaVu Sans'" !!}, sans-serif;
            font-size: 10px;
            color: #1c231f;
        }

        header {
            position: fixed;
            top: -80px; left: 0; right: 0;
            text-align: center;
            border-bottom: 1.5px solid #1c231f;
            padding-bottom: 6px;
        }
        footer {
            position: fixed;
            bottom: -50px; left: 0; right: 0;
            font-size: 8px;
            color: #5c6660;
            border-top: 0.5px solid #c9d1cc;
            padding-top: 4px;
        }
        .page-number:after { content: counter(page); }

        .shop-name { font-size: 15px; font-weight: bold; }
        .shop-meta { font-size: 9px; color: #5c6660; }
        .doc-title { font-size: 12px; font-weight: bold; margin-top: 4px; }
        .doc-period { font-size: 9px; color: #5c6660; }

        table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.data thead th {
            background: #efefef;
            border-bottom: 1px solid #9aa5a0;
            padding: 5px 6px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
        }
        table.data td { padding: 4px 6px; border-bottom: 0.5px solid #e3e8e5; }
        table.data tr.total td {
            font-weight: bold;
            border-top: 1px solid #1c231f;
            border-bottom: none;
        }
        .num { text-align: right; }

        .summary { width: 100%; margin-top: 14px; border-collapse: collapse; }
        .summary td { padding: 3px 6px; font-size: 10px; }
        .summary td.label { color: #5c6660; }
        .summary td.value { text-align: right; font-weight: bold; }

        .signatures { width: 100%; margin-top: 40px; }
        .signatures td { width: 33%; padding-top: 26px; font-size: 9px; text-align: center; }
        .sig-line { border-top: 0.5px solid #1c231f; padding-top: 3px; margin: 0 8px; }

        .section-title { font-size: 11px; font-weight: bold; margin-top: 18px; margin-bottom: 2px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <header>
        <div class="shop-name">{{ $business?->legal_name ?: $business?->name }}</div>
        <div class="shop-meta">
            {{ $branchLine }}@if ($sellerPan) · {{ __('reports.pan') }}: {{ $sellerPan }}@endif
        </div>
        <div class="doc-title">{{ $title }}</div>
        <div class="doc-period">
            {{ $period->label }} · {{ __('reports.range_bs') }} {{ $period->fromBs() }} – {{ $period->toBs() }}
            · {{ __('reports.range_ad') }} {{ $period->from->toDateString() }} – {{ $period->to->toDateString() }}
        </div>
    </header>

    <footer>
        <table style="width:100%; border:none;">
            <tr>
                <td style="text-align:left; border:none;">
                    {{ __('reports.generated_on') }} {{ \App\Support\Nepali\NepaliDate::formatLong(now()) }}
                    ({{ now()->format('Y-m-d H:i') }})
                </td>
                <td style="text-align:right; border:none;">
                    {{ __('reports.computer_generated') }} · {{ __('reports.page') }} <span class="page-number"></span>
                </td>
            </tr>
        </table>
    </footer>

    <main>
        @foreach ($sections as $index => $section)
            @if ($index > 0)
                <div class="page-break"></div>
            @endif

            @if (count($sections) > 1)
                <div class="section-title">{{ $section['title'] }}</div>
            @endif

            <table class="data">
                <thead>
                    <tr>
                        @foreach ($section['headers'] as $column => $heading)
                            <th @class(['num' => in_array($column, $section['numericColumns'] ?? [], true)])>{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($section['rows'] as $rowIndex => $row)
                        <tr @class(['total' => in_array($rowIndex, $section['totalRows'] ?? [], true)])>
                            @foreach ($row as $column => $value)
                                <td @class(['num' => in_array($column, $section['numericColumns'] ?? [], true)])>{{ $value }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($section['headers']) }}" style="text-align:center; color:#5c6660; padding:16px;">
                                {{ __('reports.no_activity') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if (! empty($section['summary']))
                <table class="summary">
                    @foreach ($section['summary'] as $label => $value)
                        <tr>
                            <td class="label">{{ $label }}</td>
                            <td class="value">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        @endforeach

        <table class="signatures">
            <tr>
                <td><div class="sig-line">{{ __('reports.prepared_by') }}</div></td>
                <td><div class="sig-line">{{ __('reports.approved_by') }}</div></td>
                <td><div class="sig-line">{{ __('nav.staff') }}</div></td>
            </tr>
        </table>
    </main>
</body>
</html>
