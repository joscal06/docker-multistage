<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_product_catalog_is_public(): void
    {
        Product::factory()->count(3)->create();

        $this->getJson('/api/products')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_a_single_product_can_be_viewed_publicly(): void
    {
        $product = Product::factory()->create();

        $this->getJson("/api/products/{$product->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_viewing_a_missing_product_returns_a_descriptive_404(): void
    {
        $this->getJson('/api/products/999999')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Producto no encontrado.');
    }

    public function test_creating_a_product_requires_authentication(): void
    {
        $this->postJson('/api/products', ['sku' => 'X', 'name' => 'X', 'price' => 1, 'stock' => 1])
            ->assertStatus(401);
    }

    public function test_an_authenticated_user_can_create_a_product(): void
    {
        $token = $this->authToken();

        $response = $this->withJwt($token)
            ->postJson('/api/products', [
                'sku' => 'ELEC-9999',
                'name' => 'Producto de prueba',
                'price' => 19.99,
                'stock' => 10,
            ]);

        $response->assertStatus(201)->assertJsonPath('data.is_active', true);
        $this->assertDatabaseHas('products', ['sku' => 'ELEC-9999']);
    }

    public function test_creating_a_product_validates_required_fields(): void
    {
        $token = $this->authToken();

        $this->withJwt($token)
            ->postJson('/api/products', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sku', 'name', 'price', 'stock']);
    }

    public function test_a_product_can_be_updated(): void
    {
        $token = $this->authToken();
        $product = Product::factory()->create(['price' => 10]);

        $this->withJwt($token)
            ->putJson("/api/products/{$product->id}", ['price' => 25.50])
            ->assertStatus(200)
            ->assertJsonPath('data.price', 25.5);
    }

    public function test_a_product_with_orders_cannot_be_deleted(): void
    {
        $token = $this->authToken();
        $product = Product::factory()->create();
        $product->orderItems()->create([
            'order_id' => Order::factory()->create()->id,
            'quantity' => 1,
            'unit_price' => $product->price,
            'subtotal' => $product->price,
        ]);

        $this->withJwt($token)
            ->deleteJson("/api/products/{$product->id}")
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    protected function authToken(): string
    {
        $user = User::factory()->create();

        return auth('api')->login($user);
    }
}
