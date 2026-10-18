<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\SafeDelete;
use App\Modules\Foundation\Http\Requests\Concerns\ValidatesTranslations;
use App\Modules\Payroll\Enums\PayCalculation;
use App\Modules\Payroll\Enums\PayComponentType;
use App\Modules\Payroll\Models\PayComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Allowances, deductions and employer contributions, with their accounts.
 */
class PayComponentController extends Controller
{
    use ValidatesTranslations;

    public function index(AccountingOptions $options): Response
    {
        Gate::authorize('viewAny', PayComponent::class);

        return Inertia::render('admin/payroll/components/Index', [
            'components' => PayComponent::query()->with(['account', 'liabilityAccount'])->get()
                ->map(fn (PayComponent $component): array => [
                    'id' => $component->id,
                    'name' => $component->translate('name'),
                    'name_translations' => $component->name,
                    'type' => $component->type->value,
                    'type_label' => $component->type->label(),
                    'calculation' => $component->calculation->value,
                    'calculation_label' => $component->calculation->label(),
                    'default_value' => (string) $component->default_value,
                    'account_id' => $component->account_id,
                    'account' => $component->account->label(),
                    'liability_account_id' => $component->liability_account_id,
                    'liability_account' => $component->liabilityAccount?->label(),
                    'is_active' => $component->is_active,
                ])
                ->sortBy('type')
                ->values(),
            'types' => PayComponentType::options(),
            'calculations' => PayCalculation::options(),
            'accounts' => $options->accounts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', PayComponent::class);

        PayComponent::query()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pay component created.')]);

        return to_route('admin.payroll.components.index');
    }

    public function update(Request $request, PayComponent $component): RedirectResponse
    {
        Gate::authorize('update', $component);

        $component->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pay component updated.')]);

        return to_route('admin.payroll.components.index');
    }

    public function destroy(PayComponent $component): RedirectResponse
    {
        Gate::authorize('delete', $component);

        SafeDelete::delete($component);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pay component deleted.')]);

        return to_route('admin.payroll.components.index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $postable = fn () => Rule::exists(Account::class, 'id')->where('is_group', false);
        $contribution = $request->input('type') === PayComponentType::EmployerContribution->value;

        $validated = $request->validate([
            ...$this->translationRules('name', 100),
            'type' => ['required', Rule::enum(PayComponentType::class)],
            'calculation' => ['required', Rule::enum(PayCalculation::class)],
            'default_value' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'account_id' => ['required', 'integer', $postable()],
            'liability_account_id' => [Rule::requiredIf($contribution), Rule::prohibitedIf(! $contribution), 'nullable', 'integer', 'different:account_id', $postable()],
            'is_active' => ['boolean'],
        ]);

        return [...$validated, 'name' => array_filter($validated['name']), 'is_active' => $request->boolean('is_active', true)];
    }
}
