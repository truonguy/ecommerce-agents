<?php

namespace Tests\Feature\Auth;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_employee_role_has_operational_permissions_only(): void
    {
        $employee = Role::where('name', 'employee')->where('guard_name', 'employee')->firstOrFail();

        $this->assertTrue($employee->hasPermissionTo('manage_product'));
        $this->assertTrue($employee->hasPermissionTo('manage_order'));
        $this->assertTrue($employee->hasPermissionTo('manage_customer'));
        $this->assertTrue($employee->hasPermissionTo('manage_inventory'));
        $this->assertFalse($employee->hasPermissionTo('manage_employee'));
        $this->assertFalse($employee->hasPermissionTo('system_config'));
        $this->assertFalse($employee->hasPermissionTo('publish_product'));
    }

    public function test_admin_role_has_all_permissions(): void
    {
        $admin = Role::where('name', 'admin')->where('guard_name', 'employee')->firstOrFail();

        foreach ([
            'manage_product', 'manage_order', 'manage_customer', 'manage_employee', 'system_config',
            'publish_product', 'manage_inventory',
        ] as $perm) {
            $this->assertTrue($admin->hasPermissionTo($perm), "admin missing $perm");
        }
    }
}
