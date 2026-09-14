<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
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
        return response()->json($order->load('items.menuItem', 'items.modifiers.modifier', 'payments', 'table', 'customer'));
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

    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,preparing,ready,completed,cancelled',
            'cancelled_reason' => 'required_if:status,cancelled|string|max:255|nullable',
        ]);

        if ($request->status === 'cancelled') {
            $order->update(['cancelled_reason' => $request->cancelled_reason]);
        }

        $order = $this->orders->transitionStatus($order, $request->status);

        return response()->json($order);
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
