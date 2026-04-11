<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Property;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BookingController extends Controller
{
    public function index()
    {
        return response()->json(Booking::with('property.type', 'user', 'type')->get());
    }

    private function isRoomAvailable($propertyId, $startDate, $endDate)
    {
        return !Booking::where('property_id', $propertyId)
            ->where('start_date', '<', $endDate)
            ->where('end_date', '>', $startDate)
            ->where('status', '!=', 'cancelled')
            ->exists();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'required|string|max:20',
            'institution' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'nullable|string|in:scheduled,in_use,finished,cancelled'
        ]);

        $property = Property::find($validated['property_id']);
        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);

        // 2. Aturan Jam Operasional
        $propertyTypeId = $property->property_type_id;
        if (in_array($propertyTypeId, [1, 2])) {
            if ($start->format('H:i') < '06:00') {
                return response()->json(['message' => 'Check-in untuk Ruang Kelas/Meeting dimulai jam 06:00'], 422);
            }
            if ($end->format('H:i') > '22:00') {
                return response()->json(['message' => 'Check-out untuk Ruang Kelas/Meeting maksimal jam 22:00'], 422);
            }
        } elseif (in_array($propertyTypeId, [3, 4, 5])) {
            if ($start->format('H:i') < '14:00') {
                return response()->json(['message' => 'Check-in untuk Tipe Kamar dimulai jam 14:00'], 422);
            }
            if ($end->format('H:i') > '12:00') {
                return response()->json(['message' => 'Check-out untuk Tipe Kamar maksimal jam 12:00'], 422);
            }
        }

        // 3. Validasi isRoomAvailable
        if (!$this->isRoomAvailable($validated['property_id'], $validated['start_date'], $validated['end_date'])) {
            return response()->json(['message' => 'Ruangan sudah dipesan pada tanggal tersebut'], 409);
        }

        // 4. Booking code otomatis digenerate oleh Model (Booking::boot)
        
        $validated['user_id'] = $request->user()->id;

        $booking = Booking::create($validated);

        return response()->json($booking, 201);
    }

    public function show($id)
    {
        $booking = Booking::with('property.type', 'user', 'type')->find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        return response()->json($booking);
    }

    public function update(Request $request, $id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $validated = $request->validate([
            'property_id' => 'sometimes|exists:properties,id',
            'contact_name' => 'sometimes|string|max:255',
            'contact_email' => 'sometimes|email|max:255',
            'contact_phone' => 'sometimes|string|max:20',
            'institution' => 'nullable|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'status' => 'sometimes|string|in:scheduled,in_use,finished,cancelled'
        ]);

        $booking->update($validated);

        return response()->json($booking);
    }

    public function destroy($id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $booking->delete();

        return response()->json(['message' => 'Booking deleted successfully']);
    }
}
