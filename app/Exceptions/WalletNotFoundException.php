<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Thrown when a wallet does not exist or does not belong to the authenticated user.
 * Maps to HTTP 404.
 */
class WalletNotFoundException extends Exception
{
    public function __construct(string $message = 'Wallet not found.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error'   => 'wallet_not_found',
            'message' => $this->getMessage(),
        ], 404);
    }
}
