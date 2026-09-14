<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\RecipeIngredient;
use App\Models\StockMovement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Runs after an order completes: walks each order item's recipe and
 * decrements raw-ingredient stock, writing an auditable stock_movements
 * row per ingredient. Queued (not inline in the checkout request) so a
 * slow inventory pass never delays the customer's receipt, and retry-safe
 * because it's driven off the immutable order_items rows.
 */
class DeductInventoryForOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $orderId) {}

    public function handle(): void
    {
        $order = Order::withoutGlobalScopes()->with('items')->findOrFail($this->orderId);

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if (! $item->menuItem?->track_inventory) {
                    continue;
                }

                $recipeQuery = RecipeIngredient::query();
                $recipe = $item->item_variant_id
                    ? $recipeQuery->where('item_variant_id', $item->item_variant_id)->get()
                    : $recipeQuery->where('menu_item_id', $item->menu_item_id)->get();

                foreach ($recipe as $ingredient) {
                    $consumed = $ingredient->quantity_required * $item->quantity;

                    $ingredient->inventoryItem()->decrement('quantity_on_hand', $consumed);

                    StockMovement::create([
                        'inventory_item_id' => $ingredient->inventory_item_id,
                        'type' => 'sale',
                        'quantity' => -$consumed,
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'note' => "Order {$order->order_number}",
                    ]);
                }
            }
        });
    }
}
