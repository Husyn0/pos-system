<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailySalesSummary;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /** Fast dashboard summary tiles + trend — reads pre-aggregated rollups, not raw orders. */
    public function dashboard(Request $request)
    {
        $branchId = $request->branch_id;
        $days = (int) $request->input('days', 30);
        $from = now()->subDays($days)->toDateString();

        $trend = DailySalesSummary::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('summary_date', '>=', $from)
            ->orderBy('summary_date')
            ->get(['summary_date', 'gross_sales', 'net_sales', 'orders_count', 'avg_order_value']);

        $today = Order::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDate('placed_at', now()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(total),0) as gross_sales, COALESCE(AVG(total),0) as avg_order_value')
            ->first();

        return response()->json([
            'today' => $today,
            'trend' => $trend,
        ]);
    }

    public function topItems(Request $request)
    {
        $branchId = $request->branch_id;
        $days = (int) $request->input('days', 30);

        $rows = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->when($branchId, fn ($q) => $q->where('orders.branch_id', $branchId))
            ->where('orders.placed_at', '>=', now()->subDays($days))
            ->where('orders.status', '!=', 'cancelled')
            ->groupBy('menu_items.id', 'menu_items.name')
            ->orderByDesc(DB::raw('SUM(order_items.line_total)'))
            ->limit(10)
            ->get([
                'menu_items.id',
                'menu_items.name',
                DB::raw('SUM(order_items.quantity) as quantity_sold'),
                DB::raw('SUM(order_items.line_total) as revenue'),
            ]);

        return response()->json($rows);
    }

    public function peakHours(Request $request)
    {
        $branchId = $request->branch_id;
        $days = (int) $request->input('days', 30);

        $rows = Order::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('placed_at', '>=', now()->subDays($days))
            ->where('status', '!=', 'cancelled')
            ->selectRaw("EXTRACT(HOUR FROM placed_at) as hour, COUNT(*) as orders_count")
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return response()->json($rows);
    }
}
