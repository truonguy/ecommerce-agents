<?php

namespace Tests\Feature\Product;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopDetailTest extends TestCase
{
    use RefreshDatabase;

    private function publishedProduct(array $attrs = []): Product
    {
        $product = Product::factory()->published()->create($attrs);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        Inventory::factory()->create(['product_variant_id' => $variant->id, 'available_stock' => 7]);

        return $product;
    }

    public function test_show_published_product_with_relations(): void
    {
        $product = $this->publishedProduct(['slug' => 'cool-thing']);

        $this->getJson('/api/products/cool-thing')
            ->assertOk()
            ->assertJson(['id' => $product->id, 'slug' => 'cool-thing'])
            ->assertJsonStructure([
                'id', 'slug', 'category' => ['id', 'name'],
                'variants' => [['id', 'sku', 'price', 'inventory' => ['available_stock']]],
            ]);
    }

    public function test_public_no_token_required(): void
    {
        $this->publishedProduct(['slug' => 'public-thing']);
        $this->getJson('/api/products/public-thing')->assertOk();
    }

    public function test_draft_product_returns_404(): void
    {
        Product::factory()->create(['slug' => 'draft-thing']);
        $this->getJson('/api/products/draft-thing')->assertNotFound();
    }

    public function test_archived_product_returns_404(): void
    {
        Product::factory()->archived()->create(['slug' => 'archived-thing']);
        $this->getJson('/api/products/archived-thing')->assertNotFound();
    }

    public function test_soft_deleted_product_returns_404(): void
    {
        $product = $this->publishedProduct(['slug' => 'gone-thing']);
        $product->delete();

        $this->getJson('/api/products/gone-thing')->assertNotFound();
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->getJson('/api/products/does-not-exist')->assertNotFound();
    }
}
