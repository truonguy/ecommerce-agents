<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    private function forgetGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /** AC-05.1 — logout revoke token hiện tại */
    public function test_shop_logout_revokes_current_token(): void
    {
        $customer = Customer::factory()->create();
        $token = $customer->createToken('shop')->plainTextToken;

        $this->withToken($token)->postJson('/api/shop/auth/logout')->assertOk();

        $this->assertSame(0, $customer->tokens()->count());

        $this->forgetGuards();
        $this->withToken($token)->getJson('/api/shop/ping')->assertUnauthorized();
    }

    /** AC-05.1 — CRM logout chỉ revoke token đang dùng, token khác còn hiệu lực */
    public function test_crm_logout_revokes_only_current_token(): void
    {
        $employee = Employee::factory()->create();
        $t1 = $employee->createToken('a')->plainTextToken;
        $t2 = $employee->createToken('b')->plainTextToken;

        $this->withToken($t1)->postJson('/api/crm/auth/logout')->assertOk();
        $this->assertSame(1, $employee->tokens()->count());

        $this->forgetGuards();
        $this->withToken($t2)->getJson('/api/crm/ping')->assertOk();

        $this->forgetGuards();
        $this->withToken($t1)->getJson('/api/crm/ping')->assertUnauthorized();
    }

    /** AC-05.2 — logout-all (CRM) revoke toàn bộ device */
    public function test_crm_logout_all_revokes_every_token(): void
    {
        $employee = Employee::factory()->create();
        $t1 = $employee->createToken('a')->plainTextToken;
        $t2 = $employee->createToken('b')->plainTextToken;
        $employee->createToken('c');

        $this->withToken($t1)->postJson('/api/crm/auth/logout-all')->assertOk();
        $this->assertSame(0, $employee->tokens()->count());

        $this->forgetGuards();
        $this->withToken($t2)->getJson('/api/crm/ping')->assertUnauthorized();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/shop/auth/logout')->assertUnauthorized();
        $this->postJson('/api/crm/auth/logout')->assertUnauthorized();
        $this->postJson('/api/crm/auth/logout-all')->assertUnauthorized();
    }
}
