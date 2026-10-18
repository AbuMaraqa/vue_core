<?php

use App\Modules\Accounting\Enums\JournalEntryStatus;
use App\Modules\Accounting\Support\AccountBalances;
use App\Modules\Accounting\Support\Decimal;
use App\Modules\Inventory\Actions\StockAdjustments\PostStockAdjustment;
use App\Modules\Inventory\Data\StockAdjustmentData;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Support\StockLedger;
use App\Modules\Production\Enums\ProductionOrderStatus;
use App\Modules\Production\Models\BillOfMaterials;
use App\Modules\Production\Models\ProductionOrder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A small carpentry: tables are made of wood, screws and glue. The opening
 * stock is 100 wood at 2.00, 1000 screws at 0.10 and 10 glue at 1.50
 * (inventory 315.00), so every cost below can be checked by hand.
 */
beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    seedAccounting();

    $this->materials = Warehouse::factory()->create(['code' => 'RAW']);
    $this->finished = Warehouse::factory()->create(['code' => 'FG']);
    $this->table = Item::factory()->create(['code' => 'TABLE']);
    $this->wood = Item::factory()->create(['code' => 'WOOD']);
    $this->screw = Item::factory()->create(['code' => 'SCREW']);
    $this->glue = Item::factory()->create(['code' => 'GLUE']);

    app(PostStockAdjustment::class)->handle(new StockAdjustmentData(
        date: '2026-06-01',
        warehouse_id: $this->materials->id,
        lines: [
            ['item_id' => $this->wood->id, 'quantity' => '100', 'unit_cost' => '2'],
            ['item_id' => $this->screw->id, 'quantity' => '1000', 'unit_cost' => '0.1'],
            ['item_id' => $this->glue->id, 'quantity' => '10', 'unit_cost' => '1.5'],
        ],
        account_id: account('3103')->id,
    ));
});

/**
 * The balance of an account of the seeded chart, debits minus credits.
 */
function productionBalance(string $code): string
{
    return Decimal::round(app(AccountBalances::class)->asOf(now())[account($code)->id] ?? '0');
}

function onHand(Item $item, Warehouse $warehouse): string
{
    return app(StockLedger::class)->onHand($item, $warehouse);
}

/**
 * Plan an order for two tables: 10 wood, 40 screws and 20.00 of labour.
 *
 * @param  array<string, mixed>  $overrides
 */
function planOrder(array $overrides = []): ProductionOrder
{
    test()->actingAs(staffMember('Production Order Create'))
        ->post(route('admin.production.orders.store'), [
            'date' => '2026-06-10',
            'item_id' => test()->table->id,
            'quantity' => '2',
            'materials_warehouse_id' => test()->materials->id,
            'product_warehouse_id' => test()->finished->id,
            'materials' => [
                ['item_id' => test()->wood->id, 'quantity' => '10'],
                ['item_id' => test()->screw->id, 'quantity' => '40'],
            ],
            'costs' => [
                ['description' => 'Labour', 'account_id' => account('5104')->id, 'amount' => '20'],
            ],
            ...$overrides,
        ])
        ->assertSessionHasNoErrors();

    return ProductionOrder::query()->latest('id')->firstOrFail();
}

function startOrder(ProductionOrder $order, string $date = '2026-06-10'): void
{
    test()->actingAs(staffMember('Production Order Start'))
        ->post(route('admin.production.orders.start', $order), ['date' => $date])
        ->assertSessionHasNoErrors();
}

/**
 * Finish the order: 12 wood used (2 more), 30 screws (10 back), 2 glue
 * added, and 20.00 labour plus 6.00 paint.
 */
