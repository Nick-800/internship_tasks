<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Wallet;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class WalletAnalyticsService
{
    public function getAllTimeForWallet(int $walletId): array
    {
        $inboundSub = DB::table('transactions')
            ->selectRaw('destination_wallet_id AS wallet_id')
            ->selectRaw('COALESCE(SUM(amount), 0) AS total_received')
            ->selectRaw(
                'SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) AS deposit_count',
                [TransactionType::Deposit->value]
            )
            ->selectRaw(
                'SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) AS transfer_in_count',
                [TransactionType::Transfer->value]
            )
            ->selectRaw('MAX(created_at) AS latest_received_at')
            ->groupBy('destination_wallet_id');

        $outboundSub = DB::table('transactions')
            ->selectRaw('source_wallet_id AS wallet_id')
            ->selectRaw('COALESCE(SUM(amount), 0) AS total_sent')
            ->selectRaw(
                'SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) AS transfer_out_count',
                [TransactionType::Transfer->value]
            )
            ->selectRaw('MAX(created_at) AS latest_sent_at')
            ->whereNotNull('source_wallet_id')
            ->groupBy('source_wallet_id');

        $result = Wallet::query()
            ->from('wallets AS w')
            ->leftJoinSub($inboundSub, 'inbound', fn ($join) => $join->on('inbound.wallet_id', '=', 'w.id'))
            ->leftJoinSub($outboundSub, 'outbound', fn ($join) => $join->on('outbound.wallet_id', '=', 'w.id'))
            ->where('w.id', $walletId)
            ->selectRaw('w.id AS wallet_id')
            ->selectRaw('w.currency')
            ->selectRaw('w.balance AS current_balance')
            ->selectRaw('COALESCE(inbound.total_received, 0) AS total_received')
            ->selectRaw('COALESCE(outbound.total_sent, 0) AS total_sent')
            ->selectRaw('COALESCE(inbound.deposit_count, 0) AS deposit_count')
            ->selectRaw('COALESCE(inbound.transfer_in_count, 0) AS transfer_in_count')
            ->selectRaw('COALESCE(outbound.transfer_out_count, 0) AS transfer_out_count')
            ->selectRaw('COALESCE(inbound.total_received, 0) - COALESCE(outbound.total_sent, 0) AS net_flow')
            ->selectRaw($this->latestActivitySql() . ' AS latest_transaction_at')
            ->selectSub($this->balanceRankSubQuery(), 'balance_rank')
            ->first();

        return [
            'wallet_id'             => (int) $result->wallet_id,
            'currency'              => (string) $result->currency,
            'current_balance'       => (float) $result->current_balance,
            'total_received'        => (float) $result->total_received,
            'total_sent'            => (float) $result->total_sent,
            'net_flow'              => (float) $result->net_flow,
            'deposit_count'         => (int) $result->deposit_count,
            'transfer_in_count'     => (int) $result->transfer_in_count,
            'transfer_out_count'    => (int) $result->transfer_out_count,
            'latest_transaction_at' => $result->latest_transaction_at,
            'balance_rank'          => (int) $result->balance_rank,
        ];
    }

    private function balanceRankSubQuery(): Builder
    {
        return DB::table('wallets AS wr')
            ->selectRaw('COUNT(*)')
            ->whereRaw('wr.balance > w.balance OR (wr.balance = w.balance AND wr.id <= w.id)');
    }

    private function latestActivitySql(): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return "CASE
                WHEN inbound.latest_received_at IS NULL THEN outbound.latest_sent_at
                WHEN outbound.latest_sent_at IS NULL THEN inbound.latest_received_at
                ELSE MAX(inbound.latest_received_at, outbound.latest_sent_at)
            END";
        }

        return "CASE
            WHEN inbound.latest_received_at IS NULL THEN outbound.latest_sent_at
            WHEN outbound.latest_sent_at IS NULL THEN inbound.latest_received_at
            ELSE GREATEST(inbound.latest_received_at, outbound.latest_sent_at)
        END";
    }
}
