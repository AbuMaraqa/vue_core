<?php

namespace App\Modules\Foundation\Printing;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A document printed as a PDF: an invoice, a voucher, a payslip, a report...
 *
 * Every document shares one layout: the letterhead and the document title
 * in the header of each page, the company contacts, the footer note and the
 * page numbers in the footer. A subclass only describes its content:
 *
 * ```php
 * class ReceiptDocument extends PrintDocument
 * {
 *     public function __construct(protected Payment $payment) {}
 *
 *     public function title(): string { return __('Receipt'); }
 *     public function number(): ?string { return $this->payment->number; }
 *     public function details(): array { return [__('Date') => $this->payment->date->toDateString()]; }
 *     public function signatures(): array { return [__('Received by'), __('Accountant')]; }
 *
 *     protected function view(): string { return 'invoicing::print.receipt'; }
 *     protected function data(): array { return ['payment' => $this->payment]; }
 * }
 *
 * // controller
 * Gate::authorize('view', $payment);
 *
 * return new ReceiptDocument($payment);
 * ```
 *
 * Returned from a controller, the PDF opens in the browser, ready to print;
 * `?download=1` saves it instead. The content view receives the data plus
 * `$start` and `$end` (`left`/`right` for the current direction), and can use
 * the `<x-print::...>` components and the classes of the layout stylesheet.
 */
abstract class PrintDocument implements Responsable
{
    /**
     * The translated title shown in the header, e.g. "Sales invoice".
     */
    abstract public function title(): string;

    /**
     * The Blade view that renders the content between the header and the footer.
     *
     * @return view-string
     */
    abstract protected function view(): string;

    /**
     * @return array<string, mixed>
     */
    protected function data(): array
    {
        return [];
    }

    /**
     * The reference shown under the title, e.g. the invoice number.
     */
    public function number(): ?string
    {
        return null;
    }

    /**
     * A badge next to the title, used for documents that are not final, such as drafts or cancelled ones.
     *
     * @return array{label: string, tone: 'neutral'|'success'|'warning'|'danger'}|null
     */
    public function status(): ?array
    {
        return null;
    }

    /**
     * The facts printed at the top of the first page, translated labels mapped to values.
     * Empty values are left out.
     *
     * @return array<string, string|null>
     */
    public function details(): array
    {
        return [];
    }

    /**
     * The translated captions of the signature boxes printed after the content.
     *
     * @return list<string>
     */
    public function signatures(): array
    {
        return [];
    }

    public function orientation(): Orientation
    {
        return Orientation::Portrait;
    }

    /**
     * The paper of this document, or null to use the one chosen in the print settings.
     */
    public function paperSize(): ?PaperSize
    {
        return null;
    }

    /**
     * Whether the file is saved rather than opened in the browser when `?download` is not given.
     */
    public function downloadsByDefault(): bool
    {
        return false;
    }

    public function filename(): string
    {
        $name = Str::slug(trim($this->title().' '.$this->number()), language: null);

        return ($name !== '' ? $name : 'document').'.pdf';
    }

    /**
     * Render the content of the document, without the header and the footer.
     *
     * @param  array<string, mixed>  $shared  Variables of the layout every content view receives.
     */
    public function renderContent(array $shared): string
    {
        return view($this->view(), [...$shared, ...$this->data()])->render();
    }

    /**
     * Render the document as PDF bytes.
     */
    public function render(): string
    {
        return app(PdfRenderer::class)->render($this);
    }

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        return app(PdfRenderer::class)->response($this, $request->boolean('download', $this->downloadsByDefault()));
    }
}
