<?php

use App\Models\User;
use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Banking\Models\Cheque;
use App\Modules\Inventory\Models\Item;
use App\Modules\Invoicing\Models\Invoice;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('admin.dashboard'));

    $response->assertRedirect(route('login'));
});

test('users without admin access are forbidden from the dashboard', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('admin.dashboard'));

    $response->assertForbidden();
});

test('staff members can visit the dashboard', function () {
    User::factory()->count(2)->create();

    $response = $this->actingAs(staffMember())->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/Dashboard')
        ->where('stats.users', 3)
        ->where('stats.newUsers', 3)
        ->has('recentUsers.data', 3)
        ->missing('registrations')
        ->loadDeferredProps(fn (Assert $reload) => $reload->has('registrations', 12)),
    );
});

test('super admins can visit the dashboard without explicit permissions', function () {
    $response = $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.dashboard'));

    $response->assertOk();
});

test('the dashboard warns about overdue invoices, cheques due soon and items running low', function () {
    Invoice::factory()->create([
        'status' => DocumentStatus::Posted,
        'due_date' => now()->subDay()->toDateString(),
        'total' => '100',
        'paid_amount' => '0',
    ]);
    Invoice::factory()->create(['status' => DocumentStatus::Posted, 'total' => '100', 'paid_amount' => '100']);
    Cheque::factory()->create(['due_date' => now()->addDays(3)->toDateString()]);
    Cheque::factory()->create();
    Item::factory()->create(['reorder_level' => '5']);
    Item::factory()->create(['reorder_level' => null]);

    $this->actingAs(staffMember('Sales Invoice View List', 'Cheque View List', 'Item View List'))
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('business', fn (Assert $reload) => $reload
                ->has('businessStats', 3)
                ->where('businessStats.0.key', 'overdue-invoices')
                ->where('businessStats.0.value', '1')
                ->where('businessStats.1.key', 'cheques-due-this-week')
                ->where('businessStats.1.value', '1')
                ->where('businessStats.2.key', 'items-running-low')
                ->where('businessStats.2.value', '1')));

    $this->actingAs(staffMember())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('business', fn (Assert $reload) => $reload->has('businessStats', 0)));
});

test('the quick actions only offer what the user may do', function () {
    $this->actingAs(staffMember('Sales Invoice Create', 'Bank Voucher Create'))
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('shortcuts', 2)
            ->where('shortcuts.0.key', 'new-sale')
            ->where('shortcuts.0.url', route('admin.sales.invoices.create', absolute: false))
            ->where('shortcuts.1.key', 'record-an-expense')
            ->where('shortcuts.1.url', route('admin.banking.vouchers.create', ['direction' => 'out', 'kind' => 'expense'], absolute: false)),
        );

    $this->actingAs(staffMember())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('shortcuts', 0));
});
