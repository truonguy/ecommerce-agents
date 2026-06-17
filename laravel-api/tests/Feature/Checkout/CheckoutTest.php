<?php

namespace Tests\Feature\Checkout;

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function variant(float $price, int $available): ProductVariant
    {
        $product = Product::factory()->published()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => $price]);
        Inventory::factory()->create(['product_variant_id' => $variant->id, 'available_stock' => $available, 'reserved_stock' => 0]);

        return $variant;
    }

    private function cartFor(Customer $customer): Cart
    {
        return Cart::factory()->create(['customer_id' => $customer->id]);
    }

    private function shipping(): array
    {
        return [
            'recipient_name' => 'Jane Doe',
            'recipient_phone' => '0900000000',
            'shipping_address' => '123 Main St',
        ];
    }

    public function test_checkout_creates_order_reserves_and_clears_cart(): void
    {
        $customer = Customer::factory()->create();
        $cart = $this->cartFor($customer);
        $variant = $this->variant(10, 5);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 2]);

        $res = $this->withToken($customer->createToken('shop')->plainTextToken)
            ->postJson('/api/checkout', $this->shipping())
            ->assertCreated()
            ->assertJson(['status' => OrderStatus::PENDING->value]);

        $this->assertEquals(20, $res->json('total'));

        $this->assertDatabaseHas('order_items', [
            'product_variant_id' => $variant->id, 'unit_price' => '10.00', 'quantity' => 2, 'line_total' => '20.00',
        ]);
        $this->assertDatabaseHas('inventories', [
            'product_variant_id' => $variant->id, 'available_stock' => 3, 'reserved_stock' => 2,
        ]);
        $this->assertSame(0, $cart->items()->count()); // cart cleared
    }

    public function test_checkout_empty_cart_fails(): void
    {
        $customer = Customer::factory()->create();
        $this->cartFor($customer);

        $this->withToken($customer->createToken('shop')->plainTextToken)
            ->postJson('/api/checkout', $this->shipping())
            ->assertStatus(422);
    }

    public function test_insufficient_stock_rolls_back_everything(): void
    {
        $customer = Customer::factory()->create();
        $cart = $this->cartFor($customer);
        $ok = $this->variant(10, 10);
        $low = $this->variant(20, 1);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $ok->id, 'quantity' => 1]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $low->id, 'quantity' => 5]);

        $this->withToken($customer->createToken('shop')->plainTextToken)
            ->postJson('/api/checkout', $this->shipping())
            ->assertStatus(422);

        // không tạo order; inventory cả hai giữ nguyên; cart không bị clear
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('inventories', ['product_variant_id' => $ok->id, 'available_stock' => 10, 'reserved_stock' => 0]);
        $this->assertDatabaseHas('inventories', ['product_variant_id' => $low->id, 'available_stock' => 1, 'reserved_stock' => 0]);
        $this->assertSame(2, $cart->items()->count());
    }

    public function test_price_is_snapshotted(): void
    {
        $customer = Customer::factory()->create();
        $cart = $this->cartFor($customer);
        $variant = $this->variant(10, 5);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->withToken($customer->createToken('shop')->plainTextToken)
            ->postJson('/api/checkout', $this->shipping())->assertCreated();

        $variant->update(['price' => 999]); // đổi giá sau khi đặt

        $this->assertDatabaseHas('order_items', ['product_variant_id' => $variant->id, 'unit_price' => '10.00']);
    }

    public function test_shipping_fields_required(): void
    {
        $customer = Customer::factory()->create();
        $cart = $this->cartFor($customer);
        $variant = $this->variant(10, 5);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->withToken($customer->createToken('shop')->plainTextToken)
            ->postJson('/api/checkout', [])
            ->assertStatus(422)->assertJsonValidationErrors(['recipient_name', 'recipient_phone', 'shipping_address']);
    }

    public function test_only_customer_can_checkout(): void
    {
        $token = Employee::factory()->create()->createToken('crm')->plainTextToken;

        $this->withToken($token)->postJson('/api/checkout', $this->shipping())->assertUnauthorized();
    }
}
