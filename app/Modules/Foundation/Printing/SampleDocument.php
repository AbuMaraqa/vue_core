<?php

namespace App\Modules\Foundation\Printing;

/**
 * A made-up document that shows the print settings at work: the letterhead,
 * the details, a table of lines, totals, the amount in words and signatures.
 */
final class SampleDocument extends PrintDocument
{
    public function title(): string
    {
        return __('Print preview');
    }

    public function number(): string
    {
        return 'PREVIEW-0001';
    }

    /**
     * @return array{label: string, tone: 'neutral'}
     */
    public function status(): array
    {
        return ['label' => __('Sample'), 'tone' => 'neutral'];
    }

    public function details(): array
    {
        return [
            __('Date') => now()->toDateString(),
            __('Reference') => 'REF-123',
        ];
    }

    public function signatures(): array
    {
        return [__('Prepared by'), __('Approved by'), __('Received by')];
    }

    protected function view(): string
    {
        return 'foundation::print.sample';
    }
}
