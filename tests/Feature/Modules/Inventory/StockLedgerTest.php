<?php

use App\Modules\Accounting\Exceptions\AccountingException;
use App\Modules\Accounting\Settings\AccountingSettings;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Support\StockLedger;

beforeEach(function () {
    $this->stock = app(StockLedger::class);
    $this->item = Item::factory()->create(['purchase_price' => '4']);
    $this->warehouse = Warehouse::factory()->create();
    $this->source = Warehouse::factory()->create();
});

function stockLevel(): StockLevel
{
    return StockLevel::query()->where('item_id', test()->item->id)->where('warehouse_id', test()->warehouse->id)->sole();
}

test('receipts are averaged by value', function () {
    $this->stock->receive($this->item, $this->warehouse, '10', '5', now(), StockMovementType::Purchase);
    $this->stock->receive($this->item, $this->warehouse, '10', '7', now(), StockMovementType::Purchase);

    expect(stockLevel())
        ->quantity->toBe('20.0000')
        ->value->toBe('120.0000')
        ->average_cost->toBe('6.000000');
});

test('issues take the average cost and leave the average unchanged', function () {
    $this->stock->receive($this->item, $this->warehouse, '10', '5', now(), StockMovementType::Purchase);
    $this->stock->receive($this->item, $this->warehouse, '10', '7', now(), StockMovementType::Purchase);

    $movement = $this->stock->issue($this->item, $this->warehouse, '5', now(), StockMovementType::Sale);

    expect($movement)
        ->quantity->toBe('-5.0000')
        ->value->toBe('-30.0000')
        ->and(stockLevel())
        ->quantity->toBe('15.0000')
        ->value->toBe('90.0000')
        ->average_cost->toBe('6.000000');
});

test('emptying a warehouse takes all the remaining value, so no cents are left', function () {
    $this->stock->receive($this->item, $this->warehouse, '3', '3.333333', now(), StockMovementType::Purchase);

    $first = $this->stock->issue($this->item, $this->warehouse, '1', now(), StockMovementType::Sale);
    $rest = $this->stock->issue($this->item, $this->warehouse, '2', now(), StockMovementType::Sale);

    expect($first->value)->toBe('-3.3333')
        ->and($rest->value)->toBe('-6.6667')
        ->and(stockLevel())->quantity->toBe('0.0000')->value->toBe('0.0000');
});

test('more than what is on hand cannot be issued', function () {
    $this->stock->receive($this->item, $this->warehouse, '2', '5', now(), StockMovementType::Purchase);

    expect(fn () => $this->stock->issue($this->item, $this->warehouse, '3', now(), StockMovementType::Sale))
        ->toThrow(AccountingException::class);

    expect(stockLevel()->quantity)->toBe('2.0000');
});

test('negative stock can be allowed in the settings, at the last known cost', function () {
    $settings = app(AccountingSettings::class);
    $settings->allow_negative_stock = true;
    $settings->save();

    $movement = $this->stock->issue($this->item, $this->warehouse, '2', now(), StockMovementType::Sale);

    expect($movement->value)->toBe('-8.0000')
        ->and(stockLevel())->quantity->toBe('-2.0000');
});

test('services have no stock', function () {
    $service = Item::factory()->service()->create();

    expect(fn () => $this->stock->receive($service, $this->warehouse, '1', '5', now(), StockMovementType::Purchase))
        ->toThrow(AccountingException::class);
});

test('reversing a document undoes its movements exactly', function () {
    $this->stock->receive($this->item, $this->warehouse, '10', '5', now(), StockMovementType::Purchase);
    $this->stock->receive($this->item, $this->warehouse, '10', '8', now(), StockMovementType::Purchase, $this->source);

    $this->stock->reverse($this->source);

    expect(stockLevel())
        ->quantity->toBe('10.0000')
        ->value->toBe('50.0000')
        ->average_cost->toBe('5.000000')
        ->and(StockMovement::query()->where('is_reversed', true)->count())->toBe(1)
        ->and($this->stock->reverse($this->source))->toBe([]);
});

test('received stock that was already used cannot be reversed', function () {
    $this->stock->receive($this->item, $this->warehouse, '10', '5', now(), StockMovementType::Purchase, $this->source);
    $this->stock->issue($this->item, $this->warehouse, '8', now(), StockMovementType::Sale);

    expect(fn () => $this->stock->reverse($this->source))->toThrow(AccountingException::class);
});

test('the value of a level is always the sum of its movements', function () {
    $this->stock->receive($this->item, $this->warehouse, '7', '3.17', now(), StockMovementType::Purchase);
    $this->stock->issue($this->item, $this->warehouse, '2.5', now(), StockMovementType::Sale);
    $this->stock->receive($this->item, $this->warehouse, '4', '2.99', now(), StockMovementType::Purchase, $this->source);
    $this->stock->issue($this->item, $this->warehouse, '1.25', now(), StockMovementType::Sale);
    $this->stock->reverse($this->source);

    $movements = StockMovement::query()->where('item_id', $this->item->id);

    expect(stockLevel())
        ->quantity->toBe(number_format((float) $movements->clone()->sum('quantity'), 4, '.', ''))
        ->value->toBe(number_format((float) $movements->clone()->sum('value'), 4, '.', ''));
});

test('the quantity on hand adds up every warehouse', function () {
    $other = Warehouse::factory()->create();
    $this->stock->receive($this->item, $this->warehouse, '3', '1', now(), StockMovementType::Purchase);
    $this->stock->receive($this->item, $other, '4.5', '1', now(), StockMovementType::Purchase);

    expect($this->stock->onHand($this->item))->toBe('7.5000')
        ->and($this->stock->onHand($this->item, $other))->toBe('4.5000');
});
