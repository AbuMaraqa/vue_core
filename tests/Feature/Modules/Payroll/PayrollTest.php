<?php

use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Support\AccountBalances;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Banking\Actions\BankAccounts\SaveBankAccount;
use App\Modules\Banking\Data\BankAccountData;
use App\Modules\Banking\Enums\BankAccountType;
use App\Modules\Banking\Models\BankAccount;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\PayComponent;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\Payslip;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * May 2026 (31 days):
 *
 *   Sara, all month: basic 1,000 + transport 50 − social security 7.5% (75)
 *   − advance 100 = net 875; employer social security 14.25% = 142.50.
 *   Omar, hired on 16 May: basic 620 × 16/31 = 320.
 */
beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    seedAccounting();

    $component = fn (string $name, string $type, string $calculation, string $account, ?string $liability = null) => PayComponent::query()->create([
        'name' => ['en' => $name], 'type' => $type, 'calculation' => $calculation, 'default_value' => '0',
        'account_id' => account($account)->id, 'liability_account_id' => $liability ? account($liability)->id : null,
    ]);

    $transport = $component('Transport', 'earning', 'fixed', '5201');
    $socialSecurity = $component('Social security', 'deduction', 'percent_of_basic', '2105');
    $employerSocialSecurity = $component('Employer social security', 'employer_contribution', 'percent_of_basic', '5201', '2105');
    $advance = $component('Advance recovery', 'deduction', 'fixed', '1109');

    $this->sara = Employee::factory()->create(['code' => 'E1', 'name' => 'Sara', 'basic_salary' => '1000', 'hire_date' => '2025-01-01']);
    $this->sara->components()->createMany([
        ['pay_component_id' => $transport->id, 'value' => '50'],
        ['pay_component_id' => $socialSecurity->id, 'value' => '7.5'],
        ['pay_component_id' => $employerSocialSecurity->id, 'value' => '14.25'],
        ['pay_component_id' => $advance->id, 'value' => '100'],
    ]);
    $this->omar = Employee::factory()->create(['code' => 'E2', 'name' => 'Omar', 'basic_salary' => '620', 'hire_date' => '2026-05-16']);
    Employee::factory()->create(['code' => 'E3', 'termination_date' => '2026-04-30', 'hire_date' => '2025-01-01']);

    $this->cash = app(SaveBankAccount::class)->handle(new BankAccount, new BankAccountData(
        name: 'Cash', type: BankAccountType::Cash, currency_id: Currency::query()->where('code', 'JOD')->value('id'),
    ));
});

function gl(string $code): string
{
    return Decimal::round(app(AccountBalances::class)->asOf(now())[account($code)->id] ?? '0');
}

function prepareMay(): PayrollRun
{
    test()->actingAs(staffMember('Payroll Run Create'))
        ->post(route('admin.payroll.runs.store'), ['month' => '2026-05'])
        ->assertSessionHasNoErrors();

    return PayrollRun::query()->latest('id')->firstOrFail();
}

test('a payroll is prepared with a payslip for everyone employed in the month', function () {
    $run = prepareMay();
    $sara = $run->payslips()->where('employee_id', $this->sara->id)->sole();
    $omar = $run->payslips()->where('employee_id', $this->omar->id)->sole();

    expect($run->payslips()->count())->toBe(2)
        ->and($run->status)->toBe(PayrollRunStatus::Draft)
        ->and($sara)
        ->basic->toBe('1000.0000')
        ->earnings_total->toBe('50.0000')
        ->deductions_total->toBe('175.0000')
        ->employer_total->toBe('142.5000')
        ->net_pay->toBe('875.0000')
        ->and($omar)->days_worked->toBe(16)->basic->toBe('320.0000')
        ->and($run->fresh()->net_total)->toBe('1195.0000');

    $this->actingAs(staffMember('Payroll Run Create'))
        ->post(route('admin.payroll.runs.store'), ['month' => '2026-05'])
        ->assertSessionHasErrors('accounting');
});

test('a draft payslip can be adjusted', function () {
    $run = prepareMay();
    $omar = $run->payslips()->where('employee_id', $this->omar->id)->sole();
    $bonus = PayComponent::query()->where('name->en', 'Transport')->sole();

    $this->actingAs(staffMember('Payroll Run Update'))
        ->put(route('admin.payroll.runs.payslips.update', [$run, $omar]), [
            'basic' => '320',
            'lines' => [['pay_component_id' => $bonus->id, 'amount' => '30']],
        ])
        ->assertSessionHasNoErrors();

    expect($omar->fresh()->net_pay)->toBe('350.0000')
        ->and($run->fresh()->net_total)->toBe('1225.0000');
});

test('posting accrues the salaries, and paying settles them', function () {
    $run = prepareMay();

    $this->actingAs(staffMember('Payroll Run Post'))->post(route('admin.payroll.runs.post', $run))->assertSessionHasNoErrors();

    expect($run->fresh())->status->toBe(PayrollRunStatus::Posted)->number->toStartWith('PR-2026-')
        ->and(gl('5201'))->toBe('1512.5000')
        ->and(gl('2105'))->toBe('-217.5000')
        ->and(gl('1109'))->toBe('-100.0000')
        ->and(gl('2104'))->toBe('-1195.0000');

    $this->actingAs(staffMember('Payroll Run Pay'))
        ->post(route('admin.payroll.runs.pay', $run), ['bank_account_id' => $this->cash->id, 'date' => '2026-06-01'])
        ->assertSessionHasNoErrors();

    expect($run->fresh()->status)->toBe(PayrollRunStatus::Paid)
        ->and(gl('2104'))->toBe('0.0000')
        ->and(gl($this->cash->account->code))->toBe('-1195.0000');
});

