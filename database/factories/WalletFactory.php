<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'user_id'  => User::factory(),
            'balance'  => $this->faker->randomFloat(2, 0, 10000),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP']),
        ];
    }

    /**
     * State: wallet with a zero balance (useful for testing deposits/transfers).
     */
    public function empty(): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => '0.00',
        ]);
    }

    /**
     * State: wallet with a specific balance.
     */
    public function withBalance(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => $amount,
        ]);
    }
}
