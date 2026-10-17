@php($direction = app(\App\Modules\Core\Support\Localization\Locales::class)->direction())
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: sans-serif; font-size: 10pt; color: #1d2939; }
        h1 { font-size: 16pt; margin: 0 0 4pt; }
        .meta { color: #667085; font-size: 9pt; margin-bottom: 12pt; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f2f4f7; font-weight: bold; text-align: {{ $direction === 'rtl' ? 'right' : 'left' }}; }
        th, td { border: 0.5pt solid #d0d5dd; padding: 5pt 6pt; }
        tr:nth-child(even) td { background: #fcfcfd; }
        .notice { margin-top: 10pt; color: #b54708; font-size: 9pt; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        {{ __('Exported on :date', ['date' => now()->translatedFormat('j F Y, H:i')]) }}
        · {{ __(':count records', ['count' => $rows->count()]) }}
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $value)
                        <td>{{ is_bool($value) ? ($value ? __('Yes') : __('No')) : ($value ?? '—') }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headings) }}">{{ __('No records found.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if ($truncated)
        <p class="notice">{{ __('Only the first :limit records are included. Export to Excel for the full list.', ['limit' => $limit]) }}</p>
    @endif
</body>
</html>
