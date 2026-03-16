<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\TransactionImmutableException;
use App\Models\Transaction;
use App\Models\Wallet;
use Database\Factories\TransactionFactory;

// =============================================================================
// 1. ENUM CASTS
// =============================================================================

describe('Enum casts', function () {
    it('casts the type column to a TransactionType enum instance', function () {
        $wallet = Wallet::factory()->create();
        $tx = Transaction::factory()->deposit()->create([
            'destination_wallet_id' => $wallet->id,
        ]);

        expect($tx->fresh()->type)->toBe(TransactionType::Deposit);
    });

    it('casts the status column to a TransactionStatus enum instance', function () {
        $wallet = Wallet::factory()->create();
        $tx = Transaction::factory()->create([
            'destination_wallet_id' => $wallet->id,
        ]);

        expect($tx->fresh()->status)->toBe(TransactionStatus::Completed);
    });

    it('serialises enum to its string value in JSON', function () {
        $wallet = Wallet::factory()->create();
        $tx = Transaction::factory()->transfer()->create([
            'source_wallet_id'      => Wallet::factory()->create()->id,
            'destination_wallet_id' => $wallet->id,
        ]);

        $json = $tx->fresh()->toArray();

        expect($json['type'])->toBe('transfer')
            ->and($json['status'])->toBe('completed');
    });

    it('factory failed() state sets status to TransactionStatus::Failed', function () {
        $wallet = Wallet::factory()->create();
        $tx = Transaction::factory()->failed()->create([
            'destination_wallet_id' => $wallet->id,
        ]);

        expect($tx->fresh()->status)->toBe(TransactionStatus::Failed);
    });
});

// =============================================================================
// 2. ACCESSORS
// =============================================================================

describe('Accessors', function () {
    it('formattedBalance returns balance with currency code', function () {
        $wallet = Wallet::factory()->create(['balance' => 1234.56, 'currency' => 'USD']);

        expect($wallet->formatted_balance)->toBe('1,234.56 USD');
    });

    it('formattedBalance handles zero balance', function () {
        $wallet = Wallet::factory()->empty()->create(['currency' => 'EUR']);

        expect($wallet->formatted_balance)->toBe('0.00 EUR');
    });

    it('formattedBalance appears in wallet JSON serialisation via $appends', function () {
        $wallet = Wallet::factory()->create(['balance' => 500.00, 'currency' => 'GBP']);

        $array = $wallet->toArray();

        expect($array)->toHaveKey('formatted_balance')
            ->and($array['formatted_balance'])->toBe('500.00 GBP');
    });

    it('formattedAmount returns amount as formatted number string', function () {
        $wallet = Wallet::factory()->create();
        $tx = Transaction::factory()->create([
            'destination_wallet_id' => $wallet->id,
            'amount'                => 9999.99,
        ]);

        expect($tx->formatted_amount)->toBe('9,999.99');
    });

    it('formattedAmount appears in transaction JSON serialisation via $appends', function () {
        $wallet = Wallet::factory()->create();
        $tx = Transaction::factory()->create([
            'destination_wallet_id' => $wallet->id,
            'amount'                => 42.50,
        ]);

        $array = $tx->toArray();

        expect($array)->toHaveKey('formatted_amount')
            ->and($array['formatted_amount'])->toBe('42.50');
    });
});

// =============================================================================
// 3. LOCAL SCOPES
// =============================================================================

