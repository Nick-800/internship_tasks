<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Observers\TransactionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Observer applied here via the #[ObservedBy] attribute (Laravel 10+).
 *
 * WHY an Observer on Transaction?
 *   - Transactions are financial records — they must be immutable once completed.
 *   - Centralising that rule in an Observer means no controller can accidentally
 *     bypass it; the protection lives at the model layer.
 *
 * CONS to keep in mind:
 *   - Observers DO NOT fire on bulk operations (Transaction::where(...)->update()).
 *     Always use individual model saves for records that need observer protection.
 *   - Observers can make code harder to trace ("action at a distance").
 *     Document them clearly and keep their logic focused and side-effect-free.
 */
#[ObservedBy(TransactionObserver::class)]
class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_wallet_id',
        'destination_wallet_id',
        'amount',
        'type',
        'status',
        'description',
    ];

    /**
     * Enum casts replace the raw 'deposit'/'transfer' and 'completed'/'failed'
     * strings with type-safe BackedEnum instances. Invalid values become
     * impossible at the PHP layer — a typo is a compile-time error, not a
     * silent bug that reaches the database.
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'type'   => TransactionType::class,
        'status' => TransactionStatus::class,
    ];

    /**
     * Computed attributes appended to every JSON serialisation.
     * formatted_amount appears alongside the raw amount in API responses.
     */
    protected $appends = ['formatted_amount'];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'source_wallet_id');
    }

    public function destinationWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'destination_wallet_id');
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * formattedAmount — human-readable monetary string.
     *
     * Examples: "1,234.56", "0.50", "10,000.00"
     *
     * Uses PHP's number_format rather than a locale-aware formatter so the
     * output is consistent regardless of the server's locale setting.
     * Currency symbol is intentionally omitted here; the wallet's `currency`
     * field determines the symbol at the presentation layer.
     */
    protected function formattedAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format((float) $this->amount, 2),
        );
    }

    // -------------------------------------------------------------------------
    // Local Scopes
    // -------------------------------------------------------------------------

    /**
     * Scope: filter transactions received by a specific wallet.
     * Aligns with the (destination_wallet_id, created_at) composite index.
     */
    public function scopeForDestinationWallet(Builder $query, int $walletId): Builder
    {
        return $query->where('destination_wallet_id', $walletId);
    }

    /**
     * Scope: filter transactions sent from a specific wallet.
     * Aligns with the (source_wallet_id, created_at) composite index.
     */
    public function scopeForSourceWallet(Builder $query, int $walletId): Builder
    {
        return $query->where('source_wallet_id', $walletId);
    }

    /**
     * Scope: all transactions involving a wallet in either direction.
     *
     * WHERE source_wallet_id = ? OR destination_wallet_id = ?
     *
     * Use this when you need the full activity history of a wallet (both sent
     * and received). For one-direction-only queries, prefer the more targeted
     * scopeForSourceWallet / scopeForDestinationWallet which align with the
     * composite indexes and are therefore faster on large tables.
     */
    public function scopeForWallet(Builder $query, int $walletId): Builder
    {
        return $query->where(function (Builder $q) use ($walletId) {
            $q->where('source_wallet_id', $walletId)
              ->orWhere('destination_wallet_id', $walletId);
        });
    }
}
