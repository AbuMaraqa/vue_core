<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Support\AccountingOptions;
use App\Modules\Accounting\Support\Currencies;
use App\Modules\Accounting\Support\SafeDelete;
use App\Modules\Foundation\Support\Filters\SearchFilter;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\EmployeePayComponent;
use App\Modules\Payroll\Models\PayComponent;
use App\Modules\Payroll\Models\Payslip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Employees, their salary and the components they receive or have
 * deducted.
 */
class EmployeeController extends Controller
{
    public function index(Request $request, AccountingOptions $options, Currencies $currencies): Response
    {
        Gate::authorize('viewAny', Employee::class);

        $employees = QueryBuilder::for(Employee::query()->with('costCenter')->withExists('payslips'))
            ->allowedFilters(
                SearchFilter::on('code', 'name', 'national_id', 'position'),
                AllowedFilter::exact('cost_center', 'cost_center_id'),
                AllowedFilter::callback('status', fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))->delimiter(''),
            )
            ->defaultSort('code')
            ->allowedSorts('code', 'name', 'hire_date', 'basic_salary')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Employee $employee): array => $this->row($request, $employee));

        return Inertia::render('admin/payroll/employees/Index', [
            'employees' => $employees,
            'costCenters' => $options->costCenters(),
            'filters' => [
                'search' => $request->input('filter.search'),
                'cost_center' => $request->input('filter.cost_center'),
                'status' => $request->input('filter.status'),
            ],
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    public function create(AccountingOptions $options): Response
    {
        Gate::authorize('create', Employee::class);

        return Inertia::render('admin/payroll/employees/Create', [
            ...$this->formOptions($options),
            'defaults' => ['hire_date' => now()->toDateString()],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Employee::class);

        $employee = $this->save(new Employee, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee created.')]);

        return to_route('admin.payroll.employees.show', $employee);
    }

    public function show(Request $request, Employee $employee, Currencies $currencies): Response
    {
        Gate::authorize('view', $employee);

        $employee->load(['costCenter', 'components.component']);

        return Inertia::render('admin/payroll/employees/Show', [
            'employee' => [
                ...$this->row($request, $employee),
                'components' => $employee->components->map(fn (EmployeePayComponent $assignment): array => [
                    'name' => $assignment->component->translate('name'),
                    'type' => $assignment->component->type->value,
                    'type_label' => $assignment->component->type->label(),
                    'calculation' => $assignment->component->calculation->value,
                    'value' => (string) $assignment->value,
                ])->values(),
            ],
            'payslips' => $employee->payslips()->with('run')->latest('id')->limit(24)->get()->map(fn (Payslip $payslip): array => [
                'id' => $payslip->id,
                'run_id' => $payslip->payroll_run_id,
                'period' => $payslip->run->period_start->format('Y-m'),
                'status_label' => $payslip->run->status->label(),
                'status_color' => $payslip->run->status->color(),
                'basic' => (string) $payslip->basic,
                'earnings' => (string) $payslip->earnings_total,
                'deductions' => (string) $payslip->deductions_total,
                'net_pay' => (string) $payslip->net_pay,
            ])->values(),
            'baseCurrency' => ['code' => $currencies->base()->code, 'decimal_places' => $currencies->base()->decimal_places],
        ]);
    }

    public function edit(Request $request, Employee $employee, AccountingOptions $options): Response
    {
        Gate::authorize('update', $employee);

        $employee->load('components');

        return Inertia::render('admin/payroll/employees/Edit', [
            ...$this->formOptions($options),
            'employee' => [
                ...$this->row($request, $employee),
                'components' => $employee->components->map(fn (EmployeePayComponent $assignment): array => [
                    'pay_component_id' => $assignment->pay_component_id,
                    'value' => (string) $assignment->value,
                ])->values(),
            ],
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('update', $employee);

        $this->save($employee, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee updated.')]);

        return to_route('admin.payroll.employees.show', $employee);
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        Gate::authorize('delete', $employee);

        SafeDelete::delete($employee);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee deleted.')]);

        return to_route('admin.payroll.employees.index');
    }

    protected function save(Employee $employee, Request $request): Employee
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique(Employee::class, 'code')->ignore($employee)],
            'name' => ['required', 'string', 'max:150'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:150'],
            'cost_center_id' => ['nullable', 'integer', Rule::exists(CostCenter::class, 'id')->where('is_active', true)],
            'hire_date' => ['required', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'basic_salary' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'payment_method' => ['required', Rule::in(['bank', 'cash'])],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'iban' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'components' => ['array', 'max:50'],
            'components.*.pay_component_id' => ['required', 'integer', 'distinct', Rule::exists(PayComponent::class, 'id')],
            'components.*.value' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
        ]);

        return DB::transaction(function () use ($employee, $validated, $request): Employee {
            $employee->fill([...Arr::except($validated, ['components']), 'is_active' => $request->boolean('is_active', true)])->save();

            $employee->components()->delete();
            $employee->components()->createMany($validated['components'] ?? []);

            return $employee;
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(Request $request, Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'code' => $employee->code,
            'name' => $employee->name,
            'national_id' => $employee->national_id,
            'position' => $employee->position,
            'cost_center_id' => $employee->cost_center_id,
            'cost_center' => $employee->costCenter?->label(),
            'hire_date' => $employee->hire_date->toDateString(),
            'termination_date' => $employee->termination_date?->toDateString(),
            'basic_salary' => (string) $employee->basic_salary,
            'payment_method' => $employee->payment_method,
            'bank_name' => $employee->bank_name,
            'iban' => $employee->iban,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'notes' => $employee->notes,
            'is_active' => $employee->is_active,
            'can' => [
                'update' => $request->user()?->can('update', $employee) ?? false,
                'delete' => $request->user()?->can('delete', $employee) ?? false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(AccountingOptions $options): array
    {
        return [
            'costCenters' => $options->costCenters(),
            'payComponents' => PayComponent::query()->orderByDesc('is_active')->get()->map(fn (PayComponent $component): array => [
                'value' => $component->id,
                'label' => $component->translate('name'),
                'type' => $component->type->value,
                'type_label' => $component->type->label(),
                'calculation' => $component->calculation->value,
                'default_value' => (string) $component->default_value,
            ])->values(),
        ];
    }
}
