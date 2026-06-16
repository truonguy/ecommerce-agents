<?php

namespace Tests\Feature\Product;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
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

    public function test_employee_can_list_categories(): void
    {
        Category::factory()->count(3)->create();

        $this->withToken($this->token())->getJson('/api/crm/categories')
            ->assertOk()->assertJsonStructure(['data']);
    }

    public function test_can_create_category_with_auto_slug(): void
    {
        $this->withToken($this->token())
            ->postJson('/api/crm/categories', ['name' => 'Summer Shirts'])
            ->assertCreated()
            ->assertJson(['name' => 'Summer Shirts', 'slug' => 'summer-shirts']);

        $this->assertDatabaseHas('categories', ['slug' => 'summer-shirts']);
    }

    public function test_can_create_nested_category(): void
    {
        $parent = Category::factory()->create();

        $this->withToken($this->token())
            ->postJson('/api/crm/categories', ['name' => 'Child', 'parent_id' => $parent->id])
            ->assertCreated()
            ->assertJson(['parent_id' => $parent->id]);
    }

    public function test_can_update_category(): void
    {
        $category = Category::factory()->create();

        $this->withToken($this->token())
            ->putJson("/api/crm/categories/{$category->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJson(['name' => 'Renamed', 'slug' => 'renamed']);
    }

    public function test_delete_is_soft(): void
    {
        $category = Category::factory()->create();

        $this->withToken($this->token())
            ->deleteJson("/api/crm/categories/{$category->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($category);
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        Category::factory()->create(['slug' => 'shirts', 'name' => 'Shirts']);

        $this->withToken($this->token())
            ->postJson('/api/crm/categories', ['name' => 'Shirts'])
            ->assertStatus(422)->assertJsonValidationErrors(['slug']);
    }

    public function test_validation_requires_name(): void
    {
        $this->withToken($this->token())
            ->postJson('/api/crm/categories', [])
            ->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_customer_token_cannot_access(): void
    {
        $token = Customer::factory()->create()->createToken('shop')->plainTextToken;

        $this->withToken($token)->getJson('/api/crm/categories')->assertUnauthorized();
    }

    public function test_employee_without_permission_forbidden(): void
    {
        // employee không gán role → không có manage_product
        $this->withToken($this->token(null))
            ->getJson('/api/crm/categories')->assertForbidden();
    }
}
