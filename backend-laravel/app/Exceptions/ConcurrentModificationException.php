<?php

namespace App\Exceptions;

use Exception;

/** The order was modified (status changed, items added, etc.) by another
 *  request between when the caller read it and when they tried to act on
 *  it. The client should re-fetch and retry rather than blindly overwrite. */
class ConcurrentModificationException extends Exception
{
    public function __construct()
    {
        parent::__construct('This order was modified by another request. Refresh and try again.');
    }

    public function render()
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => 'concurrent_modification',
        ], 409);
    }
}
