<?php

namespace App\Services\Crm;

use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Repositories\Contracts\InventoryRepositoryInterface;

class InventoryService
{
    public function __construct(
        private readonly InventoryRepositoryInterface $inventories,
    ) {}

    /**
     * @param  array{available_stock: int, reserved_stock: int}  $data
     */
    public function set(ProductVariant $variant, array $data): Inventory
    {
        return $this->inventories->upsertForVariant($variant, $data);
    }
}
