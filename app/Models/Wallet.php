<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'balance', 'currency'];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    /**
     * Computed attributes appended to every JSON serialisation.
     * formatted_balance provides a human-readable balance string in API responses.
     */
    protected $appends = ['formatted_balance'];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Transactions where this wallet is the source (money leaving).
     */
    public function sentTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'source_wallet_id');
    }

    /**
     * Transactions where this wallet is the destination (money arriving).
     */
    public function receivedTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'destination_wallet_id');
    }

    /**
     * Alias for receivedTransactions — used by the show endpoint eager load
     * and relationship constraints in WalletController.
     *
     * NOTE: this relationship only covers inbound transactions (destination_wallet_id).
     * To query all transactions involving this wallet in either direction, use
     * Transaction::scopeForWallet($walletId) instead.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'destination_wallet_id');
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * formattedBalance — human-readable balance with currency code.
     *
     * Examples: "1,234.56 USD", "0.50 EUR", "10,000.00 GBP"
     *
     * Intentionally kept simple (no locale-aware symbol) so the output is
     * consistent regardless of server locale. The currency code is appended
     * so consumers always know the denomination without a separate field lookup.
     */
    protected function formattedBalance(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format((float) $this->balance, 2) . ' ' . $this->currency,
        );
    }
}
