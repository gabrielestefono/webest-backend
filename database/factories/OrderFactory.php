<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::query()->firstOrCreate(
                ['name' => 'Produto padrão'],
                [
                    'description' => 'Produto criado automaticamente para seeds e testes.',
                    'base_price' => 0,
                    'active' => true,
                ]
            )->id,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(array_keys(Order::STATUSES)),
        ];
    }
}
