<?php

namespace App\Enums;

/**
 * Represents the two ways funds can enter a wallet.
 *
 * DEPOSIT  — funds added from an external source (no source_wallet_id).
 * TRANSFER — funds moved between two existing wallets.
 *
 * Using a BackedEnum instead of plain strings means:
 *   - Invalid values are impossible at the PHP layer (no "typo = silent bug").
 *   - IDE autocompletion works everywhere this type is referenced.
 *   - Eloquent casts the DB string to/from this enum automatically.
 */
enum TransactionType: string
{
    case Deposit  = 'deposit';
    case Transfer = 'transfer';
}