function completeOrder(ProductionOrder $order): void
{
    $lines = $order->materials()->get()->keyBy('item_id');

    test()->actingAs(staffMember('Production Order Complete'))
        ->post(route('admin.production.orders.complete', $order), [
            'date' => '2026-06-12',
            'produced_quantity' => '2',
            'materials' => [
                ['id' => $lines[test()->wood->id]->id, 'item_id' => test()->wood->id, 'consumed_quantity' => '12'],
                ['id' => $lines[test()->screw->id]->id, 'item_id' => test()->screw->id, 'consumed_quantity' => '30'],
                ['item_id' => test()->glue->id, 'consumed_quantity' => '2'],
            ],
            'costs' => [
                ['description' => 'Labour', 'account_id' => account('5104')->id, 'amount' => '20'],
                ['description' => 'Paint', 'account_id' => account('5104')->id, 'amount' => '6'],
            ],
        ])
        ->assertSessionHasNoErrors();
}

describe('bills of materials', function () {
    test('the list needs its permission', function () {
        $this->actingAs(staffMember())
            ->get(route('admin.production.boms.index'))
            ->assertForbidden();
    });

    test('a recipe is saved with its materials and costs and estimated at the average cost', function () {
        $this->actingAs(staffMember('Bill Of Materials Create', 'Bill Of Materials View List'))
            ->post(route('admin.production.boms.store'), [
                'code' => 'BOM-TABLE',
                'name' => 'Dining table',
                'item_id' => $this->table->id,
                'quantity' => '1',
                'components' => [
                    ['item_id' => $this->wood->id, 'quantity' => '4', 'waste_percent' => '25'],
                    ['item_id' => $this->screw->id, 'quantity' => '20'],
                ],
                'costs' => [
                    ['description' => 'Labour', 'account_id' => account('5104')->id, 'amount' => '10'],
                ],
            ])
            ->assertRedirect(route('admin.production.boms.index'))
            ->assertSessionHasNoErrors();

        $bom = BillOfMaterials::query()->sole();

        expect($bom->components)->toHaveCount(2)
            ->and($bom->components->first()->grossQuantity())->toBe('5.0000')
            ->and($bom->costs->sole()->amount)->toBe('10.0000');

        // 5 wood × 2.00 + 20 screws × 0.10 + 10.00 labour
        $this->get(route('admin.production.boms.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/production/boms/Index')
                ->has('boms.data', 1)
                ->where("estimates.{$bom->id}.materials", '12.0000')
                ->where("estimates.{$bom->id}.total", '22.0000')
                ->where("estimates.{$bom->id}.unit", '22.0000'));
    });

    test('a product cannot be one of its own materials, nor a material repeated', function () {
        $this->actingAs(staffMember('Bill Of Materials Create'))
            ->post(route('admin.production.boms.store'), [
                'code' => 'BOM-1',
                'name' => 'Table',
                'item_id' => $this->table->id,
                'quantity' => '1',
                'components' => [
                    ['item_id' => $this->table->id, 'quantity' => '1'],
                    ['item_id' => $this->wood->id, 'quantity' => '1'],
                    ['item_id' => $this->wood->id, 'quantity' => '2'],
                ],
            ])
            ->assertSessionHasErrors(['components.0.item_id', 'components.1.item_id', 'components.2.item_id']);

        expect(BillOfMaterials::query()->count())->toBe(0);
    });
});

