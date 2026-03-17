<?php

namespace App\Http\Controllers;

use App\Exceptions\WalletNotFoundException;
use App\Models\Wallet;
use App\Services\WalletAnalyticsService;
use Illuminate\Http\JsonResponse;

class WalletAnalyticsController extends Controller
{
    public function __construct(private readonly WalletAnalyticsService $walletAnalyticsService) {}

    /**
     * GET /api/wallets/{id}/analytics
     *
     * Returns all-time wallet analytics powered by raw SQL + subqueries.
     *
     * @throws WalletNotFoundException
     */
    public function __invoke(int $id): JsonResponse
    {
        if (! Wallet::find($id)) {
            throw new WalletNotFoundException();
        }

        return response()->json([
            'analytics' => $this->walletAnalyticsService->getAllTimeForWallet($id),
        ]);
    }
}
