{{--
    Side-by-side boxes for the people a document concerns, e.g. the customer
    and the delivery address, or the payer and the payee.

    <x-print::parties :parties="[
        ['title' => __('Bill to'), 'name' => $contact->name, 'lines' => [$contact->address, $contact->phone]],
    ]" />
--}}
@props(['parties'])

<table style="width: 100%; margin-bottom: 5mm">
    <tr>
        @foreach ($parties as $party)
            @if (! $loop->first)
                <td style="width: 3%"></td>
            @endif
            <td class="box text-start" style="width: {{ round(97 / count($parties), 2) }}%">
                <div class="box-title">{{ $party['title'] }}</div>
                <div class="box-name">{{ $party['name'] }}</div>
                @foreach (array_filter($party['lines'] ?? [], fn ($line): bool => filled($line)) as $line)
                    <div class="muted">{{ \App\Modules\Foundation\Printing\Bidi::isolate($line) }}</div>
                @endforeach
            </td>
        @endforeach
    </tr>
</table>
