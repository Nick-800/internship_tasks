<?php

namespace App\Observers;

use App\Exceptions\TransactionImmutableException;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

/**
 * TransactionObserver — enforces immutability and audit logging on Transaction records.
 *
 * PROS of using an Observer here:
 *   - The immutability rule is guaranteed at the model layer, not the controller layer.
 *     No controller can accidentally update a completed transaction.
 *   - Adding new lifecycle hooks (e.g., audit log on delete) requires zero changes
 *     to controllers or services.
 *
 * CONS / caveats:
 *   - Observers do NOT fire on bulk/query-builder operations such as:
 *       Transaction::where('status', 'completed')->update([...]);  // observer SKIPPED
 *     Always use per-model saves for records governed by an observer.
 *   - "Action at a distance" — a developer unfamiliar with the observer might be
 *     confused why their update is throwing an exception. Keep observers documented
 *     and register them explicitly (see AppServiceProvider).
 */
class TransactionObserver
{
    /**
     * Log every new transaction for the audit trail.
     */
    public function created(Transaction $transaction): void
    {
        Log::info('Transaction created.', [
            'id'                   => $transaction->id,
            'type'                 => $transaction->type,
            'amount'               => $transaction->amount,
            'source_wallet_id'     => $transaction->source_wallet_id,
            'destination_wallet_id'=> $transaction->destination_wallet_id,
            'status'               => $transaction->status,
        ]);
    }

    /**
     * Block any attempt to modify a completed transaction.
     *
     * Returning false from an Observer hook cancels the Eloquent operation.
     * We also throw a typed exception so the caller gets a meaningful HTTP response.
     *
     * @throws TransactionImmutableException
     */
    public function updating(Transaction $transaction): bool
    {
        if ($transaction->getOriginal('status') === 'completed') {
            throw new TransactionImmutableException();
        }

        return true;
    }
}
