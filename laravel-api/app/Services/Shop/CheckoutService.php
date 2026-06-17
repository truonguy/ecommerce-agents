<?php

namespace App\Services\Shop;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Services\Order\InventoryReservationService;
use App\Services\Order\OrderService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CheckoutService
{
    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly InventoryReservationService $reservation,
        private readonly OrderService $orders,
    ) {}

    /**
     * Checkout trong 1 transaction: validate → reserve (lock) → snapshot giá → tạo order PENDING → clear cart.
     * Thiếu tồn ở bất kỳ item nào → InsufficientStockException → rollback toàn bộ.
     *
     * @param  array{recipient_name: string, recipient_phone: string, shipping_address: string}  $shipping
     */
    public function checkout(Customer $customer, array $shipping, ?string $idempotencyKey = null): Order
    {
        return DB::transaction(function () use ($customer, $shipping, $idempotencyKey) {
            $cart = $this->carts->activeCartFor($customer);
            $cart->load('items.variant.product');

            if ($cart->items->isEmpty()) {
                throw new UnprocessableEntityHttpException('Cart is empty.');
            }

            $lineItems = [];
            $total = 0.0;

            foreach ($cart->items as $item) {
                $this->reservation->reserve($item->variant, $item->quantity);

                $unitPrice = (float) $item->variant->price;
                $lineTotal = round($unitPrice * $item->quantity, 2);
                $total += $lineTotal;

                $lineItems[] = [
                    'product_variant_id' => $item->variant->id,
                    'product_name' => $item->variant->product->name,
                    'sku' => $item->variant->sku,
                    'unit_price' => $unitPrice,
                    'quantity' => $item->quantity,
                    'line_total' => $lineTotal,
                ];
            }

            $order = $this->orders->create([
                'customer_id' => $customer->id,
                'status' => OrderStatus::PENDING->value,
                'total' => round($total, 2),
                'idempotency_key' => $idempotencyKey,
                'recipient_name' => $shipping['recipient_name'],
                'recipient_phone' => $shipping['recipient_phone'],
                'shipping_address' => $shipping['shipping_address'],
            ], $lineItems);

            $cart->items()->delete();

            return $order;
        });
    }
}
