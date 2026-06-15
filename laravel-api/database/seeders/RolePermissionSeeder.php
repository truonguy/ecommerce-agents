<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Guard cho RBAC của CRM (chỉ Employee dùng roles/permissions).
     */
    private const GUARD = 'employee';

    private const PERMISSIONS = [
        'manage_product',
        'manage_order',
        'manage_customer',
        'manage_employee',
        'system_config',
    ];

    private const EMPLOYEE_PERMISSIONS = [
        'manage_product',
        'manage_order',
        'manage_customer',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);
        }

        $employee = Role::firstOrCreate(['name' => 'employee', 'guard_name' => self::GUARD]);
        $employee->syncPermissions(self::EMPLOYEE_PERMISSIONS);

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => self::GUARD]);
        $admin->syncPermissions(self::PERMISSIONS);
    }
}
