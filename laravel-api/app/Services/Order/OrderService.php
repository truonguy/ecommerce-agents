<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
    ) {}

    /**
     * Tạo order + order_items (snapshot). Gọi trong transaction của CheckoutService.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $items
     */
    public function create(array $attributes, array $items): Order
    {
        $order = $this->orders->create($attributes);
        $order->items()->createMany($items);

        return $order->load('items');
    }
}
