<?php

namespace App\Jobs;

use App\Models\LoyaltyTransaction;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Runs after an order completes. Deliberately queued (not inline) since
 * crediting loyalty points is not something the customer is waiting on at
 * checkout, and it's safe to be eventually-consistent — unlike inventory,
 * a loyalty point landing a few seconds late has no correctness impact.
 */
class AwardLoyaltyPointsForOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const POINTS_PER_CURRENCY_UNIT = 1; // 1 point per $1 spent, tune per tenant in a fuller implementation

    public function __construct(public string $orderId) {}

    public function handle(): void
    {
        $order = Order::withoutGlobalScopes()->findOrFail($this->orderId);
        if (! $order->customer_id) return;

        // Idempotent: if we've already credited this order, don't do it twice
        // (a job can be retried after a transient failure).
        $alreadyCredited = LoyaltyTransaction::where('order_id', $order->id)
            ->where('reason', 'earn_purchase')->exists();
        if ($alreadyCredited) return;

        $points = (int) floor((float) $order->total * self::POINTS_PER_CURRENCY_UNIT);
        if ($points <= 0) return;

        DB::transaction(function () use ($order, $points) {
            LoyaltyTransaction::create([
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'points_change' => $points,
                'reason' => 'earn_purchase',
            ]);
            \App\Models\Customer::where('id', $order->customer_id)->increment('loyalty_points', $points);
            \App\Models\Customer::where('id', $order->customer_id)->increment('total_spent', $order->total);
        });
    }
}
