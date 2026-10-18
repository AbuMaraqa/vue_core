{{--
    Free text entered by the user, such as the notes of an invoice. Nothing is
    printed when the text is empty.

    <x-print::notes :text="$invoice->notes" />
--}}
@props(['text', 'title' => null])

@if (filled($text))
    <div class="box text-start" style="margin-top: 5mm">
        <div class="box-title">{{ $title ?? __('Notes') }}</div>
        {!! nl2br(e($text)) !!}
    </div>
@endif
