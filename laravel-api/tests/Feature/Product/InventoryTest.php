<?php

namespace Tests\Feature\Product;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\ProductVariant;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function token(?string $role = 'employee'): string
    {
        $employee = Employee::factory()->create();
        if ($role !== null) {
            $employee->assignRole($role);
        }

        return $employee->createToken('crm')->plainTextToken;
    }

    public function test_employee_can_update_inventory(): void
    {
        $variant = ProductVariant::factory()->create();
        Inventory::factory()->create(['product_variant_id' => $variant->id, 'available_stock' => 1]);

        $this->withToken($this->token())
            ->putJson("/api/crm/variants/{$variant->id}/inventory", [
                'available_stock' => 10,
                'reserved_stock' => 2,
            ])->assertOk();

        $this->assertDatabaseHas('inventories', [
            'product_variant_id' => $variant->id,
            'available_stock' => 10,
            'reserved_stock' => 2,
        ]);
    }

    public function test_inventory_is_created_if_missing(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->withToken($this->token())
            ->putJson("/api/crm/variants/{$variant->id}/inventory", [
                'available_stock' => 5,
                'reserved_stock' => 0,
            ])->assertOk();

        $this->assertDatabaseHas('inventories', ['product_variant_id' => $variant->id, 'available_stock' => 5]);
    }

    public function test_available_stock_cannot_be_negative(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->withToken($this->token())
            ->putJson("/api/crm/variants/{$variant->id}/inventory", [
                'available_stock' => -1,
                'reserved_stock' => 0,
            ])->assertStatus(422)->assertJsonValidationErrors(['available_stock']);
    }

    public function test_reserved_stock_cannot_be_negative(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->withToken($this->token())
            ->putJson("/api/crm/variants/{$variant->id}/inventory", [
                'available_stock' => 1,
                'reserved_stock' => -5,
            ])->assertStatus(422)->assertJsonValidationErrors(['reserved_stock']);
    }

    public function test_requires_manage_inventory_permission(): void
    {
        $variant = ProductVariant::factory()->create();

        // employee không gán role → không có manage_inventory
        $this->withToken($this->token(null))
            ->putJson("/api/crm/variants/{$variant->id}/inventory", [
                'available_stock' => 1, 'reserved_stock' => 0,
            ])->assertForbidden();
    }

    public function test_customer_cannot_access(): void
    {
        $variant = ProductVariant::factory()->create();
        $token = Customer::factory()->create()->createToken('shop')->plainTextToken;

        $this->withToken($token)
            ->putJson("/api/crm/variants/{$variant->id}/inventory", [
                'available_stock' => 1, 'reserved_stock' => 0,
            ])->assertUnauthorized();
    }
}