describe('post and pay', function () {
    test('one request posts a draft payroll and pays it', function () {
        $run = prepareMay();

        $this->actingAs(staffMember('Payroll Run Post', 'Payroll Run Pay'))
            ->post(route('admin.payroll.runs.post-and-pay', $run), ['bank_account_id' => $this->cash->id, 'date' => '2026-06-01'])
            ->assertSessionHasNoErrors();

        expect($run->fresh())->status->toBe(PayrollRunStatus::Paid)->number->toStartWith('PR-2026-')
            ->and($run->journalEntries()->count())->toBe(2)
            ->and(gl('5201'))->toBe('1512.5000')
            ->and(gl('2104'))->toBe('0.0000')
            ->and(gl($this->cash->account->code))->toBe('-1195.0000');
    });

    test('a draft needs the permission to pay as well as to post', function () {
        $run = prepareMay();

        $this->actingAs(staffMember('Payroll Run Post'))
            ->post(route('admin.payroll.runs.post-and-pay', $run), ['bank_account_id' => $this->cash->id, 'date' => '2026-06-01'])
            ->assertForbidden();

        expect($run->fresh()->status)->toBe(PayrollRunStatus::Draft)
            ->and(gl('2104'))->toBe('0.0000');
    });

    test('a paid payroll cannot be paid again', function () {
        $run = prepareMay();
        $user = staffMember('Payroll Run Post', 'Payroll Run Pay');
        $payment = ['bank_account_id' => $this->cash->id, 'date' => '2026-06-01'];

        $this->actingAs($user)->post(route('admin.payroll.runs.post-and-pay', $run), $payment)->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('admin.payroll.runs.post-and-pay', $run), $payment)
            ->assertForbidden();

        expect(gl($this->cash->account->code))->toBe('-1195.0000');
    });
});

test('each step can be taken back', function () {
    $run = prepareMay();
    $this->actingAs(staffMember('Payroll Run Post'))->post(route('admin.payroll.runs.post', $run));
    $this->actingAs(staffMember('Payroll Run Pay'))->post(route('admin.payroll.runs.pay', $run), ['bank_account_id' => $this->cash->id, 'date' => '2026-06-01']);

    $this->actingAs(staffMember('Payroll Run Cancel'))->post(route('admin.payroll.runs.reverse', $run))->assertSessionHasNoErrors();

    expect($run->fresh()->status)->toBe(PayrollRunStatus::Posted)
        ->and(gl('2104'))->toBe('-1195.0000');

    $this->actingAs(staffMember('Payroll Run Cancel'))->post(route('admin.payroll.runs.reverse', $run))->assertSessionHasNoErrors();

    expect($run->fresh()->status)->toBe(PayrollRunStatus::Cancelled)
        ->and(gl('5201'))->toBe('0.0000')
        ->and(gl('2104'))->toBe('0.0000');

    $draft = prepareMay();

    $this->actingAs(staffMember('Payroll Run Cancel'))
        ->post(route('admin.payroll.runs.reverse', $draft))
        ->assertRedirect(route('admin.payroll.runs.index'));

    expect($draft->fresh())->toBeNull();
});

test('an employee who was paid cannot be deleted', function () {
    $run = prepareMay();

    $this->actingAs(staffMember('Employee Delete'))
        ->delete(route('admin.payroll.employees.destroy', $this->sara))
        ->assertForbidden();

    $this->actingAs(staffMember('Payroll Run View'))
        ->get(route('admin.payroll.runs.show', $run))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payroll/runs/Show')
            ->has('run.payslips', 2)
            ->where('run.net_total', '1195.0000'));
});

test('employees are saved with their components', function () {
    $transport = PayComponent::query()->where('name->en', 'Transport')->sole();

    $this->actingAs(staffMember('Employee Create'))
        ->post(route('admin.payroll.employees.store'), [
            'code' => 'E9', 'name' => 'Lina', 'hire_date' => '2026-06-01', 'basic_salary' => '700', 'payment_method' => 'bank',
            'components' => [['pay_component_id' => $transport->id, 'value' => '40']],
        ])
        ->assertSessionHasNoErrors();

    expect(Employee::query()->where('code', 'E9')->sole()->components()->sole()->value)->toBe('40.0000');
});

describe('printing', function () {
    test('the payroll summary and each payslip print as PDFs for those who can view the run', function () {
        $run = prepareMay();
        $payslip = $run->payslips()->where('employee_id', $this->sara->id)->sole();
        $viewer = staffMember('Payroll Run View');

        $summary = $this->actingAs($viewer)->get(route('admin.payroll.runs.pdf', $run))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $slip = $this->actingAs($viewer)->get(route('admin.payroll.runs.payslips.pdf', [$run, $payslip]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        expect($summary->getContent())->toStartWith('%PDF')
            ->and($slip->getContent())->toStartWith('%PDF');
    });

    test('printing needs the permission to view the run', function () {
        $run = prepareMay();
        $payslip = $run->payslips()->firstOrFail();

        $this->actingAs(staffMember('Payroll Run View List'))
            ->get(route('admin.payroll.runs.pdf', $run))
            ->assertForbidden();
        $this->actingAs(staffMember('Payroll Run View List'))
            ->get(route('admin.payroll.runs.payslips.pdf', [$run, $payslip]))
            ->assertForbidden();
    });

    test('a payslip of another run is not found', function () {
        $run = prepareMay();
        $otherPayslip = Payslip::factory()->create();

        $this->actingAs(staffMember('Payroll Run View'))
            ->get(route('admin.payroll.runs.payslips.pdf', [$run, $otherPayslip]))
            ->assertNotFound();
    });
});
