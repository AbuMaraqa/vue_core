<?php

use App\Modules\Accounting\Enums\DocumentStatus;
use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Support\AccountBalances;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Support\StockLedger;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    seedAccounting();

    $this->main = Warehouse::factory()->create(['code' => 'MAIN']);
    $this->branch = Warehouse::factory()->create(['code' => 'BRANCH']);
    $this->item = Item::factory()->create(['code' => 'CHAIR']);
});

/**
 * @param  list<array<string, mixed>>  $lines
 */
function adjustStock(array $lines, ?int $accountId = null, ?int $warehouseId = null): StockAdjustment
{
    test()->actingAs(staffMember('Stock Adjustment Create'))
        ->post(route('admin.inventory.adjustments.store'), [
            'date' => '2026-06-10',
            'warehouse_id' => $warehouseId ?? test()->main->id,
            'account_id' => $accountId,
            'lines' => $lines,
        ])
        ->assertSessionHasNoErrors();

    return StockAdjustment::query()->latest('id')->firstOrFail();
}

/**
 * The balance of an account of the seeded chart, debits minus credits.
 */
function accountBalance(string $code): string
{
    return Decimal::round(app(AccountBalances::class)->asOf(now())[account($code)->id] ?? '0');
}

describe('adjustments', function () {
    test('found stock is valued at the entered cost and posted against the adjustment account', function () {
        $adjustment = adjustStock([['item_id' => $this->item->id, 'quantity' => '10', 'unit_cost' => '5']]);

        expect($adjustment)
            ->number->toStartWith('ADJ-2026-')
            ->status->toBe(DocumentStatus::Posted)
            ->and($adjustment->lines->sole()->value)->toBe('50.0000')
            ->and(app(StockLedger::class)->onHand($this->item, $this->main))->toBe('10.0000')
            ->and(accountBalance('1106'))->toBe('50.0000')
            ->and(accountBalance('5102'))->toBe('-50.0000');
    });

    test('opening stock can be posted against opening balance equity', function () {
        adjustStock([['item_id' => $this->item->id, 'quantity' => '4', 'unit_cost' => '2.5']], account('3103')->id);

        expect(accountBalance('3103'))->toBe('-10.0000');
    });

    test('lost stock is taken at the average cost', function () {
        adjustStock([['item_id' => $this->item->id, 'quantity' => '10', 'unit_cost' => '5']]);
        adjustStock([['item_id' => $this->item->id, 'quantity' => '-4']]);

        expect(accountBalance('1106'))->toBe('30.0000')
            ->and(accountBalance('5102'))->toBe('-30.0000');
    });

    test('a decrease larger than the stock is refused and nothing is saved', function () {
        $this->actingAs(staffMember('Stock Adjustment Create'))
            ->post(route('admin.inventory.adjustments.store'), [
                'date' => '2026-06-10',
                'warehouse_id' => $this->main->id,
                'lines' => [['item_id' => $this->item->id, 'quantity' => '-1']],
            ])
            ->assertSessionHasErrors('accounting');

        expect(StockAdjustment::query()->exists())->toBeFalse()
            ->and(JournalEntry::query()->exists())->toBeFalse();
    });

    test('services and zero quantities are refused', function () {
        $service = Item::factory()->service()->create();

        $this->actingAs(staffMember('Stock Adjustment Create'))
            ->post(route('admin.inventory.adjustments.store'), [
                'date' => '2026-06-10',
                'warehouse_id' => $this->main->id,
                'lines' => [['item_id' => $service->id, 'quantity' => '1'], ['item_id' => $this->item->id, 'quantity' => '0']],
            ])
            ->assertSessionHasErrors(['lines.0.item_id', 'lines.1.quantity']);
    });

    test('cancelling reverses the stock and the entry', function () {
        $adjustment = adjustStock([['item_id' => $this->item->id, 'quantity' => '10', 'unit_cost' => '5']]);

        $this->actingAs(staffMember('Stock Adjustment Cancel'))
            ->post(route('admin.inventory.adjustments.cancel', $adjustment))
            ->assertRedirect(route('admin.inventory.adjustments.show', $adjustment));

        expect($adjustment->fresh()->status)->toBe(DocumentStatus::Cancelled)
            ->and(app(StockLedger::class)->onHand($this->item))->toBe('0.0000')
            ->and(accountBalance('1106'))->toBe('0.0000')
            ->and(JournalEntry::query()->where('status', JournalEntryStatus::Reversed)->count())->toBe(1);

        $this->actingAs(staffMember('Stock Adjustment Cancel'))
            ->post(route('admin.inventory.adjustments.cancel', $adjustment))
            ->assertForbidden();
    });

    test('the adjustment page shows its lines and entry', function () {
        $adjustment = adjustStock([['item_id' => $this->item->id, 'quantity' => '2', 'unit_cost' => '3']]);

        $this->actingAs(staffMember('Stock Adjustment View'))
            ->get(route('admin.inventory.adjustments.show', $adjustment))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/inventory/adjustments/Show')
                ->where('adjustment.data.total', '6.0000')
                ->has('adjustment.data.journal_entries', 1)
                ->where('adjustment.data.can.cancel', false));
    });
});

