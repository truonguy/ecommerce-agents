<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_token_can_access_customer_route(): void
    {
        $customer = Customer::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/shop/ping')
            ->assertOk()
            ->assertJson(['type' => 'customer', 'id' => $customer->id]);
    }

    public function test_employee_token_cannot_access_customer_route(): void
    {
        $employee = Employee::factory()->create();
        $token = $employee->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/shop/ping')->assertUnauthorized();
    }

    public function test_employee_token_can_access_crm_route(): void
    {
        $employee = Employee::factory()->create();
        $token = $employee->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/crm/ping')
            ->assertOk()
            ->assertJson(['type' => 'employee', 'id' => $employee->id]);
    }

    public function test_customer_token_cannot_access_crm_route(): void
    {
        $customer = Customer::factory()->create();
        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/crm/ping')->assertUnauthorized();
    }

    public function test_no_token_is_unauthorized(): void
    {
        $this->getJson('/api/shop/ping')->assertUnauthorized();
        $this->getJson('/api/crm/ping')->assertUnauthorized();
    }
}
