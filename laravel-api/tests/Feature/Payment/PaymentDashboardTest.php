<?php

namespace Tests\Feature\Payment;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config(['payment.vnpay.secret' => 'sekret']);
    }

    private function token(?string $role = 'employee'): string
    {
        $employee = Employee::factory()->create();
        if ($role !== null) {
            $employee->assignRole($role);
        }

        return $employee->createToken('crm')->plainTextToken;
    }

    public function test_list_payments(): void
    {
        Payment::factory()->count(3)->create();

        $this->withToken($this->token())->getJson('/api/crm/payments')
            ->assertOk()->assertJsonStructure(['data', 'meta']);
    }

    public function test_filter_by_status(): void
    {
        Payment::factory()->status(PaymentStatus::SUCCESS)->create();
        Payment::factory()->status(PaymentStatus::SUCCESS)->create();
        Payment::factory()->status(PaymentStatus::FAILED)->create();

        $res = $this->withToken($this->token())->getJson('/api/crm/payments?status=SUCCESS')->assertOk();
        $this->assertCount(2, $res->json('data'));
    }

    public function test_detail_includes_attempts(): void
    {
        $payment = Payment::factory()->create();
        PaymentAttempt::factory()->count(2)->create(['payment_id' => $payment->id]);

        $this->withToken($this->token())->getJson("/api/crm/payments/{$payment->id}")
            ->assertOk()->assertJson(['id' => $payment->id])->assertJsonCount(2, 'attempts');
    }

    public function test_retry_failed_payment_creates_new_attempt(): void
    {
        $payment = Payment::factory()->status(PaymentStatus::FAILED)->create(['method' => PaymentMethod::VNPAY, 'gateway' => 'vnpay']);

        $res = $this->withToken($this->token())
            ->postJson("/api/crm/payments/{$payment->id}/retry")->assertOk();

        $this->assertSame(PaymentStatus::PROCESSING, $payment->fresh()->status);
        $this->assertSame(1, $payment->attempts()->count());
        $this->assertStringContainsString('vnp_SecureHash', $res->json('payment_url'));
    }

    public function test_cannot_retry_successful_payment(): void
    {
        $payment = Payment::factory()->status(PaymentStatus::SUCCESS)->create();

        $this->withToken($this->token())
            ->postJson("/api/crm/payments/{$payment->id}/retry")->assertStatus(422);
    }

    public function test_customer_cannot_access(): void
    {
        $payment = Payment::factory()->create();
        $token = Customer::factory()->create()->createToken('shop')->plainTextToken;

        $this->withToken($token)->getJson('/api/crm/payments')->assertUnauthorized();
        $this->withToken($token)->postJson("/api/crm/payments/{$payment->id}/retry")->assertUnauthorized();
    }

    public function test_employee_without_manage_order_forbidden(): void
    {
        $this->withToken($this->token(null))->getJson('/api/crm/payments')->assertForbidden();
    }
}
