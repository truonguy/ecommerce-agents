<?php

namespace Tests\Feature\Payment;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Services\Payment\Gateways\VnpayAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payment.vnpay.secret' => 'sekret']);
    }

    private function processingPayment(string $ref): Payment
    {
        $payment = Payment::factory()->create([
            'method' => PaymentMethod::VNPAY,
            'gateway' => 'vnpay',
            'status' => PaymentStatus::PROCESSING,
        ]);
        PaymentAttempt::factory()->create([
            'payment_id' => $payment->id,
            'provider_txn_ref' => $ref,
            'status' => PaymentStatus::PROCESSING,
        ]);

        return $payment;
    }

    private function signedPayload(string $ref, string $code): array
    {
        $params = ['vnp_TxnRef' => $ref, 'vnp_ResponseCode' => $code, 'vnp_Amount' => '5000000'];

        return $params + ['vnp_SecureHash' => VnpayAdapter::hash($params, 'sekret')];
    }

    public function test_valid_webhook_marks_payment_success(): void
    {
        $payment = $this->processingPayment('VNP-100');

        $this->postJson('/api/payment/webhook?gateway=vnpay', $this->signedPayload('VNP-100', '00'))
            ->assertOk();

        $this->assertSame(PaymentStatus::SUCCESS, $payment->fresh()->status);
        $this->assertDatabaseHas('payment_attempts', ['provider_txn_ref' => 'VNP-100', 'status' => PaymentStatus::SUCCESS->value]);
    }

    public function test_failed_response_code_marks_payment_failed(): void
    {
        $payment = $this->processingPayment('VNP-200');

        $this->postJson('/api/payment/webhook?gateway=vnpay', $this->signedPayload('VNP-200', '24'))
            ->assertOk();

        $this->assertSame(PaymentStatus::FAILED, $payment->fresh()->status);
    }

    public function test_invalid_signature_returns_400_and_no_change(): void
    {
        $payment = $this->processingPayment('VNP-300');

        $payload = ['vnp_TxnRef' => 'VNP-300', 'vnp_ResponseCode' => '00', 'vnp_SecureHash' => 'tampered'];

        $this->postJson('/api/payment/webhook?gateway=vnpay', $payload)->assertStatus(400);

        $this->assertSame(PaymentStatus::PROCESSING, $payment->fresh()->status);
    }

    public function test_duplicate_webhook_is_idempotent(): void
    {
        $payment = $this->processingPayment('VNP-400');
        $payload = $this->signedPayload('VNP-400', '00');

        $this->postJson('/api/payment/webhook?gateway=vnpay', $payload)->assertOk();
        $this->postJson('/api/payment/webhook?gateway=vnpay', $payload)->assertOk(); // lần 2

        $this->assertSame(PaymentStatus::SUCCESS, $payment->fresh()->status);
        // chỉ 1 attempt, không nhân đôi
        $this->assertSame(1, $payment->attempts()->count());
    }

    public function test_unknown_ref_returns_404(): void
    {
        $this->postJson('/api/payment/webhook?gateway=vnpay', $this->signedPayload('VNP-NOPE', '00'))
            ->assertNotFound();
    }
}
