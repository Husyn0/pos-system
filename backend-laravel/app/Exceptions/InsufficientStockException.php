<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown inside a locked DB transaction the moment we discover an order
 * can't be fulfilled from current stock. Carries every short ingredient
 * (not just the first one hit) so the POS can show the cashier a complete
 * picture in one round trip instead of a frustrating one-at-a-time reveal.
 */
class InsufficientStockException extends Exception
{
    /** @param array<int, array{inventory_item_id: string, name: string, required: float, available: float, unit: string}> $shortages */
    public function __construct(public readonly array $shortages)
    {
        $summary = collect($shortages)->map(fn ($s) => "{$s['name']} (need {$s['required']}{$s['unit']}, have {$s['available']}{$s['unit']})")->join(', ');
        parent::__construct("Insufficient stock: {$summary}");
    }

    public function render()
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => 'insufficient_stock',
            'shortages' => $this->shortages,
        ], 409); // 409 Conflict: the request is well-formed but can't be satisfied right now
    }
}
