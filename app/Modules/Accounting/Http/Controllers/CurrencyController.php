<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\ExchangeRate;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\SafeDelete;
use App\Modules\Foundation\Http\Requests\Concerns\ValidatesTranslations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Currencies and their exchange rates against the base currency.
 */
class CurrencyController extends Controller
{
    use ValidatesTranslations;

    public function index(Currencies $currencies): Response
    {
        Gate::authorize('viewAny', Currency::class);

        $baseId = $currencies->base()->id;

        return Inertia::render('admin/accounting/currencies/Index', [
            'currencies' => Currency::query()
                ->with(['exchangeRates' => fn ($query) => $query->latest('effective_date')->limit(1)])
                ->orderByRaw('id = ? desc', [$baseId])
                ->orderBy('code')
                ->get()
                ->map(fn (Currency $currency): array => [
                    'id' => $currency->id,
                    'code' => $currency->code,
                    'name' => $currency->translate('name'),
                    'name_translations' => $currency->name,
                    'symbol' => $currency->symbol,
                    'decimal_places' => $currency->decimal_places,
                    'is_active' => $currency->is_active,
                    'is_base' => $currency->id === $baseId,
                    'latest_rate' => $currency->exchangeRates->first() ? [
                        'rate' => (string) $currency->exchangeRates->first()->rate,
                        'effective_date' => $currency->exchangeRates->first()->effective_date->toDateString(),
                    ] : null,
                ]),
            'baseCode' => $currencies->base()->code,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Currency::class);

        Currency::query()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Currency created.')]);

        return to_route('admin.accounting.currencies.index');
    }

    public function update(Request $request, Currency $currency, Currencies $currencies): RedirectResponse
    {
        Gate::authorize('update', $currency);

        $attributes = $this->validated($request, $currency);

        if ($currencies->isBase($currency)) {
            $attributes['is_active'] = true;
        }

        $currency->update($attributes);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Currency updated.')]);

        return to_route('admin.accounting.currencies.index');
    }

    public function destroy(Currency $currency, Currencies $currencies): RedirectResponse
    {
        Gate::authorize('delete', $currency);

        abort_if($currencies->isBase($currency), 403);

        SafeDelete::delete($currency);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Currency deleted.')]);

        return to_route('admin.accounting.currencies.index');
    }

    /**
     * The rate history of a currency, for its rates dialog.
     */
    public function rates(Currency $currency): JsonResponse
    {
        Gate::authorize('viewAny', Currency::class);

        return response()->json($currency->exchangeRates()->latest('effective_date')->limit(60)->get()->map(fn (ExchangeRate $rate): array => [
            'id' => $rate->id,
            'effective_date' => $rate->effective_date->toDateString(),
            'rate' => (string) $rate->rate,
        ]));
    }

    /**
     * Add a rate, or replace the rate of the same day.
     */
    public function storeRate(Request $request, Currency $currency, Currencies $currencies): RedirectResponse
    {
        Gate::authorize('update', $currency);

        abort_if($currencies->isBase($currency), 403);

        $validated = $request->validate([
            'effective_date' => ['required', 'date'],
            'rate' => ['required', 'numeric', 'gt:0', 'decimal:0,8'],
        ]);

        $currency->exchangeRates()->updateOrCreate(
            ['effective_date' => $request->date('effective_date')?->toDateString()],
            ['rate' => $validated['rate']],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Exchange rate saved.')]);

        return back();
    }

    public function destroyRate(Currency $currency, ExchangeRate $rate): RedirectResponse
    {
        Gate::authorize('update', $currency);

        abort_unless($rate->currency_id === $currency->id, 404);

        $rate->delete();

        return back();
    }

    /**
     * The rate of a currency on a date, used by forms when the currency or the
     * date of a document changes.
     */
    public function lookup(Request $request, Currencies $currencies): JsonResponse
    {
        $request->validate([
            'currency_id' => ['required', 'integer', Rule::exists(Currency::class, 'id')],
            'date' => ['required', 'date'],
        ]);

        $currency = Currency::query()->findOrFail($request->integer('currency_id'));

        return response()->json([
            'rate' => $currencies->rate($currency, $request->string('date')->value()),
            'is_base' => $currencies->isBase($currency),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Currency $currency = null): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:3', 'alpha', Rule::unique(Currency::class, 'code')->ignore($currency)],
            ...$this->translationRules('name', 100),
            'symbol' => ['nullable', 'string', 'max:10'],
            'decimal_places' => ['required', 'integer', 'between:0,4'],
            'is_active' => ['boolean'],
        ]);

        return [
            ...$validated,
            'code' => strtoupper($validated['code']),
            'name' => array_filter($validated['name']),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
