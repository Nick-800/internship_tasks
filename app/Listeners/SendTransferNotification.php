<?php

namespace App\Listeners;

use App\Events\FundsTransferred;
use Illuminate\Support\Facades\Log;

/**
 * Notifies both wallet owners whenever a transfer completes.
 *
 * Again, logging instead of real mail/push notifications to keep
 * the focus on the event-listener decoupling pattern.
 */
class SendTransferNotification
{
    public function handle(FundsTransferred $event): void
    {
        $tx = $event->transaction;

        Log::info('Transfer notification sent to sender.', [
            'wallet_id'      => $event->sourceWallet->id,
            'user_id'        => $event->sourceWallet->user_id,
            'amount_debited' => $tx->amount,
        ]);

        Log::info('Transfer notification sent to recipient.', [
            'wallet_id'       => $event->destinationWallet->id,
            'user_id'         => $event->destinationWallet->user_id,
            'amount_credited' => $tx->amount,
        ]);
    }
}
