<?php

namespace Tests\Feature\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Services\Payment\Gateways\VnpayAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payment.vnpay.secret' => 'sekret']);
    }

    private function seedScenario(string $ref, OrderStatus $orderStatus = OrderStatus::PENDING): array
    {
        $order = Order::factory()->create(['status' => $orderStatus]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'method' => PaymentMethod::VNPAY,
            'gateway' => 'vnpay',
            'status' => PaymentStatus::PROCESSING,
        ]);
        PaymentAttempt::factory()->create([
            'payment_id' => $payment->id,
            'provider_txn_ref' => $ref,
            'status' => PaymentStatus::PROCESSING,
        ]);

        return [$order, $payment];
    }

    private function signedPayload(string $ref, string $code): array
    {
        $params = ['vnp_TxnRef' => $ref, 'vnp_ResponseCode' => $code, 'vnp_Amount' => '5000000'];

        return $params + ['vnp_SecureHash' => VnpayAdapter::hash($params, 'sekret')];
    }

    public function test_success_webhook_confirms_order(): void
    {
        [$order] = $this->seedScenario('VNP-S1');

        $this->postJson('/api/payment/webhook?gateway=vnpay', $this->signedPayload('VNP-S1', '00'))->assertOk();

        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
    }

    public function test_failed_webhook_keeps_order_pending(): void
    {
        [$order] = $this->seedScenario('VNP-F1');

        $this->postJson('/api/payment/webhook?gateway=vnpay', $this->signedPayload('VNP-F1', '24'))->assertOk();

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_duplicate_success_does_not_double_confirm(): void
    {
        [$order] = $this->seedScenario('VNP-D1');
        $payload = $this->signedPayload('VNP-D1', '00');

        $this->postJson('/api/payment/webhook?gateway=vnpay', $payload)->assertOk();
        $this->postJson('/api/payment/webhook?gateway=vnpay', $payload)->assertOk(); // idempotent

        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
    }

    public function test_success_when_order_already_confirmed_is_safe(): void
    {
        [$order] = $this->seedScenario('VNP-A1', OrderStatus::CONFIRMED);

        $this->postJson('/api/payment/webhook?gateway=vnpay', $this->signedPayload('VNP-A1', '00'))->assertOk();

        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
        $this->assertSame(PaymentStatus::SUCCESS, Payment::where('order_id', $order->id)->first()->status);
    }
}
