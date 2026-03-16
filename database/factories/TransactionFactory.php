<?php

namespace Database\Factories;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'source_wallet_id'      => null,
            'destination_wallet_id' => Wallet::factory(),
            'amount'                => $this->faker->randomFloat(2, 1, 1000),
            'type'                  => TransactionType::Deposit,
            'status'                => TransactionStatus::Completed,
            'description'           => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * State: a deposit transaction (no source wallet).
     */
    public function deposit(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_wallet_id' => null,
            'type'             => TransactionType::Deposit,
        ]);
    }

    /**
     * State: a transfer transaction (requires both source and destination wallets).
     */
    public function transfer(?int $sourceWalletId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'source_wallet_id' => $sourceWalletId ?? Wallet::factory(),
            'type'             => TransactionType::Transfer,
        ]);
    }

    /**
     * State: a failed transaction (overrides the default completed status).
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransactionStatus::Failed,
        ]);
    }
}
