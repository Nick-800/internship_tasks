<?php

namespace App\Models;

use App\Observers\TransactionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
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

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'source_wallet_id');
    }

    public function destinationWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'destination_wallet_id');
    }
}