describe('transfers', function () {
    beforeEach(function () {
        adjustStock([['item_id' => $this->item->id, 'quantity' => '10', 'unit_cost' => '5']]);
    });

    test('stock moves at the sending average cost without an entry', function () {
        $this->actingAs(staffMember('Stock Transfer Create'))
            ->post(route('admin.inventory.transfers.store'), [
                'date' => '2026-06-11',
                'from_warehouse_id' => $this->main->id,
                'to_warehouse_id' => $this->branch->id,
                'lines' => [['item_id' => $this->item->id, 'quantity' => '4']],
            ])
            ->assertSessionHasNoErrors();

        $stock = app(StockLedger::class);

        expect($stock->onHand($this->item, $this->main))->toBe('6.0000')
            ->and($stock->onHand($this->item, $this->branch))->toBe('4.0000')
            ->and($this->branch->stockLevels()->sole()->value)->toBe('20.0000')
            ->and(JournalEntry::query()->count())->toBe(1);
    });

    test('a transfer needs two different warehouses', function () {
        $this->actingAs(staffMember('Stock Transfer Create'))
            ->post(route('admin.inventory.transfers.store'), [
                'date' => '2026-06-11',
                'from_warehouse_id' => $this->main->id,
                'to_warehouse_id' => $this->main->id,
                'lines' => [['item_id' => $this->item->id, 'quantity' => '1']],
            ])
            ->assertSessionHasErrors('to_warehouse_id');
    });

    test('cancelling sends the stock back', function () {
        $this->actingAs(staffMember('Stock Transfer Create'))
            ->post(route('admin.inventory.transfers.store'), [
                'date' => '2026-06-11',
                'from_warehouse_id' => $this->main->id,
                'to_warehouse_id' => $this->branch->id,
                'lines' => [['item_id' => $this->item->id, 'quantity' => '4']],
            ]);

        $transfer = StockTransfer::query()->sole();

        $this->actingAs(staffMember('Stock Transfer Cancel'))
            ->post(route('admin.inventory.transfers.cancel', $transfer))
            ->assertRedirect();

        expect($transfer->fresh()->status)->toBe(DocumentStatus::Cancelled)
            ->and(app(StockLedger::class)->onHand($this->item, $this->main))->toBe('10.0000')
            ->and(app(StockLedger::class)->onHand($this->item, $this->branch))->toBe('0.0000');
    });
});

