<?php

namespace Tests\Feature\Payment;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('payments', ['id', 'order_id', 'method', 'gateway', 'amount', 'status']));
        $this->assertTrue(Schema::hasColumns('payment_attempts', ['id', 'payment_id', 'provider_txn_ref', 'status', 'raw_payload']));
    }

    public function test_one_payment_per_order(): void
    {
        $order = Order::factory()->create();
        Payment::factory()->create(['order_id' => $order->id]);

        $this->expectException(QueryException::class);
        Payment::factory()->create(['order_id' => $order->id]);
    }

    public function test_payment_has_many_attempts(): void
    {
        $payment = Payment::factory()->create();
        $a = PaymentAttempt::factory()->create(['payment_id' => $payment->id]);
        PaymentAttempt::factory()->create(['payment_id' => $payment->id]);

        $this->assertSame(2, $payment->attempts()->count());
        $this->assertTrue($a->payment->is($payment));
    }

    public function test_provider_txn_ref_is_unique(): void
    {
        PaymentAttempt::factory()->create(['provider_txn_ref' => 'TXN-1']);

        $this->expectException(QueryException::class);
        PaymentAttempt::factory()->create(['provider_txn_ref' => 'TXN-1']);
    }

    public function test_enum_and_payload_casts(): void
    {
        $payment = Payment::factory()->create(['status' => PaymentStatus::PENDING, 'method' => PaymentMethod::VNPAY]);
        $this->assertInstanceOf(PaymentStatus::class, $payment->status);
        $this->assertInstanceOf(PaymentMethod::class, $payment->method);

        $attempt = PaymentAttempt::factory()->create(['raw_payload' => ['a' => 1]]);
        $this->assertSame(['a' => 1], $attempt->fresh()->raw_payload);
    }

    public function test_order_relationship(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->create(['order_id' => $order->id]);

        $this->assertTrue($payment->order->is($order));
    }
}
