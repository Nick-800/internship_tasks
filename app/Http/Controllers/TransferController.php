<?php

namespace App\Http\Controllers;

use App\Exceptions\WalletNotFoundException;
use App\Http\Requests\TransferRequest;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;

class TransferController extends Controller
{
    public function __construct(private readonly WalletService $walletService) {}

    /**
     * POST /api/transfer
     *
     * Delegates all business logic to WalletService::transfer, which:
     *   - Opens a DB::transaction
     *   - Enforces SelfTransfer / InsufficientFunds rules (Custom Exceptions)
     *   - Debits & credits atomically
     *   - Dispatches FundsTransferred event (decoupled notifications via Listeners)
     *
     * The controller's only job is HTTP: validate input, call the service,
     * return a JSON response.
     *
     * @throws WalletNotFoundException
     */
    public function transfer(TransferRequest $request): JsonResponse
    {
        $source = Wallet::find($request->source_wallet_id);
        $destination = Wallet::find($request->destination_wallet_id);

        if (! $source) {
            throw new WalletNotFoundException('Source wallet not found.');
        }

        if (! $destination) {
            throw new WalletNotFoundException('Destination wallet not found.');
        }

        $transaction = $this->walletService->transfer(
            $source,
            $destination,
            (float) $request->amount,
            $request->description,
        );

        return response()->json([
            'message'             => 'Transfer successful.',
            'transaction'         => $transaction,
            'source_balance'      => $source->fresh()->balance,
            'destination_balance' => $destination->fresh()->balance,
        ], 201);
    }
}
