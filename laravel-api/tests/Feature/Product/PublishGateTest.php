<?php

namespace Tests\Feature\Product;

use App\Enums\PublishStatus;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function token(?string $role): string
    {
        $employee = Employee::factory()->create();
        if ($role !== null) {
            $employee->assignRole($role);
        }

        return $employee->createToken('crm')->plainTextToken;
    }

    /** AC-P10.1 — admin publish được */
    public function test_admin_can_publish_product(): void
    {
        $product = Product::factory()->create(); // DRAFT

        $this->withToken($this->token('admin'))
            ->postJson("/api/crm/products/{$product->id}/publish")
            ->assertOk()->assertJson(['publish_status' => PublishStatus::PUBLISHED->value]);

        $this->assertSame(PublishStatus::PUBLISHED, $product->fresh()->publish_status);
    }

    /** AC-P10.1 — employee KHÔNG publish được → 403 */
    public function test_employee_cannot_publish_product(): void
    {
        $product = Product::factory()->create();

        $this->withToken($this->token('employee'))
            ->postJson("/api/crm/products/{$product->id}/publish")
            ->assertForbidden();

        $this->assertSame(PublishStatus::DRAFT, $product->fresh()->publish_status);
    }

    public function test_admin_can_unpublish_product(): void
    {
        $product = Product::factory()->published()->create();

        $this->withToken($this->token('admin'))
            ->postJson("/api/crm/products/{$product->id}/unpublish")
            ->assertOk()->assertJson(['publish_status' => PublishStatus::DRAFT->value]);
    }

    public function test_customer_cannot_publish(): void
    {
        $product = Product::factory()->create();
        $token = Customer::factory()->create()->createToken('shop')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/crm/products/{$product->id}/publish")
            ->assertUnauthorized();
    }
}
