<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request)
    {
        $query = Order::query()->with('items.menuItem', 'table', 'customer')
            ->when($request->branch_id, fn ($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->date, fn ($q) => $q->whereDate('placed_at', $request->date))
            ->latest('placed_at');

        return response()->json($query->paginate($request->integer('per_page', 25)));
    }

    public function show(Order $order)
    {
        return response()->json($order->load('items.menuItem', 'items.modifiers.modifier', 'payments.refunds', 'table', 'customer'));
    }

    public function store(CreateOrderRequest $request)
    {
        $order = $this->orders->createOrder(
            app('currentTenantId'),
            $request->validated(),
            $request->user()?->id
        );

        return response()->json($order, 201);
    }

    /** Add more items to an already-open order (dine-in tab). */
    public function addItems(Request $request, Order $order)
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|uuid',
            'items.*.item_variant_id' => 'nullable|uuid',
            'items.*.quantity' => 'required|integer|min:1|max:99',
            'items.*.modifier_ids' => 'nullable|array',
            'items.*.notes' => 'nullable|string|max:255',
        ]);

        return response()->json($this->orders->addItems($order, $data['items']));
    }

    public function voidItem(Request $request, Order $order, OrderItem $orderItem)
    {
        $request->validate(['reason' => 'required|string|max:255']);
        return response()->json($this->orders->voidItem($order, $orderItem, $request->reason));
    }

    /** Non-cancel status transitions (confirmed/preparing/ready/completed).
     *  Cancellation is handled by its own endpoint since it has different
     *  rules (payments must be refunded first — see OrderService::cancelOrder). */
    public function updateStatus(Request $request, Order $order)
    {
        $request->validate(['status' => 'required|in:confirmed,preparing,ready,completed']);
        return response()->json($this->orders->transitionStatus($order, $request->status));
    }

    public function cancel(Request $request, Order $order, PaymentService $payments)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
            'auto_refund' => 'nullable|boolean',
        ]);

        $result = $this->orders->cancelOrder(
            $order,
            $request->reason,
            $request->boolean('auto_refund'),
            $request->user()?->id,
            $payments
        );

        return response()->json($result);
    }

    /** Track an order by its public order number — used by public-web / mobile without auth. */
    public function trackByNumber(Request $request, string $orderNumber)
    {
        $order = Order::withoutGlobalScopes()
            ->where('order_number', $orderNumber)
            ->where('branch_id', $request->query('branch_id'))
            ->with('items.menuItem')
            ->firstOrFail();

        return response()->json([
            'order_number' => $order->order_number,
            'status' => $order->status,
            'placed_at' => $order->placed_at,
            'ready_at' => $order->ready_at,
            'items' => $order->items,
            'total' => $order->total,
        ]);
    }
}
