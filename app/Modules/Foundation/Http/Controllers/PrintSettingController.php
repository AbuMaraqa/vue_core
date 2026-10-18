<?php

namespace App\Modules\Foundation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Foundation\Http\Requests\UpdatePrintSettingsRequest;
use App\Modules\Foundation\Printing\PaperSize;
use App\Modules\Foundation\Printing\PrintSettings;
use App\Modules\Foundation\Printing\SampleDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * How every printed document looks. The site name, logo, contacts and font
 * come from the general settings; this page holds what is specific to paper.
 */
class PrintSettingController extends Controller
{
    public function edit(PrintSettings $settings): Response
    {
        return Inertia::render('admin/print-settings/Edit', [
            'settings' => [
                'paper_size' => $settings->paper_size->value,
                'show_logo' => $settings->show_logo,
                'show_printed_by' => $settings->show_printed_by,
                'footer_note' => $settings->footer_note,
            ],
            'paperSizes' => PaperSize::options(),
            'canUpdate' => Gate::allows('Setting Update'),
        ]);
    }

    public function update(UpdatePrintSettingsRequest $request, PrintSettings $settings): RedirectResponse
    {
        $settings->fill([
            'paper_size' => $request->enum('paper_size', PaperSize::class),
            'show_logo' => $request->boolean('show_logo'),
            'show_printed_by' => $request->boolean('show_printed_by'),
            'footer_note' => $request->validated('footer_note'),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Print settings saved.')]);

        return to_route('admin.print-settings.edit');
    }

    /**
     * A sample document printed with the saved settings.
     */
    public function preview(): SampleDocument
    {
        return new SampleDocument;
    }
}
