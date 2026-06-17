<?php

namespace App\Repositories\Eloquent;

use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Repositories\Contracts\InventoryRepositoryInterface;

class InventoryRepository implements InventoryRepositoryInterface
{
    public function upsertForVariant(ProductVariant $variant, array $data): Inventory
    {
        return Inventory::updateOrCreate(
            ['product_variant_id' => $variant->id],
            $data,
        );
    }
}
