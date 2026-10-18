@if ($summary !== [])
    <p class="muted text-start">{{ implode(' · ', $summary) }}</p>
@endif

<table class="grid" dir="{{ $direction }}">
    <thead>
        <tr>
            <th>#</th>
            @foreach ($headings as $heading)
                <th>{{ $heading }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td class="muted">{{ $loop->iteration }}</td>
                @foreach ($row as $value)
                    {{-- Amounts arrive formatted, e.g. "1,250.00", so numbers are recognised by their shape. --}}
                    <td @class(['num' => is_int($value) || is_float($value) || (is_string($value) && preg_match('/^[-(]?[\d,]+(\.\d+)?\)?%?$/', $value))])>{{ is_bool($value) ? ($value ? __('Yes') : __('No')) : ($value ?? '—') }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($headings) + 1 }}" class="text-center muted">{{ __('No records found.') }}</td>
            </tr>
        @endforelse
    </tbody>
</table>

@if ($notice)
    <p class="notice">{{ $notice }}</p>
@endif