describe('items', function () {
    test('items are listed with their stock and filtered below the reorder level', function () {
        $this->item->update(['reorder_level' => '20']);
        Item::factory()->create(['reorder_level' => null]);
        adjustStock([['item_id' => $this->item->id, 'quantity' => '10', 'unit_cost' => '5']]);

        $this->actingAs(staffMember('Item View List'))
            ->get(route('admin.inventory.items.index', ['filter' => ['low_stock' => '1']]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/inventory/items/Index')
                ->has('items.data', 1)
                ->where('items.data.0.code', 'CHAIR')
                ->where('items.data.0.on_hand', '10.0000')
                ->where('items.data.0.stock_value', '50.0000')
                ->where('items.data.0.can.delete', false));
    });

    test('an item is created with accounts of the right kind', function () {
        $this->actingAs(staffMember('Item Create'))
            ->post(route('admin.inventory.items.store'), [
                'code' => 'DESK',
                'name' => ['ar' => 'مكتب', 'en' => 'Desk'],
                'type' => 'stock',
                'sale_price' => '120',
                'inventory_account_id' => account('4101')->id,
            ])
            ->assertSessionHasErrors('inventory_account_id');

        $this->actingAs(staffMember('Item Create'))
            ->post(route('admin.inventory.items.store'), [
                'code' => 'DESK',
                'name' => ['ar' => 'مكتب', 'en' => 'Desk'],
                'type' => 'stock',
                'sale_price' => '120',
            ])
            ->assertSessionHasNoErrors();

        $desk = Item::query()->where('code', 'DESK')->sole();

        expect($desk->sale_price)->toBe('120.0000')
            ->and($desk->inventoryAccountId())->toBe(account('1106')->id)
            ->and($desk->salesAccountId())->toBe(account('4101')->id);
    });

    test('an item saved without a code is numbered from its id and keeps that code', function () {
        $this->actingAs(staffMember('Item Create', 'Item Update'))
            ->post(route('admin.inventory.items.store'), [
                'name' => ['ar' => 'مكتب'],
                'type' => 'stock',
            ])
            ->assertSessionHasNoErrors();

        $desk = Item::query()->latest('id')->first();

        expect($desk->code)->toBe(sprintf('I-%05d', $desk->id));

        $this->actingAs(staffMember('Item Update'))
            ->put(route('admin.inventory.items.update', $desk), [
                'name' => ['ar' => 'مكتب كبير'],
                'type' => 'stock',
            ])
            ->assertSessionHasNoErrors();

        expect($desk->fresh()->code)->toBe(sprintf('I-%05d', $desk->id));
    });

    test('an item that moved in stock keeps its type and cannot be deleted', function () {
        adjustStock([['item_id' => $this->item->id, 'quantity' => '1', 'unit_cost' => '5']]);

        $this->actingAs(staffMember('Item Update'))
            ->put(route('admin.inventory.items.update', $this->item), [
                'code' => 'CHAIR',
                'name' => ['en' => 'Chair'],
                'type' => 'service',
            ])
            ->assertSessionHasErrors('type');

        $this->actingAs(staffMember('Item Delete'))
            ->delete(route('admin.inventory.items.destroy', $this->item))
            ->assertForbidden();
    });

    test('the item picker finds active items by code or name', function () {
        Item::factory()->create(['code' => 'TABLE', 'name' => ['en' => 'Dining table']]);
        Item::factory()->create(['code' => 'OLD', 'name' => ['en' => 'Old table'], 'is_active' => false]);

        $this->actingAs(staffMember())
            ->getJson(route('admin.inventory.items.search', ['search' => 'table']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.label', 'TABLE - Dining table');
    });
});

describe('reports', function () {
    test('the valuation adds up quantities and values on a date', function () {
        adjustStock([['item_id' => $this->item->id, 'quantity' => '10', 'unit_cost' => '5']]);
        app(StockLedger::class)->issue($this->item, $this->main, '4', '2026-06-12', StockMovementType::Sale);

        $this->actingAs(staffMember('Inventory Report View'))
            ->get(route('admin.inventory.reports.valuation', ['as_of' => '2026-06-11']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.rows.0.quantity', '10.0000')
                ->where('report.total_value', '50.0000'));

        $this->actingAs(staffMember('Inventory Report View'))
            ->get(route('admin.inventory.reports.valuation', ['as_of' => '2026-06-12']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.rows.0.quantity', '6.0000')
                ->where('report.rows.0.average_cost', '5.0000')
                ->where('report.total_value', '30.0000'));
    });

    test('the stock card runs from the opening balance', function () {
        adjustStock([['item_id' => $this->item->id, 'quantity' => '10', 'unit_cost' => '5']]);
        app(StockLedger::class)->issue($this->item, $this->main, '4', '2026-06-12', StockMovementType::Sale);

        $this->actingAs(staffMember('Inventory Report View'))
            ->get(route('admin.inventory.reports.stock-card', ['item_id' => $this->item->id, 'from' => '2026-06-11', 'to' => '2026-06-30']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.opening_quantity', '10.0000')
                ->has('report.lines', 1)
                ->where('report.lines.0.out', '4.0000')
                ->where('report.closing_quantity', '6.0000')
                ->where('report.closing_value', '30.0000'));
    });

    test('downloading needs the export permission', function () {
        $this->actingAs(staffMember('Inventory Report View'))
            ->get(route('admin.inventory.reports.valuation', ['format' => 'csv']))
            ->assertForbidden();

        $this->actingAs(staffMember('Inventory Report View', 'Inventory Report Export'))
            ->get(route('admin.inventory.reports.valuation', ['format' => 'csv']))
            ->assertDownload();
    });
});
