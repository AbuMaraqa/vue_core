{{--
    The summary under a table of lines, aligned to the end of the page. The row
    marked `emphasis` is highlighted with the brand color.

    <x-print::totals :rows="[
        ['label' => __('Subtotal'), 'value' => '1,000.00'],
        ['label' => __('Total'), 'value' => '1,160.00', 'emphasis' => true],
    ]" />
--}}
@props(['rows'])

{{-- mpdf ignores margins on tables, so an empty first cell pushes the totals to the end. --}}
<table style="width: 100%; margin-top: 4mm">
    <tr>
        <td style="width: 52%"></td>
        <td style="padding: 0">
            <table class="totals">
                @foreach ($rows as $row)
                    <tr @class(['emphasis' => $row['emphasis'] ?? false])>
                        <td class="text-start" style="white-space: nowrap">{{ $row['label'] }}</td>
                        <td class="num">{{ $row['value'] }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>
