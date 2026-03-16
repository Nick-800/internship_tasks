<?php

namespace App\Http\Controllers;

use App\Exceptions\WalletNotFoundException;
use App\Http\Requests\DepositRequest;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    public function __construct(private readonly WalletService $walletService) {}

    /**
     * GET /api/wallets/{wallet}
     * Return a wallet with its owner and latest 20 transactions.
     *
     * @throws WalletNotFoundException
     */
    public function show(int $id): JsonResponse
    {
        $wallet = Wallet::with(['user', 'transactions' => fn ($q) => $q->forDestinationWallet($id)->latest()->limit(20)])
            ->find($id);

        if (! $wallet) {
            throw new WalletNotFoundException();
        }

        return response()->json(['wallet' => $wallet]);
    }

    /**
     * POST /api/wallets/{wallet}/deposit
     * Deposit funds into a wallet.
     *
     * @throws WalletNotFoundException
     */
    public function deposit(DepositRequest $request, int $id): JsonResponse
    {
        $wallet = Wallet::find($id);

        if (! $wallet) {
            throw new WalletNotFoundException();
        }

        $transaction = $this->walletService->deposit(
            $wallet,
            (float) $request->amount,
            $request->description,
        );

        return response()->json([
            'message'     => 'Deposit successful.',
            'transaction' => $transaction,
            'new_balance' => $wallet->fresh()->balance,
        ], 201);
    }

    /**
     * GET /api/wallets/{wallet}/transactions
     * Paginated transaction history for a wallet.
     *
     * @throws WalletNotFoundException
     */
    public function transactions(int $id): JsonResponse
    {
        $wallet = Wallet::find($id);

        if (! $wallet) {
            throw new WalletNotFoundException();
        }

        $transactions = Transaction::forDestinationWallet($id)->latest()->paginate(15);

        return response()->json($transactions);
    }
}
