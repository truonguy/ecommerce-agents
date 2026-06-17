<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\UpdateInventoryRequest;
use App\Models\ProductVariant;
use App\Services\Crm\InventoryService;
use Illuminate\Http\JsonResponse;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventories,
    ) {}

    public function update(UpdateInventoryRequest $request, ProductVariant $variant): JsonResponse
    {
        $inventory = $this->inventories->set($variant, $request->validated());

        return response()->json($inventory);
    }
}
