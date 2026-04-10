<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Booking;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    /**
     * List properties with search and filters.
     */
    public function index(Request $request)
    {
        $query = Property::with('type');

        if ($request->has('search')) {
            $query->where('name', 'ilike', '%' . $request->search . '%');
        }

        if ($request->has('type_id')) {
            $query->where('property_type_id', $request->type_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * Show one property detail.
     */
    public function show($id)
    {
        $property = Property::with('type')->find($id);

        if (!$property) {
            return response()->json(['message' => 'Property not found'], 404);
        }

        return response()->json($property);
    }

    /**
     * Check availability for a property on a date range.
     */
    public function availability(Request $request, $id)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $property = Property::find($id);

        if (!$property) {
            return response()->json(['message' => 'Property not found'], 404);
        }

        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $conflicting = Booking::where('property_id', $id)
            ->whereNotIn('status', ['cancelled', 'finished'])
            ->where('start_date', '<', $endDate)
            ->where('end_date', '>', $startDate)
            ->count();

        return response()->json([
            'property_id' => $id,
            'available' => $conflicting === 0,
            'conflicting_bookings' => $conflicting,
            'message' => $conflicting === 0 ? 'Property tersedia' : 'Property tidak tersedia pada tanggal tersebut'
        ]);
    }
}
