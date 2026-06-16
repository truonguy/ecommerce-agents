<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use App\Models\Employee;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function tokenFor(string $role): string
    {
        $employee = Employee::factory()->create();
        $employee->assignRole($role);

        return $employee->createToken('crm')->plainTextToken;
    }

    /** AC-06.1 — employee có manage_product */
    public function test_employee_can_access_manage_product(): void
    {
        $this->withToken($this->tokenFor('employee'))
            ->getJson('/api/crm/products')->assertOk();
    }

    public function test_employee_can_access_manage_order_and_customer(): void
    {
        $token = $this->tokenFor('employee');
        $this->withToken($token)->getJson('/api/crm/orders')->assertOk();
        $this->withToken($token)->getJson('/api/crm/customers')->assertOk();
    }

    /** AC-06.2 — employee KHÔNG có manage_employee / system_config */
    public function test_employee_cannot_access_manage_employee(): void
    {
        $this->withToken($this->tokenFor('employee'))
            ->getJson('/api/crm/employees')->assertForbidden();
    }

    public function test_employee_cannot_access_system_config(): void
    {
        $this->withToken($this->tokenFor('employee'))
            ->getJson('/api/crm/system-config')->assertForbidden();
    }

    /** AC-06.3 — admin có manage_employee + system_config */
    public function test_admin_can_access_manage_employee_and_system_config(): void
    {
        $token = $this->tokenFor('admin');
        $this->withToken($token)->getJson('/api/crm/employees')->assertOk();
        $this->withToken($token)->getJson('/api/crm/system-config')->assertOk();
    }

    /** AC-06.4 — customer token không chạm được CRM */
    public function test_customer_token_cannot_access_crm_endpoints(): void
    {
        $token = Customer::factory()->create()->createToken('shop')->plainTextToken;

        $this->withToken($token)->getJson('/api/crm/products')->assertUnauthorized();
        $this->withToken($token)->getJson('/api/crm/employees')->assertUnauthorized();
    }
}
