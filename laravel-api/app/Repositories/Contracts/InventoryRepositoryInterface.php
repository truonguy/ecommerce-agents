<?php

namespace App\Repositories\Contracts;

use App\Models\Inventory;
use App\Models\ProductVariant;

interface InventoryRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertForVariant(ProductVariant $variant, array $data): Inventory;
}
