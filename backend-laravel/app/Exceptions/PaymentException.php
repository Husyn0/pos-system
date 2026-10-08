<?php

namespace App\Exceptions;

use Exception;

/** Thrown for payment-domain rule violations that aren't simple field validation:
 *  overpayment, over-refund, currency mismatch, paying a cancelled order, etc. */
class PaymentException extends Exception
{
    public function __construct(string $message, public readonly string $errorCode = 'payment_error')
    {
        parent::__construct($message);
    }

    public function render()
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ], 422);
    }
}
