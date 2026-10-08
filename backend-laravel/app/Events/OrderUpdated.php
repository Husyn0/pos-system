<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

/** Items added or voided on an already-placed order — distinct from
 *  OrderCreated/OrderStatusChanged so clients can diff line items instead
 *  of assuming a whole new order appeared. */
class OrderUpdated implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->order->tenant_id}.branch.{$this->order->branch_id}.orders")];
    }

    public function broadcastAs(): string
    {
        return 'order.updated';
    }

    public function broadcastWith(): array
    {
        return ['order' => $this->order->load('items.menuItem')->toArray()];
    }
}