describe('Local scopes', function () {
    it('scopeForDestinationWallet returns only transactions received by a wallet', function () {
        $wallet      = Wallet::factory()->create();
        $otherWallet = Wallet::factory()->create();

        Transaction::factory()->count(3)->create(['destination_wallet_id' => $wallet->id]);
        Transaction::factory()->count(2)->create(['destination_wallet_id' => $otherWallet->id]);

        $results = Transaction::forDestinationWallet($wallet->id)->get();

        expect($results)->toHaveCount(3)
            ->each(fn ($t) => $t->destination_wallet_id->toBe($wallet->id));
    });

    it('scopeForSourceWallet returns only transactions sent from a wallet', function () {
        $source      = Wallet::factory()->create();
        $destination = Wallet::factory()->create();
        $other       = Wallet::factory()->create();

        Transaction::factory()->transfer($source->id)->count(2)->create([
            'destination_wallet_id' => $destination->id,
        ]);
        Transaction::factory()->transfer($other->id)->count(4)->create([
            'destination_wallet_id' => $destination->id,
        ]);

        $results = Transaction::forSourceWallet($source->id)->get();

        expect($results)->toHaveCount(2)
            ->each(fn ($t) => $t->source_wallet_id->toBe($source->id));
    });

    it('scopeForWallet returns transactions in either direction', function () {
        $walletA = Wallet::factory()->create();
        $walletB = Wallet::factory()->create();
        $walletC = Wallet::factory()->create();

        // 2 received by A
        Transaction::factory()->count(2)->create(['destination_wallet_id' => $walletA->id]);
        // 3 sent from A to B
        Transaction::factory()->transfer($walletA->id)->count(3)->create([
            'destination_wallet_id' => $walletB->id,
        ]);
        // 5 unrelated (B ↔ C only)
        Transaction::factory()->transfer($walletB->id)->count(5)->create([
            'destination_wallet_id' => $walletC->id,
        ]);

        $results = Transaction::forWallet($walletA->id)->get();

        // A is involved in 2 received + 3 sent = 5
        expect($results)->toHaveCount(5);
    });
});

// =============================================================================
// 4. OBSERVER — TRANSACTION IMMUTABILITY
// =============================================================================

describe('TransactionObserver immutability', function () {
    it('throws TransactionImmutableException when updating a completed transaction', function () {
        $wallet = Wallet::factory()->create();
        $tx = Transaction::factory()->create([
            'destination_wallet_id' => $wallet->id,
            'status'                => TransactionStatus::Completed,
        ]);

        expect(fn () => $tx->update(['description' => 'tampered']))
            ->toThrow(TransactionImmutableException::class);
    });

    it('allows updating a failed transaction', function () {
        $wallet = Wallet::factory()->create();
        $tx = Transaction::factory()->failed()->create([
            'destination_wallet_id' => $wallet->id,
        ]);

        // Should not throw — failed transactions are not yet immutable
        $tx->update(['description' => 'retry note']);

        expect($tx->fresh()->description)->toBe('retry note');
    });
});

// =============================================================================
// 5. FACTORIES
// =============================================================================

describe('Factories', function () {
    it('WalletFactory creates a wallet with a user', function () {
        $wallet = Wallet::factory()->create();

        expect($wallet->user)->not->toBeNull()
            ->and($wallet->balance)->toBeNumeric()
            ->and($wallet->currency)->toBeIn(['USD', 'EUR', 'GBP']);
    });

    it('WalletFactory empty() state produces zero balance', function () {
        $wallet = Wallet::factory()->empty()->create();

        expect((float) $wallet->balance)->toBe(0.0);
    });

    it('WalletFactory withBalance() state sets exact balance', function () {
        $wallet = Wallet::factory()->withBalance(250.75)->create();

        expect((float) $wallet->balance)->toBe(250.75);
    });

    it('TransactionFactory deposit() state has null source_wallet_id', function () {
        $wallet = Wallet::factory()->create();
        $tx = Transaction::factory()->deposit()->create([
            'destination_wallet_id' => $wallet->id,
        ]);

        expect($tx->source_wallet_id)->toBeNull()
            ->and($tx->type)->toBe(TransactionType::Deposit);
    });

    it('TransactionFactory transfer() state has a source_wallet_id', function () {
        $source = Wallet::factory()->create();
        $dest   = Wallet::factory()->create();
        $tx = Transaction::factory()->transfer($source->id)->create([
            'destination_wallet_id' => $dest->id,
        ]);

        expect($tx->source_wallet_id)->toBe($source->id)
            ->and($tx->type)->toBe(TransactionType::Transfer);
    });
});
