<?php

namespace Database\Factories;

use App\Models\Proposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proposal>
 */
class ProposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => null,
            'sender_id' => null,
            'content' => $this->faker->paragraph(),
            'price' => $this->faker->randomFloat(2, 100, 10000),
            'is_accepted' => null,
        ];
    }
}
