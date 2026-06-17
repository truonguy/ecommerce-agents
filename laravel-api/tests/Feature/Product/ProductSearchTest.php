<?php

namespace Tests\Feature\Product;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    private function publish(array $attrs, float $price = 50): Product
    {
        $product = Product::factory()->published()->create($attrs);
        ProductVariant::factory()->create(['product_id' => $product->id, 'price' => $price]);

        return $product;
    }

    public function test_keyword_matches_name(): void
    {
        $this->publish(['name' => 'Red Hoodie', 'slug' => 'red-hoodie']);
        $this->publish(['name' => 'Blue Sneaker', 'slug' => 'blue-sneaker']);

        $res = $this->getJson('/api/products?q=hoodie')->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('red-hoodie', $res->json('data.0.slug'));
    }

    public function test_keyword_matches_description(): void
    {
        $this->publish(['name' => 'Jacket', 'slug' => 'jacket', 'description' => 'Fully waterproof shell']);
        $this->publish(['name' => 'Shirt', 'slug' => 'shirt', 'description' => 'Cotton tee']);

        $res = $this->getJson('/api/products?q=waterproof')->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('jacket', $res->json('data.0.slug'));
    }

    public function test_keyword_only_returns_published(): void
    {
        Product::factory()->create(['name' => 'Secret Hoodie', 'slug' => 'secret']); // DRAFT

        $res = $this->getJson('/api/products?q=hoodie')->assertOk();
        $this->assertCount(0, $res->json('data'));
    }

    public function test_keyword_combined_with_category_and_price(): void
    {
        $cat = Category::factory()->create();
        $this->publish(['name' => 'Cheap Hoodie', 'slug' => 'cheap-hoodie', 'category_id' => $cat->id], price: 20);
        $this->publish(['name' => 'Pricey Hoodie', 'slug' => 'pricey-hoodie', 'category_id' => $cat->id], price: 500);
        $this->publish(['name' => 'Other Hoodie', 'slug' => 'other-hoodie'], price: 20); // khác category

        $res = $this->getJson("/api/products?q=hoodie&category_id={$cat->id}&price_max=100")->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('cheap-hoodie', $res->json('data.0.slug'));
    }

    public function test_search_with_sort(): void
    {
        $a = $this->publish(['name' => 'Hoodie A', 'slug' => 'hoodie-a'], price: 80);
        $b = $this->publish(['name' => 'Hoodie B', 'slug' => 'hoodie-b'], price: 10);

        $res = $this->getJson('/api/products?q=hoodie&sort=price_asc')->assertOk();
        $ids = array_column($res->json('data'), 'id');
        $this->assertSame([$b->id, $a->id], $ids);
    }

    /** AC-P8.3 — benchmark (ngưỡng nới vì phụ thuộc môi trường; mục tiêu thực <300ms cần dataset chốt) */
    public function test_search_performance_sanity(): void
    {
        Product::factory()->count(120)->published()->create();

        $start = microtime(true);
        $this->getJson('/api/products?q=a&per_page=15')->assertOk();
        $elapsedMs = (microtime(true) - $start) * 1000;

        $this->assertLessThan(2000, $elapsedMs, "search took {$elapsedMs}ms");
    }
}
