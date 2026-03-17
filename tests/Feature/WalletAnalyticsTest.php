<?php

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Wallet;

describe('Wallet analytics endpoint', function () {
    it('returns all-time analytics using raw SQL subqueries', function () {
        $wallet = Wallet::factory()->create([
            'balance' => 500.00,
            'currency' => 'USD',
        ]);

        $richerWallet = Wallet::factory()->create(['balance' => 900.00, 'currency' => 'USD']);
        $poorerWallet = Wallet::factory()->create(['balance' => 100.00, 'currency' => 'USD']);

        Transaction::factory()->deposit()->create([
            'destination_wallet_id' => $wallet->id,
            'amount' => 120.00,
            'created_at' => '2026-03-10 10:00:00',
            'updated_at' => '2026-03-10 10:00:00',
        ]);

        Transaction::factory()->transfer($richerWallet->id)->create([
            'destination_wallet_id' => $wallet->id,
            'amount' => 30.00,
            'created_at' => '2026-03-11 10:00:00',
            'updated_at' => '2026-03-11 10:00:00',
        ]);

        Transaction::factory()->transfer($wallet->id)->create([
            'destination_wallet_id' => $poorerWallet->id,
            'amount' => 50.00,
            'created_at' => '2026-03-12 10:00:00',
            'updated_at' => '2026-03-12 10:00:00',
        ]);

        Transaction::factory()->transfer($wallet->id)->create([
            'destination_wallet_id' => $richerWallet->id,
            'amount' => 20.00,
            'created_at' => '2026-03-13 10:00:00',
            'updated_at' => '2026-03-13 10:00:00',
        ]);

        // unrelated transaction should not impact this wallet's analytics
        Transaction::factory()->transfer($poorerWallet->id)->create([
            'destination_wallet_id' => $richerWallet->id,
            'amount' => 999.99,
            'type' => TransactionType::Transfer,
            'created_at' => '2026-03-14 10:00:00',
            'updated_at' => '2026-03-14 10:00:00',
        ]);

        $response = $this->getJson("/api/wallets/{$wallet->id}/analytics");

        $response->assertOk()
            ->assertJsonPath('analytics.wallet_id', $wallet->id)
            ->assertJsonPath('analytics.currency', 'USD')
            ->assertJsonPath('analytics.current_balance', 500)
            ->assertJsonPath('analytics.total_received', 150)
            ->assertJsonPath('analytics.total_sent', 70)
            ->assertJsonPath('analytics.net_flow', 80)
            ->assertJsonPath('analytics.deposit_count', 1)
            ->assertJsonPath('analytics.transfer_in_count', 1)
            ->assertJsonPath('analytics.transfer_out_count', 2)
            ->assertJsonPath('analytics.latest_transaction_at', '2026-03-13 10:00:00')
            ->assertJsonPath('analytics.balance_rank', 2);
    });

    it('returns zeroed aggregates when wallet has no transactions', function () {
        $wallet = Wallet::factory()->create([
            'balance' => 10.00,
            'currency' => 'EUR',
        ]);

        $response = $this->getJson("/api/wallets/{$wallet->id}/analytics");

        $response->assertOk()
            ->assertJsonPath('analytics.wallet_id', $wallet->id)
            ->assertJsonPath('analytics.currency', 'EUR')
            ->assertJsonPath('analytics.total_received', 0)
            ->assertJsonPath('analytics.total_sent', 0)
            ->assertJsonPath('analytics.net_flow', 0)
            ->assertJsonPath('analytics.deposit_count', 0)
            ->assertJsonPath('analytics.transfer_in_count', 0)
            ->assertJsonPath('analytics.transfer_out_count', 0)
            ->assertJsonPath('analytics.latest_transaction_at', null);
    });

    it('returns 404 for a missing wallet', function () {
        $response = $this->getJson('/api/wallets/999999/analytics');

        $response->assertNotFound()
            ->assertJson([
                'error' => 'wallet_not_found',
                'message' => 'Wallet not found.',
            ]);
    });
});
