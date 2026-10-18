<?php

namespace App\Modules\Foundation\Printing;

use App\Modules\Core\Settings\AppearanceSettings;
use App\Modules\Core\Support\Localization\Locales;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders a PrintDocument inside the shared layout, with the interface font,
 * the brand color and the reading direction of the current language.
 */
class PdfRenderer
{
    public function __construct(
        protected LetterheadFactory $letterheads,
        protected Locales $locales,
    ) {}

    public function render(PrintDocument $document): string
    {
        $direction = $this->locales->direction();
        $letterhead = $this->letterheads->make();

        $shared = [
            'direction' => $direction,
            'start' => $direction === 'rtl' ? 'right' : 'left',
            'end' => $direction === 'rtl' ? 'left' : 'right',
        ];

        $html = view('foundation::print.layout', [
            ...$shared,
            'document' => $document,
            'letterhead' => $letterhead,
            'content' => $document->renderContent($shared),
            'printedBy' => app(PrintSettings::class)->show_printed_by ? auth()->user()?->name : null,
            'printedAt' => now()->translatedFormat('j F Y, H:i'),
        ])->render();

        $mpdf = $this->mpdf($document, $direction);
        $mpdf->SetTitle(trim($document->title().' '.$document->number()));
        $mpdf->SetAuthor($letterhead->name);
        $mpdf->SetCreator($letterhead->name);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * The PDF opened in the browser, or saved when `$download` is true.
     */
    public function response(PrintDocument $document, bool $download = false): Response
    {
        $filename = $document->filename();

        return response($this->render($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $download ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE,
                $filename,
                Str::ascii($filename) !== '' ? Str::ascii($filename) : 'document.pdf',
            ),
        ]);
    }

    protected function mpdf(PrintDocument $document, string $direction): Mpdf
    {
        $paper = $document->paperSize() ?? app(PrintSettings::class)->paper_size;
        $orientation = $document->orientation();
        $font = PrintFonts::name(app(AppearanceSettings::class)->font_family);
        $compact = $paper === PaperSize::A5;

        $tempDir = storage_path('framework/cache/mpdf');
        File::ensureDirectoryExists($tempDir);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $paper->format($orientation),
            'orientation' => $orientation->value,
            'fontDir' => [...(new ConfigVariables)->getDefaults()['fontDir'], PrintFonts::directory()],
            'fontdata' => [...(new FontVariables)->getDefaults()['fontdata'], ...PrintFonts::fontdata()],
            'default_font' => $font,
            'default_font_size' => $compact ? 9 : 10,
            'margin_left' => $compact ? 9 : 12,
            'margin_right' => $compact ? 9 : 12,
            'margin_top' => $compact ? 28 : 34,
            'margin_bottom' => $compact ? 20 : 24,
            'margin_header' => $compact ? 6 : 8,
            'margin_footer' => $compact ? 6 : 8,
            // The interface font covers Arabic and Latin, so mpdf must not swap fonts by script.
            'autoScriptToLang' => false,
            'autoLangToFont' => false,
            'tempDir' => $tempDir,
        ]);

        $mpdf->SetDirectionality($direction);

        return $mpdf;
    }
}
