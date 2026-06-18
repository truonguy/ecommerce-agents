<?php

namespace Tests\Feature\Checkout;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function variant(float $price, int $available): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => $price]);
        Inventory::factory()->create(['product_variant_id' => $variant->id, 'available_stock' => $available, 'reserved_stock' => 0]);

        return $variant;
    }

    private function addToCart(Customer $customer, ProductVariant $variant, int $qty = 1): void
    {
        $cart = Cart::firstOrCreate(['customer_id' => $customer->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => $qty]);
    }

    private function shipping(): array
    {
        return ['recipient_name' => 'A', 'recipient_phone' => '0900000000', 'shipping_address' => '1 St'];
    }

    /** AC-C11.1 — cùng Idempotency-Key → 1 order */
    public function test_same_idempotency_key_returns_same_order(): void
    {
        $customer = Customer::factory()->create();
        $variant = $this->variant(10, 10);
        $this->addToCart($customer, $variant, 2);
        $token = $customer->createToken('shop')->plainTextToken;

        $first = $this->withToken($token)->withHeaders(['Idempotency-Key' => 'key-abc'])
            ->postJson('/api/checkout', $this->shipping())->assertCreated();

        // gọi lại cùng key (cart đã rỗng) → trả lại order cũ, không tạo mới
        $second = $this->withToken($token)->withHeaders(['Idempotency-Key' => 'key-abc'])
            ->postJson('/api/checkout', $this->shipping())->assertSuccessful();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('inventories', ['product_variant_id' => $variant->id, 'reserved_stock' => 2]);
    }

    public function test_different_key_creates_new_order(): void
    {
        $customer = Customer::factory()->create();
        $variant = $this->variant(10, 10);
        $token = $customer->createToken('shop')->plainTextToken;

        $this->addToCart($customer, $variant, 1);
        $this->withToken($token)->withHeaders(['Idempotency-Key' => 'k1'])
            ->postJson('/api/checkout', $this->shipping())->assertCreated();

        $this->addToCart($customer, $variant, 1); // cart mới
        $this->withToken($token)->withHeaders(['Idempotency-Key' => 'k2'])
            ->postJson('/api/checkout', $this->shipping())->assertCreated();

        $this->assertDatabaseCount('orders', 2);
    }

    /** AC-C11.2 — không oversell khi tồn = 1 */
    public function test_no_oversell_when_stock_is_one(): void
    {
        $variant = $this->variant(10, 1);

        $c1 = Customer::factory()->create();
        $this->addToCart($c1, $variant, 1);
        $this->withToken($c1->createToken('shop')->plainTextToken)
            ->postJson('/api/checkout', $this->shipping())->assertCreated();

        $c2 = Customer::factory()->create();
        $this->addToCart($c2, $variant, 1);
        $this->withToken($c2->createToken('shop')->plainTextToken)
            ->postJson('/api/checkout', $this->shipping())->assertStatus(422);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('inventories', [
            'product_variant_id' => $variant->id, 'available_stock' => 0, 'reserved_stock' => 1,
        ]);
    }
}