describe('production orders', function () {
    test('a draft moves no stock and posts nothing', function () {
        $order = planOrder();

        expect($order)
            ->number->toStartWith('PRD-2026-')
            ->status->toBe(ProductionOrderStatus::Draft)
            ->and($order->materials)->toHaveCount(2)
            ->and($order->journalEntries)->toBeEmpty()
            ->and(onHand($this->wood, $this->materials))->toBe('100.0000');
    });

    test('starting an order issues its materials to work in progress', function () {
        $order = planOrder();
        startOrder($order);

        $order->refresh();

        // 10 wood × 2.00 + 40 screws × 0.10
        expect($order->status)->toBe(ProductionOrderStatus::InProgress)
            ->and($order->started_on->toDateString())->toBe('2026-06-10')
            ->and($order->materials->pluck('issued_value')->all())->toBe(['20.0000', '4.0000'])
            ->and(onHand($this->wood, $this->materials))->toBe('90.0000')
            ->and(onHand($this->screw, $this->materials))->toBe('960.0000')
            ->and(productionBalance('1110'))->toBe('24.0000')
            ->and(productionBalance('1106'))->toBe('291.0000');
    });

    test('completing an order brings the product into stock at what was really consumed plus the other costs', function () {
        $order = planOrder();
        startOrder($order);
        completeOrder($order);

        $order->refresh();

        // materials: wood 12 × 2.00 + screws 30 × 0.10 + glue 2 × 1.50 = 30.00; costs 26.00
        expect($order)
            ->status->toBe(ProductionOrderStatus::Completed)
            ->produced_quantity->toBe('2.0000')
            ->materials_cost->toBe('30.0000')
            ->other_costs->toBe('26.0000')
            ->total_cost->toBe('56.0000')
            ->unit_cost->toBe('28.000000')
            ->and($order->materials)->toHaveCount(3)
            ->and($order->materials->pluck('value')->all())->toBe(['24.0000', '3.0000', '3.0000'])
            ->and(onHand($this->wood, $this->materials))->toBe('88.0000')
            ->and(onHand($this->screw, $this->materials))->toBe('970.0000')
            ->and(onHand($this->glue, $this->materials))->toBe('8.0000')
            ->and(onHand($this->table, $this->finished))->toBe('2.0000')
            ->and(StockLevel::query()->where('item_id', $this->table->id)->value('value'))->toBe('56.0000')
            ->and(productionBalance('1110'))->toBe('0.0000')
            ->and(productionBalance('1106'))->toBe('341.0000')
            ->and(productionBalance('5104'))->toBe('-26.0000');
    });

    test('a completed order steps back to in progress, then to a draft, restoring stock and books', function () {
        $order = planOrder();
        startOrder($order);
        completeOrder($order);

        $reverser = staffMember('Production Order Reverse');

        $this->actingAs($reverser)->post(route('admin.production.orders.reverse', $order))->assertSessionHasNoErrors();
        $order->refresh();

        expect($order->status)->toBe(ProductionOrderStatus::InProgress)
            ->and($order->materials)->toHaveCount(2)
            ->and($order->materials->pluck('consumed_quantity')->all())->toBe([null, null])
            ->and(onHand($this->table, $this->finished))->toBe('0.0000')
            ->and(onHand($this->wood, $this->materials))->toBe('90.0000')
            ->and(onHand($this->glue, $this->materials))->toBe('10.0000')
            ->and(productionBalance('1110'))->toBe('24.0000')
            ->and(productionBalance('5104'))->toBe('0.0000');

        $this->actingAs($reverser)->post(route('admin.production.orders.reverse', $order))->assertSessionHasNoErrors();
        $order->refresh();

        expect($order->status)->toBe(ProductionOrderStatus::Draft)
            ->and(onHand($this->wood, $this->materials))->toBe('100.0000')
            ->and(onHand($this->screw, $this->materials))->toBe('1000.0000')
            ->and(productionBalance('1110'))->toBe('0.0000')
            ->and(productionBalance('1106'))->toBe('315.0000')
            ->and($order->journalEntries()->where('status', JournalEntryStatus::Posted)->whereNull('reversal_of_id')->exists())->toBeFalse();
    });

    test('a completed order cannot be reopened once its product has left the warehouse', function () {
        $order = planOrder();
        startOrder($order);
        completeOrder($order);

        app(StockLedger::class)->issue($this->table, $this->finished, '1', '2026-06-13', StockMovementType::Sale);

        $this->actingAs(staffMember('Production Order Reverse'))
            ->post(route('admin.production.orders.reverse', $order))
            ->assertSessionHasErrors('accounting');

        expect($order->refresh()->status)->toBe(ProductionOrderStatus::Completed);
    });

    test('an order cannot start without enough materials, and nothing is issued', function () {
        $order = planOrder(['materials' => [
            ['item_id' => $this->wood->id, 'quantity' => '10'],
            ['item_id' => $this->glue->id, 'quantity' => '11'],
        ]]);

        $this->actingAs(staffMember('Production Order Start'))
            ->post(route('admin.production.orders.start', $order), ['date' => '2026-06-10'])
            ->assertSessionHasErrors('accounting');

        expect($order->refresh()->status)->toBe(ProductionOrderStatus::Draft)
            ->and(onHand($this->wood, $this->materials))->toBe('100.0000')
            ->and(productionBalance('1110'))->toBe('0.0000');
    });

    test('an order cannot be completed before the day it started', function () {
        $order = planOrder();
        startOrder($order, '2026-06-12');

        $lines = $order->materials()->get();

        $this->actingAs(staffMember('Production Order Complete'))
            ->post(route('admin.production.orders.complete', $order), [
                'date' => '2026-06-11',
                'produced_quantity' => '2',
                'materials' => $lines->map(fn ($line) => ['id' => $line->id, 'item_id' => $line->item_id, 'consumed_quantity' => $line->quantity])->all(),
            ])
            ->assertSessionHasErrors('accounting');

        expect($order->refresh()->status)->toBe(ProductionOrderStatus::InProgress);
    });

    test('only drafts can be edited', function () {
        $order = planOrder();
        startOrder($order);

        $this->actingAs(staffMember('Production Order Update'))
            ->get(route('admin.production.orders.edit', $order))
            ->assertForbidden();
    });

    test('a draft that never started is deleted, one that did is kept as cancelled', function () {
        $deleter = staffMember('Production Order Delete');

        $fresh = planOrder();
        $this->actingAs($deleter)->delete(route('admin.production.orders.destroy', $fresh))
            ->assertRedirect(route('admin.production.orders.index'));

        expect(ProductionOrder::query()->find($fresh->id))->toBeNull();

        $started = planOrder();
        startOrder($started);
        $this->actingAs(staffMember('Production Order Reverse'))->post(route('admin.production.orders.reverse', $started));
        $this->actingAs($deleter)->delete(route('admin.production.orders.destroy', $started));

        expect($started->refresh()->status)->toBe(ProductionOrderStatus::Cancelled);
    });

    test('the show page lists the order with its abilities', function () {
        $order = planOrder();

        $this->actingAs(staffMember('Production Order View', 'Production Order Start'))
            ->get(route('admin.production.orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/production/orders/Show')
                ->where('order.data.number', $order->number)
                ->has('order.data.materials', 2)
                ->where('order.data.can.start', true)
                ->where('order.data.can.complete', false));
    });
});

