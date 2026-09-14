<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Reservation::when($request->branch_id, fn ($q) => $q->where('branch_id', $request->branch_id))
                ->when($request->date, fn ($q) => $q->whereDate('reserved_at', $request->date))
                ->orderBy('reserved_at')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|uuid',
            'table_id' => 'nullable|uuid',
            'customer_id' => 'nullable|uuid',
            'guest_name' => 'required_without:customer_id|string|max:150',
            'guest_phone' => 'nullable|string|max:30',
            'party_size' => 'required|integer|min:1|max:50',
            'reserved_at' => 'required|date',
            'notes' => 'nullable|string|max:255',
        ]);

        return response()->json(Reservation::create($data), 201);
    }

    public function updateStatus(Request $request, Reservation $reservation)
    {
        $request->validate(['status' => 'required|in:confirmed,seated,cancelled,no_show']);
        $reservation->update(['status' => $request->status]);
        return response()->json($reservation);
    }
}
