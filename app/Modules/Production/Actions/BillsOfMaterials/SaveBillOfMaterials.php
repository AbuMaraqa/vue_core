<?php

namespace App\Modules\Production\Actions\BillsOfMaterials;

use App\Modules\Production\Data\BillOfMaterialsData;
use App\Modules\Production\Models\BillOfMaterials;
use Illuminate\Support\Facades\DB;

/**
 * Create or update a recipe. Its materials and costs are replaced as a
 * whole; orders already made from it keep their own copy.
 */
class SaveBillOfMaterials
{
    public function handle(BillOfMaterials $bom, BillOfMaterialsData $data): BillOfMaterials
    {
        return DB::transaction(function () use ($bom, $data): BillOfMaterials {
            $bom->fill([
                'code' => $data->code,
                'name' => $data->name,
                'item_id' => $data->item_id,
                'quantity' => $data->quantity,
                'notes' => $data->notes,
                'is_active' => $data->is_active,
            ])->save();

            $bom->components()->delete();
            $bom->costs()->delete();

            foreach ($data->components as $position => $component) {
                $bom->components()->create([
                    'item_id' => $component['item_id'],
                    'quantity' => $component['quantity'],
                    'waste_percent' => filled($component['waste_percent'] ?? null) ? $component['waste_percent'] : '0',
                    'sort' => $position,
                ]);
            }

            foreach ($data->costs as $position => $cost) {
                $bom->costs()->create([
                    'description' => $cost['description'],
                    'account_id' => $cost['account_id'],
                    'amount' => $cost['amount'],
                    'sort' => $position,
                ]);
            }

            return $bom;
        });
    }
}
