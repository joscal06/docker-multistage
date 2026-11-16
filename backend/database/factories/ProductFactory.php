<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => 'SKU-'.fake()->unique()->numerify('######'),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'category' => fake()->randomElement(['Laptops', 'Celulares', 'Accesorios', 'Audio']),
            'price' => fake()->randomFloat(2, 5, 1500),
            'stock' => fake()->numberBetween(0, 100),
            'image_url' => 'https://picsum.photos/seed/'.fake()->uuid().'/600/400',
            'is_active' => true,
        ];
    }
}
