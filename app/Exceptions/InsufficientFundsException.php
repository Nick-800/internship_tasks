<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Thrown when the source wallet balance is lower than the requested transfer amount.
 * Maps to HTTP 422 (Unprocessable Entity) — the request was valid JSON but
 * the business rule cannot be satisfied.
 */
class InsufficientFundsException extends Exception
{
    public function __construct(string $message = 'Insufficient funds in the source wallet.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error'   => 'insufficient_funds',
            'message' => $this->getMessage(),
        ], 422);
    }
}
