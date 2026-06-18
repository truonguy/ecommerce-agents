<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'method' => PaymentMethod::VNPAY,
            'gateway' => 'vnpay',
            'amount' => fake()->numberBetween(10_000, 5_000_000),
            'status' => PaymentStatus::PENDING,
        ];
    }

    public function status(PaymentStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function cod(): static
    {
        return $this->state(fn () => ['method' => PaymentMethod::COD, 'gateway' => 'cod']);
    }
}
