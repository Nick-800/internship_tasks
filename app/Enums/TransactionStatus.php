<?php

namespace App\Enums;

/**
 * Lifecycle status of a transaction record.
 *
 * COMPLETED — funds were successfully moved; the record is now immutable
 *             (enforced by TransactionObserver).
 * FAILED    — the operation did not complete; balance was not changed.
 *             Failed transactions are hidden from normal queries by the
 *             CompletedTransactionScope global scope. Use
 *             Transaction::withoutGlobalScope(CompletedTransactionScope::class)
 *             or the scopeWithFailed() local scope to include them.
 */
enum TransactionStatus: string
{
    case Completed = 'completed';
    case Failed    = 'failed';
}
