<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Actions\Currencies\ChangeBaseCurrency;
use App\Modules\Accounting\Enums\AccountRole;
use App\Modules\Accounting\Enums\DocumentType;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Settings\AccountingSettings;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\Decimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Base currency, lock date, the default account of each automatic posting,
 * document number prefixes and company details for documents.
 */
class AccountingSettingsController extends Controller
{
    public function edit(AccountingSettings $settings, AccountingOptions $options): Response
    {
        Gate::authorize('Accounting Setting View');

        return Inertia::render('admin/accounting/settings/Edit', [
            'settings' => [
                'base_currency' => $settings->base_currency,
                'lock_date' => $settings->lock_date,
                'default_accounts' => (object) $settings->default_accounts,
                'number_prefixes' => (object) $settings->number_prefixes,
                'company_tax_number' => $settings->company_tax_number,
                'company_address' => $settings->company_address,
                'prices_include_tax' => $settings->prices_include_tax,
                'allow_negative_stock' => $settings->allow_negative_stock,
            ],
            'roles' => array_map(fn (AccountRole $role): array => ['value' => $role->value, 'label' => $role->label()], AccountRole::cases()),
            'documentTypes' => array_map(fn (DocumentType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'default_prefix' => $type->defaultPrefix(),
            ], DocumentType::cases()),
            'accounts' => $options->accounts(),
            'currencies' => Currency::query()
                ->with(['exchangeRates' => fn ($query) => $query->latest('effective_date')->limit(1)])
                ->where('is_active', true)
                ->where('code', '!=', $settings->base_currency)
                ->orderBy('code')
                ->get()
                ->map(fn (Currency $currency): array => [
                    'value' => $currency->id,
                    'label' => "{$currency->code} — {$currency->translate('name')}",
                    'code' => $currency->code,
                    'suggested_rate' => $currency->exchangeRates->first()
                        ? Decimal::divide(1, (string) $currency->exchangeRates->first()->rate, 8)
                        : null,
                ]),
            'hasEntries' => JournalEntry::query()->inBooks()->exists(),
            'canUpdate' => Gate::allows('Accounting Setting Update'),
        ]);
    }

    public function update(Request $request, AccountingSettings $settings): RedirectResponse
    {
        Gate::authorize('Accounting Setting Update');

        $validated = $request->validate([
            'lock_date' => ['nullable', 'date'],
            'default_accounts' => ['array'],
            'default_accounts.*' => ['nullable', 'integer', Rule::exists(Account::class, 'id')->where('is_group', false)],
            'number_prefixes' => ['array'],
            'number_prefixes.*' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'company_tax_number' => ['nullable', 'string', 'max:50'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'prices_include_tax' => ['boolean'],
            'allow_negative_stock' => ['boolean'],
        ]);

        $roles = array_column(AccountRole::cases(), 'value');
        $types = array_column(DocumentType::cases(), 'value');

        $settings->lock_date = $request->date('lock_date')?->toDateString();
        $settings->default_accounts = array_map('intval', array_filter(array_intersect_key($validated['default_accounts'] ?? [], array_flip($roles))));
        $settings->number_prefixes = array_map('strtoupper', array_filter(array_intersect_key($validated['number_prefixes'] ?? [], array_flip($types))));
        $settings->company_tax_number = $validated['company_tax_number'] ?? null;
        $settings->company_address = $validated['company_address'] ?? null;
        $settings->prices_include_tax = $request->boolean('prices_include_tax');
        $settings->allow_negative_stock = $request->boolean('allow_negative_stock');
        $settings->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return to_route('admin.accounting.settings.edit');
    }

    /**
     * Move the books to another base currency, converting every recorded
     * amount at the given rate (one unit of the current base currency in the
     * new one).
     */
    public function changeBaseCurrency(Request $request, AccountingSettings $settings, ChangeBaseCurrency $change): RedirectResponse
    {
        Gate::authorize('Accounting Setting Update');

        $validated = $request->validate([
            'currency_id' => ['required', 'integer', Rule::exists(Currency::class, 'id')->where('is_active', true)->whereNot('code', $settings->base_currency)],
            'rate' => ['required', 'numeric', 'gt:0', 'decimal:0,8'],
        ], attributes: [
            'currency_id' => __('New base currency'),
            'rate' => __('Exchange rate'),
        ]);

        $conversion = $change->handle(Currency::query()->findOrFail($validated['currency_id']), (string) $validated['rate']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The base currency is now :code.', ['code' => $conversion->to->code])]);

        return to_route('admin.accounting.settings.edit');
    }
}
