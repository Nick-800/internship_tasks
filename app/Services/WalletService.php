<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\FundsTransferred;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\SelfTransferException;
use App\Exceptions\WalletNotFoundException;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

/**
 * WalletService — all wallet business logic lives here, away from controllers.
 *
 * DB::transaction is used for every write operation that touches more than one
 * row.  If any step throws, PDO rolls back automatically and no partial state
 * is persisted.
 */
class WalletService
{
    private const TRANSACTION_RETRY_ATTEMPTS = 5;

    /**
     * Deposit a positive amount into a wallet.
     *
     * DB::transaction wraps:
     *   1. Incrementing the wallet balance.
     *   2. Creating the Transaction audit record.
     *
     * If step 2 fails (e.g., a DB constraint violation), step 1 is rolled back
     * — the wallet balance never increases without a matching Transaction record.
     */
    public function deposit(Wallet $wallet, float $amount, ?string $description = null): Transaction
    {
        return DB::transaction(function () use ($wallet, $amount, $description) {
            // lockForUpdate() issues SELECT … FOR UPDATE, preventing a concurrent
            // deposit from reading a stale balance inside the same transaction.
            $wallet = Wallet::lockForUpdate()->findOrFail($wallet->id);

            $wallet->increment('balance', $amount);

            return Transaction::create([
                'source_wallet_id' => null,          // deposits have no source
                'destination_wallet_id' => $wallet->id,
                'amount' => $amount,
                'type' => TransactionType::Deposit,
                'status' => TransactionStatus::Completed,
                'description' => $description,
            ]);
        }, self::TRANSACTION_RETRY_ATTEMPTS);
    }

    /**
     * Transfer an amount from one wallet to another.
     *
     * DB::transaction wraps FOUR steps — all succeed or all fail together:
     *   1. Lock & reload both wallets (prevents race conditions).
     *   2. Validate business rules (sufficient funds, no self-transfer).
     *   3. Debit source, credit destination.
     *   4. Create the immutable Transaction record.
     *
     * After the transaction commits, FundsTransferred is dispatched so listeners
     * (notifications, analytics, etc.) can react without coupling them to this method.
     *
     * @throws SelfTransferException
     * @throws WalletNotFoundException
     * @throws InsufficientFundsException
     */
    public function transfer(
        Wallet $sourceWallet,
        Wallet $destinationWallet,
        float $amount,
        ?string $description = null
    ): Transaction {
        // --- Business rule validation BEFORE opening the transaction ---
        if ($sourceWallet->id === $destinationWallet->id) {
            throw new SelfTransferException;
        }

        $transaction = DB::transaction(function () use ($sourceWallet, $destinationWallet, $amount, $description) {
            // Lock both rows in a consistent order (lower id first) to prevent deadlocks
            // when two concurrent transfers involve the same pair of wallets.
            [$first, $second] = $sourceWallet->id < $destinationWallet->id
                ? [$sourceWallet->id, $destinationWallet->id]
                : [$destinationWallet->id, $sourceWallet->id];

            $locked = Wallet::lockForUpdate()
                ->whereIn('id', [$first, $second])
                ->orderBy('id')
                ->get()
                ->keyBy('id');

            if (! isset($locked[$sourceWallet->id]) || ! isset($locked[$destinationWallet->id])) {
                throw new WalletNotFoundException('One or both wallets no longer exist.');
            }

            $source = $locked[$sourceWallet->id];
            $destination = $locked[$destinationWallet->id];

            $debitedRows = DB::update(
                'UPDATE wallets SET balance = balance - ? WHERE id = ? AND balance >= ?',
                [$amount, $source->id, $amount]
            );

            if ($debitedRows === 0) {
                throw new InsufficientFundsException(
                    "Insufficient funds. Available: {$source->balance}, requested: {$amount}."
                );
            }

            $destination->increment('balance', $amount);

            return Transaction::create([
                'source_wallet_id' => $source->id,
                'destination_wallet_id' => $destination->id,
                'amount' => $amount,
                'type' => TransactionType::Transfer,
                'status' => TransactionStatus::Completed,
                'description' => $description,
            ]);
        }, self::TRANSACTION_RETRY_ATTEMPTS);

        // Dispatch AFTER the DB transaction commits — if the transaction rolled back,
        // we never reach this line, so listeners won't fire for failed transfers.
        FundsTransferred::dispatch(
            $transaction,
            $sourceWallet->fresh(),
            $destinationWallet->fresh()
        );

        return $transaction;
    }
}
