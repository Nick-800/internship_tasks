<?php

namespace App\Events;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a successful fund transfer between two wallets.
 *
 * Carries everything the listener needs to build a notification:
 * the completed Transaction and both wallets involved.
 */
class FundsTransferred
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Transaction $transaction,
        public readonly Wallet $sourceWallet,
        public readonly Wallet $destinationWallet,
    ) {}
}
