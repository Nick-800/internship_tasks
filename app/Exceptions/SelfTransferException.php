<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Thrown when a user attempts to transfer funds to their own wallet.
 * Maps to HTTP 422 — semantically invalid operation.
 */
class SelfTransferException extends Exception
{
    public function __construct(string $message = 'You cannot transfer funds to your own wallet.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error'   => 'self_transfer',
            'message' => $this->getMessage(),
        ], 422);
    }
}
