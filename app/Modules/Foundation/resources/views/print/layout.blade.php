{{--
    The page every printed document shares: a letterhead header and a footer
    repeated on each page, with the document content in between. mpdf supports
    a subset of CSS, so the layout is built with tables and no flexbox.
--}}
@php
    $brand = $letterhead->brandColor;
    $tint = function (string $hex, float $whiteRatio): string {
        $channels = array_map('hexdec', str_split(ltrim($hex, '#'), 2));

        return '#'.implode('', array_map(
            fn (int $channel): string => str_pad(dechex((int) round($channel + (255 - $channel) * $whiteRatio)), 2, '0', STR_PAD_LEFT),
            $channels,
        ));
    };
    $brandSoft = $tint($brand, 0.92);
    $tones = [
        'neutral' => ['#475467', '#f2f4f7'],
        'success' => ['#067647', '#ecfdf3'],
        'warning' => ['#b54708', '#fffaeb'],
        'danger' => ['#b42318', '#fef3f2'],
    ];
    $status = $document->status();
    $number = $document->number();
    $details = array_filter($document->details(), fn ($value): bool => filled($value));
    $signatures = $document->signatures();
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <title>{{ trim($document->title().' '.$number) }}</title>
    <style>
        body { color: #1d2939; line-height: 1.5; }
        table { border-collapse: collapse; }
        .muted { color: #667085; }
        .text-start { text-align: {{ $start }}; }
        .text-end { text-align: {{ $end }}; }
        .text-center { text-align: center; }
        .num { text-align: {{ $end }}; direction: ltr; white-space: nowrap; }
        .strong { font-weight: bold; }

        /* Header */
        .ph { width: 100%; }
        .ph td { vertical-align: middle; padding: 0; }
        .ph-logo { padding-{{ $end }}: 3mm; }
        .ph-name { font-size: 15pt; font-weight: bold; color: #101828; }
        .ph-tagline { font-size: 8.5pt; color: #667085; }
        .ph-title { font-size: 16pt; font-weight: bold; color: {{ $brand }}; }
        .ph-number { font-size: 10pt; color: #344054; direction: ltr; }
        .badge { font-size: 8pt; font-weight: bold; padding: 1mm 2.5mm; border-radius: 2mm; }
        .ph-rule { height: 0.8mm; background-color: {{ $brand }}; margin-top: 3mm; }

        /* Footer */
        .pf-rule { border-top: 0.3mm solid #d0d5dd; margin-bottom: 1.5mm; }
        .pf { width: 100%; font-size: 7.5pt; color: #667085; }
        .pf td { padding: 0.4mm 0; vertical-align: top; }
        .pf-page { white-space: nowrap; color: #344054; }
        .pf-note { color: #344054; }

        /* Details of the document */
        .details { width: 100%; margin-bottom: 5mm; background-color: {{ $brandSoft }}; }
        .details td { padding: 1.6mm 3mm; vertical-align: top; }
        .details .label { color: #667085; width: 18%; white-space: nowrap; }
        .details .value { font-weight: bold; width: 32%; }

        /* Content building blocks */
        h2 { font-size: 11pt; margin: 5mm 0 2mm; color: #101828; }
        .grid { width: 100%; }
        .grid th { background-color: {{ $brandSoft }}; color: #101828; font-weight: bold; text-align: {{ $start }}; border-bottom: 0.4mm solid {{ $brand }}; }
        .grid th, .grid td { padding: 1.8mm 2.2mm; }
        .grid td { border-bottom: 0.2mm solid #eaecf0; vertical-align: top; }
        .grid th.num, .grid td.num { text-align: {{ $end }}; }
        .grid tfoot td { font-weight: bold; border-top: 0.4mm solid #98a2b3; border-bottom: none; }
        .box { border: 0.3mm solid #d0d5dd; padding: 3mm; vertical-align: top; }
        .box-title { font-size: 8pt; color: #667085; margin-bottom: 1mm; }
        .box-name { font-weight: bold; font-size: 11pt; }
        .totals { width: 100%; }
        .totals td { padding: 1.4mm 2.2mm; border: none; }
        .totals .emphasis td { font-weight: bold; font-size: 11.5pt; color: #ffffff; background-color: {{ $brand }}; }
        .words { margin-top: 4mm; padding: 2.5mm 3mm; background-color: #f9fafb; border-{{ $start }}: 1mm solid {{ $brand }}; }
        .notice { margin-top: 4mm; color: #b54708; font-size: 8.5pt; }

        /* Signatures */
        .signatures { width: 100%; margin-top: 16mm; page-break-inside: avoid; }
        .signatures td { text-align: center; vertical-align: top; padding: 0; }
        .signatures .signature { border-top: 0.3mm solid #667085; padding-top: 1.5mm; color: #344054; font-size: 9pt; }
    </style>
</head>
<body dir="{{ $direction }}">
    <htmlpageheader name="page-header">
        <table class="ph" dir="{{ $direction }}">
            <tr>
                <td class="text-start" style="width: 55%">
                    <table dir="{{ $direction }}">
                        <tr>
                            @if ($letterhead->logoPath)
                                <td class="ph-logo"><img src="{{ $letterhead->logoPath }}" style="height: 15mm" alt=""></td>
                            @endif
                            <td>
                                <div class="ph-name">{{ $letterhead->name }}</div>
                                @if ($letterhead->tagline)
                                    <div class="ph-tagline">{{ $letterhead->tagline }}</div>
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="text-end">
                    <div class="ph-title">{{ $document->title() }}</div>
                    @if ($number)
                        <div class="ph-number">{{ $number }}</div>
                    @endif
                    @if ($status)
                        <span class="badge" style="color: {{ $tones[$status['tone']][0] }}; background-color: {{ $tones[$status['tone']][1] }}">{{ $status['label'] }}</span>
                    @endif
                </td>
            </tr>
        </table>
        <div class="ph-rule"></div>
    </htmlpageheader>

    <htmlpagefooter name="page-footer">
        <div class="pf-rule"></div>
        <table class="pf" dir="{{ $direction }}">
            <tr>
                <td class="text-start">
                    @foreach ($letterhead->details as $detail)
                        {{ $detail['label'] }}: {{ \App\Modules\Foundation\Printing\Bidi::isolate($detail['value']) }}@if (! $loop->last) &nbsp;·&nbsp; @endif
                    @endforeach
                </td>
                <td class="text-end pf-page" style="width: 22%">{{ __('Page :page of :pages', ['page' => '{PAGENO}', 'pages' => '{nbpg}']) }}</td>
            </tr>
            @if ($letterhead->footerNote)
                <tr>
                    <td colspan="2" class="text-start pf-note">{!! nl2br(e($letterhead->footerNote)) !!}</td>
                </tr>
            @endif
            <tr>
                <td colspan="2" class="text-start">
                    {{ $printedBy ? __('Printed on :date by :name', ['date' => $printedAt, 'name' => $printedBy]) : __('Printed on :date', ['date' => $printedAt]) }}
                </td>
            </tr>
        </table>
    </htmlpagefooter>

    <sethtmlpageheader name="page-header" value="on" show-this-page="1" />
    <sethtmlpagefooter name="page-footer" value="on" />

    @if ($details !== [])
        <table class="details" dir="{{ $direction }}">
            @foreach (array_chunk($details, 2, true) as $pair)
                <tr>
                    @foreach ($pair as $label => $value)
                        <td class="label text-start">{{ $label }}</td>
                        <td class="value text-start">{{ \App\Modules\Foundation\Printing\Bidi::isolate($value) }}</td>
                    @endforeach
                    @if (count($pair) === 1)
                        <td class="label"></td>
                        <td class="value"></td>
                    @endif
                </tr>
            @endforeach
        </table>
    @endif

    {!! $content !!}

    @if ($signatures !== [])
        <table class="signatures" dir="{{ $direction }}">
            <tr>
                {{-- The line is the border of the cell itself; empty cells keep the lines apart. --}}
                @foreach ($signatures as $caption)
                    @if (! $loop->first)
                        <td style="width: 8%"></td>
                    @endif
                    <td class="signature" style="width: {{ round((100 - 8 * (count($signatures) - 1)) / count($signatures), 2) }}%">{{ $caption }}</td>
                @endforeach
            </tr>
        </table>
    @endif
</body>
</html>
