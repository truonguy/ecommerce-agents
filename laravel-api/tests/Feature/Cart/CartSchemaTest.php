<?php

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CartSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('carts', ['id', 'customer_id', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasColumns('cart_items', ['id', 'cart_id', 'product_variant_id', 'quantity']));
    }

    public function test_one_active_cart_per_customer(): void
    {
        $customer = Customer::factory()->create();
        Cart::factory()->create(['customer_id' => $customer->id]);

        $this->expectException(QueryException::class);
        Cart::factory()->create(['customer_id' => $customer->id]);
    }

    public function test_cart_variant_pair_is_unique(): void
    {
        $cart = Cart::factory()->create();
        $variant = ProductVariant::factory()->create();
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id]);

        $this->expectException(QueryException::class);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id]);
    }

    public function test_relationships(): void
    {
        $customer = Customer::factory()->create();
        $cart = Cart::factory()->create(['customer_id' => $customer->id]);
        $variant = ProductVariant::factory()->create();
        $item = CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id]);

        $this->assertTrue($cart->customer->is($customer));
        $this->assertTrue($cart->items->first()->is($item));
        $this->assertTrue($item->cart->is($cart));
        $this->assertTrue($item->variant->is($variant));
    }
}
