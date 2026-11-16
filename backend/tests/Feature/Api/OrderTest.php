<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_order_requires_authentication(): void
    {
        $product = Product::factory()->create();

        $this->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertStatus(401);
    }

    public function test_an_authenticated_user_can_create_an_order_and_stock_is_decremented(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);

        $response = $this->withJwt($token)
            ->postJson('/api/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 3]],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total', 300)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.items.0.quantity', 3);

        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'total' => 300]);
        $this->assertEquals(7, $product->fresh()->stock);
    }

    public function test_an_order_fails_with_409_when_stock_is_insufficient(): void
    {
        [, $token] = $this->authenticatedUser();
        $product = Product::factory()->create(['stock' => 2]);

        $response = $this->withJwt($token)
            ->postJson('/api/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 5]],
            ]);

        $response->assertStatus(409)->assertJsonPath('success', false);
        $this->assertEquals(2, $product->fresh()->stock);
    }

    public function test_order_creation_validates_items(): void
    {
        [, $token] = $this->authenticatedUser();

        $this->withJwt($token)
            ->postJson('/api/orders', ['items' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_a_user_can_only_view_their_own_order(): void
    {
        [$owner, $ownerToken] = $this->authenticatedUser();
        [, $otherToken] = $this->authenticatedUser();
        $product = Product::factory()->create();

        $orderId = $this->withJwt($ownerToken)
            ->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->json('data.id');

        $this->withJwt($ownerToken)
            ->getJson("/api/orders/{$orderId}")
            ->assertStatus(200);

        $this->withJwt($otherToken)
            ->getJson("/api/orders/{$orderId}")
            ->assertStatus(403);
    }

    public function test_the_order_history_only_lists_the_authenticated_users_orders(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $product = Product::factory()->create();

        $this->withJwt($token)
            ->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]]);

        [, $otherToken] = $this->authenticatedUser();
        $this->withJwt($otherToken)
            ->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]]);

        $this->withJwt($token)
            ->getJson('/api/orders')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $user->id);
    }

    /**
     * @return array{0: User, 1: string}
     */
    protected function authenticatedUser(): array
    {
        $user = User::factory()->create();

        return [$user, auth('api')->login($user)];
    }
}
