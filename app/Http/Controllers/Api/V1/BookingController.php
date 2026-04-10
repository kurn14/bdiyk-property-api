<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Property;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BookingController extends Controller
{
    /**
     * Create a new booking.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'property_type_id' => 'required|exists:property_types,id',
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'required|string|max:20',
            'institution' => 'nullable|string|max:255',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'external_reference' => 'nullable|string|max:255'
        ]);

        // 1. Check if property is available (status)
        $property = Property::find($validated['property_id']);
        if ($property->status !== 'available') {
            return response()->json(['message' => 'Property tidak tersedia (status: ' . $property->status . ')'], 422);
        }

        // 2. Check for date overlap
        $conflicting = Booking::where('property_id', $validated['property_id'])
            ->whereNotIn('status', ['cancelled', 'finished'])
            ->where('start_date', '<', $validated['end_date'])
            ->where('end_date', '>', $validated['start_date'])
            ->exists();

        if ($conflicting) {
            return response()->json(['message' => 'Property tidak tersedia pada tanggal tersebut'], 422);
        }

        // 3. Create booking
        $validated['status'] = 'scheduled';
        $validated['user_id'] = 1; // Default to admin for API bookings, or handle via auth if needed

        $booking = Booking::create($validated);

        return response()->json($booking, 201);
    }

    /**
     * Cancel a booking.
     */
    public function cancel(Request $request, $id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $booking->update([
            'status' => 'cancelled'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Booking dibatalkan'
        ]);
    }
}
