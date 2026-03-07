<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Thrown when someone attempts to modify a completed (immutable) transaction.
 * Maps to HTTP 403 — action is understood but not allowed.
 */
class TransactionImmutableException extends Exception
{
    public function __construct(string $message = 'Completed transactions cannot be modified.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error'   => 'transaction_immutable',
            'message' => $this->getMessage(),
        ], 403);
    }
}
