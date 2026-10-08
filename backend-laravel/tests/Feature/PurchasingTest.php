<?php

namespace Tests\Feature;

use App\Services\PurchasingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsPosData;
use Tests\TestCase;

class PurchasingTest extends TestCase
{
    use RefreshDatabase, SeedsPosData;

    public function test_receiving_a_purchase_order_increments_stock_and_updates_cost(): void
    {
        $this->bootstrapTenant();
        $purchasing = app(PurchasingService::class);
        $beans = $this->makeInventoryItem('Espresso Beans', onHand: 500, unit: 'g');

        $po = $purchasing->createDraft($this->tenant->id, [
            'branch_id' => $this->branch->id,
            'items' => [['inventory_item_id' => $beans->id, 'quantity' => 1000, 'unit_cost' => 0.02]],
        ]);
        $purchasing->markOrdered($po);
        $purchasing->receive($po->fresh());

        $this->assertEquals(1500, (float) $beans->fresh()->quantity_on_hand);
        $this->assertEquals(0.02, (float) $beans->fresh()->cost_per_unit);
        $this->assertEquals('received', $po->fresh()->status);
        $this->assertEquals(1, \App\Models\StockMovement::where('reference_type', 'purchase_order')->where('type', 'purchase')->count());
    }

    public function test_receiving_the_same_purchase_order_twice_does_not_double_credit_stock(): void
    {
        $this->bootstrapTenant();
        $purchasing = app(PurchasingService::class);
        $beans = $this->makeInventoryItem('Espresso Beans', onHand: 0, unit: 'g');

        $po = $purchasing->createDraft($this->tenant->id, [
            'branch_id' => $this->branch->id,
            'items' => [['inventory_item_id' => $beans->id, 'quantity' => 1000, 'unit_cost' => 0.02]],
        ]);

        $purchasing->receive($po->fresh());
        $purchasing->receive($po->fresh()); // simulated double-click / retried request

        $this->assertEquals(1000, (float) $beans->fresh()->quantity_on_hand); // not 2000
        $this->assertEquals(1, \App\Models\StockMovement::where('reference_type', 'purchase_order')->count());
    }

    public function test_cancelled_purchase_order_cannot_be_received(): void
    {
        $this->bootstrapTenant();
        $purchasing = app(PurchasingService::class);
        $beans = $this->makeInventoryItem('Espresso Beans', onHand: 0, unit: 'g');

        $po = $purchasing->createDraft($this->tenant->id, [
            'branch_id' => $this->branch->id,
            'items' => [['inventory_item_id' => $beans->id, 'quantity' => 100, 'unit_cost' => 0.02]],
        ]);
        $po->update(['status' => 'cancelled']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $purchasing->receive($po->fresh());
    }
}
