<?php

use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    seedAccounting();

    $this->main = Warehouse::factory()->create(['code' => 'MAIN']);
    $this->branch = Warehouse::factory()->create(['code' => 'BRANCH']);
    $this->item = Item::factory()->create(['code' => 'CHAIR']);

    $this->actingAs(staffMember('Stock Adjustment Create', 'Stock Transfer Create'))
        ->post(route('admin.inventory.adjustments.store'), [
            'date' => '2026-06-10',
            'warehouse_id' => $this->main->id,
            'lines' => [['item_id' => $this->item->id, 'quantity' => '10', 'unit_cost' => '5']],
        ])
        ->assertSessionHasNoErrors();

    $this->post(route('admin.inventory.transfers.store'), [
        'date' => '2026-06-11',
        'from_warehouse_id' => $this->main->id,
        'to_warehouse_id' => $this->branch->id,
        'lines' => [['item_id' => $this->item->id, 'quantity' => '4']],
    ])->assertSessionHasNoErrors();
});

test('a stock adjustment prints as a PDF', function () {
    $response = $this->actingAs(staffMember('Stock Adjustment View'))
        ->get(route('admin.inventory.adjustments.pdf', StockAdjustment::query()->sole()))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
});

test('a stock transfer prints as a PDF', function () {
    $response = $this->actingAs(staffMember('Stock Transfer View'))
        ->get(route('admin.inventory.transfers.pdf', StockTransfer::query()->sole()))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
});

test('the stock card of an item prints as a PDF', function () {
    $response = $this->actingAs(staffMember('Item View'))
        ->get(route('admin.inventory.items.pdf', $this->item))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
});

test('printing needs the permission to view the document', function (string $route, Model $document) {
    $this->actingAs(staffMember())
        ->get(route($route, $document))
        ->assertForbidden();
})->with([
    'stock adjustment' => ['admin.inventory.adjustments.pdf', fn () => StockAdjustment::query()->sole()],
    'stock transfer' => ['admin.inventory.transfers.pdf', fn () => StockTransfer::query()->sole()],
    'item' => ['admin.inventory.items.pdf', fn () => Item::query()->sole()],
]);
