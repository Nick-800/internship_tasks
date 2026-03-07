<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Digital Wallet
|--------------------------------------------------------------------------
|
| POST   /api/register                         Create user + wallet atomically
| GET    /api/wallets/{id}                     View wallet & recent transactions
| POST   /api/wallets/{id}/deposit             Deposit funds
| GET    /api/wallets/{id}/transactions        Paginated transaction history
| POST   /api/transfer                         Transfer between wallets
|
*/

Route::post('/register', [AuthController::class, 'register']);

Route::prefix('wallets')->group(function () {
    Route::get('/{id}', [WalletController::class, 'show']);
    Route::post('/{id}/deposit', [WalletController::class, 'deposit']);
    Route::get('/{id}/transactions', [WalletController::class, 'transactions']);
});

Route::post('/transfer', [TransferController::class, 'transfer']);
