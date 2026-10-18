<x-print::parties :parties="[
    ['title' => __('Bill to'), 'name' => __('Sample customer'), 'lines' => ['+970 599 000 000', 'customer@example.com']],
]" />

<table class="grid" dir="{{ $direction }}">
    <thead>
        <tr>
            <th style="width: 5%">#</th>
            <th>{{ __('Description') }}</th>
            <th class="num">{{ __('Quantity') }}</th>
            <th class="num">{{ __('Price') }}</th>
            <th class="num">{{ __('Amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ([[__('Sample item'), 2, '450.00', '900.00'], [__('Sample service'), 1, '350.00', '350.00']] as [$description, $quantity, $price, $amount])
            <tr>
                <td class="muted">{{ $loop->iteration }}</td>
                <td>{{ $description }}</td>
                <td class="num">{{ $quantity }}</td>
                <td class="num">{{ $price }}</td>
                <td class="num">{{ $amount }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<x-print::totals :rows="[
    ['label' => __('Subtotal'), 'value' => '1,250.00'],
    ['label' => __('Total'), 'value' => '1,250.00', 'emphasis' => true],
]" />

<x-print::amount-in-words amount="1250" :currency="__('Dollar')" />

<x-print::notes :text="__('This is how notes entered on a document are printed.')" />