describe('report', function () {
    test('the production report sums the completed orders, per product and per material', function () {
        $order = planOrder();
        startOrder($order);
        completeOrder($order);

        $this->actingAs(staffMember('Production Report View'))
            ->get(route('admin.production.reports.summary', ['from' => '2026-06-01', 'to' => '2026-06-30']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/production/reports/Summary')
                ->has('report.orders', 1)
                ->where('report.totals.total_cost', '56.0000')
                ->where('report.products.0.average_unit_cost', '28.0000')
                ->has('report.materials', 3)
                ->where('report.materials.0.value', '24.0000'));
    });
});

describe('printing', function () {
    test('a production order prints as a PDF, planned and completed', function () {
        $order = planOrder();
        $viewer = staffMember('Production Order View');

        $planned = $this->actingAs($viewer)->get(route('admin.production.orders.pdf', $order))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        startOrder($order);
        completeOrder($order);

        $completed = $this->actingAs($viewer)->get(route('admin.production.orders.pdf', $order))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        expect($planned->getContent())->toStartWith('%PDF')
            ->and($completed->getContent())->toStartWith('%PDF');
    });

    test('printing a production order needs the permission to view it', function () {
        $order = planOrder();

        $this->actingAs(staffMember('Production Order View List'))
            ->get(route('admin.production.orders.pdf', $order))
            ->assertForbidden();
    });
});
