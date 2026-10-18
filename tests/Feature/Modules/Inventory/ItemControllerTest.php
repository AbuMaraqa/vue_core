<?php

use App\Modules\Inventory\Enums\ItemType;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Unit;

beforeEach(function () {
    seedAccounting();
});

describe('quick store', function () {
    test('an item created from a document form comes back as a picker option', function () {
        $unit = Unit::factory()->create(['name' => ['en' => 'Piece', 'ar' => 'قطعة']]);

        $response = $this->actingAs(staffMember('Item Create'))
            ->postJson(route('admin.inventory.items.quick-store'), [
                'name' => ['en' => 'Oak table'], 'type' => 'stock', 'sale_price' => '120', 'unit_id' => $unit->id,
            ])
            ->assertCreated();

        $item = Item::query()->sole();

        $response->assertExactJson([
            'value' => $item->id,
            'label' => $item->label(),
            'hint' => 'Piece',
            'type' => 'stock',
            'unit' => 'Piece',
            'sale_price' => '120.0000',
            'purchase_price' => '0.0000',
            'sales_tax_id' => null,
            'purchase_tax_id' => null,
            'on_hand' => '0',
        ]);

        expect($item)
            ->code->toBe(sprintf('I-%05d', $item->id))
            ->type->toBe(ItemType::Stock)
            ->is_active->toBeTrue();
    });

    test('it is validated like the item form', function () {
        $this->actingAs(staffMember('Item Create'))
            ->postJson(route('admin.inventory.items.quick-store'), ['name' => ['en' => ''], 'type' => 'gadget', 'sale_price' => '-1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name.en', 'type', 'sale_price']);
    });

    test('staff members without the permission are forbidden', function () {
        $this->actingAs(staffMember())
            ->postJson(route('admin.inventory.items.quick-store'), ['name' => ['en' => 'Oak table'], 'type' => 'service'])
            ->assertForbidden();

        expect(Item::query()->count())->toBe(0);
    });
});
