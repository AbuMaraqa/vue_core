<?php

use App\Modules\Accounting\Printing\FinancialDocument;
use App\Modules\Accounting\Settings\AccountingSettings;
use App\Modules\Foundation\Printing\AmountInWords;
use App\Modules\Foundation\Printing\LetterheadFactory;
use App\Modules\Foundation\Printing\PaperSize;
use App\Modules\Foundation\Printing\PrintSettings;
use Inertia\Testing\AssertableInertia as Assert;

describe('print settings', function () {
    test('staff who may view settings see the print settings', function () {
        $this->actingAs(staffMember('Setting View'))
            ->get(route('admin.print-settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/print-settings/Edit')
                ->where('settings.paper_size', 'A4')
                ->where('canUpdate', false));
    });

    test('the print settings are saved', function () {
        $this->actingAs(staffMember('Setting View', 'Setting Update'))
            ->put(route('admin.print-settings.update'), [
                'paper_size' => 'A5',
                'show_logo' => false,
                'show_printed_by' => false,
                'footer_note' => 'Bank: 123-456',
            ])
            ->assertRedirect(route('admin.print-settings.edit'))
            ->assertSessionHasNoErrors();

        $settings = app(PrintSettings::class)->refresh();

        expect($settings->paper_size)->toBe(PaperSize::A5)
            ->and($settings->show_logo)->toBeFalse()
            ->and($settings->show_printed_by)->toBeFalse()
            ->and($settings->footer_note)->toBe('Bank: 123-456');
    });

    test('an unknown paper size is rejected', function () {
        $this->actingAs(staffMember('Setting View', 'Setting Update'))
            ->put(route('admin.print-settings.update'), [
                'paper_size' => 'A3',
                'show_logo' => true,
                'show_printed_by' => true,
            ])
            ->assertSessionHasErrors('paper_size');
    });

    test('viewing the settings does not allow changing them', function () {
        $this->actingAs(staffMember('Setting View'))
            ->put(route('admin.print-settings.update'), ['paper_size' => 'A5', 'show_logo' => true, 'show_printed_by' => true])
            ->assertForbidden();
    });
});

describe('printed documents', function () {
    test('the preview opens in the browser, in arabic', function () {
        $response = $this->actingAs(staffMember('Setting View'))
            ->get('/ar/admin/print-settings/preview')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        expect($response->headers->get('Content-Disposition'))->toStartWith('inline')
            ->and(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
    });

    test('a document is saved instead when asked to download it', function () {
        $response = $this->actingAs(staffMember('Setting View'))
            ->get(route('admin.print-settings.preview', ['download' => 1]))
            ->assertOk();

        expect($response->headers->get('Content-Disposition'))->toStartWith('attachment');
    });

    test('the letterhead carries the contacts and the details modules add', function () {
        $accounting = app(AccountingSettings::class);
        $accounting->company_tax_number = '562-114-998';
        $accounting->company_address = 'Ramallah, Main Street';
        $accounting->save();

        $letterhead = app(LetterheadFactory::class)->make();

        expect(array_column($letterhead->details, 'value', 'label'))
            ->toMatchArray(['Tax number' => '562-114-998', 'Address' => 'Ramallah, Main Street']);
    });
});

describe('formatting', function () {
    test('an amount is written in arabic words with its fraction in digits', function () {
        expect(AmountInWords::spell('1250.5', 'دينار', 3, 'ar'))
            ->toBe('فقط ألف ومئتان وخمسون دينار و500/1000 لا غير')
            ->and(AmountInWords::spell(2000, 'شيكل', 2, 'ar'))
            ->toBe('فقط ألفان شيكل لا غير');
    });

    test('an amount is written in english words', function () {
        expect(AmountInWords::spell('1250.75', 'dollars', 2, 'en'))
            ->toBe('Only one thousand two hundred fifty dollars and 75/100');
    });

    test('money keeps every digit of large amounts', function () {
        expect(FinancialDocument::money('12345678901234.5678', 3))->toBe('12,345,678,901,234.568')
            ->and(FinancialDocument::money('-1500', 2))->toBe('-1,500.00')
            ->and(FinancialDocument::quantity('2.5000'))->toBe('2.5')
            ->and(FinancialDocument::percent('0'))->toBe('');
    });
});
