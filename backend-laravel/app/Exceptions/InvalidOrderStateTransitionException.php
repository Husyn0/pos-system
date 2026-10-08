<?php

namespace App\Exceptions;

use Exception;

class InvalidOrderStateTransitionException extends Exception
{
    public function __construct(public readonly string $from, public readonly string $to)
    {
        parent::__construct("Cannot transition order from '{$from}' to '{$to}'.");
    }

    public function render()
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => 'invalid_state_transition',
            'from' => $this->from,
            'to' => $this->to,
        ], 409);
    }
}
