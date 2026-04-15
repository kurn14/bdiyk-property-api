<?php

namespace App\Http\Controllers\Web;

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

        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $id = (int) $id;
        $virtualMapping = [
            6 => 3, // Request ID 6 -> property_type_id 3 (Kamar VIP)
            7 => 4, // Request ID 7 -> property_type_id 4 (Kamar 2 Bed)
            8 => 5, // Request ID 8 -> property_type_id 5 (Kamar 3 Bed)
        ];

        if (in_array($id, [1, 2, 3, 4, 5])) {
            $property = Property::find($id);
            if (!$property) {
                return response()->json(['message' => 'Property not found'], 404);
            }

            $conflicting = \App\Models\BookingSchedule::whereHas('booking', function ($q) use ($id) {
                $q->where('property_id', $id)
                  ->where(function ($qStatus) {
                      $qStatus->whereNotIn('status', ['cancelled', 'finished', 'booked'])
                              ->orWhere(function ($qBooked) {
                                  $qBooked->where('status', 'booked')
                                          ->where('payment_time_limit', '>=', now());
                              });
                  });
            })
            ->where('start_time', '<', $endDate)
            ->where('end_time', '>', $startDate)
            ->count();

            return response()->json([
                'property_id' => $id,
                'available' => ($conflicting === 0 && $property->status === 'available'),
                'conflicting_bookings' => $conflicting,
                'message' => $conflicting === 0 ? 'Property tersedia' : 'Property tidak tersedia pada tanggal tersebut'
            ]);
            
        } elseif (isset($virtualMapping[$id])) {
            $targetTypeId = $virtualMapping[$id];
            $availableRoomsCount = 0;
            $allRooms = Property::where('property_type_id', $targetTypeId)->get();
            
            foreach ($allRooms as $room) {
                if ($room->status !== 'available') continue;
                
                $conflicting = \App\Models\BookingSchedule::whereHas('booking', function ($q) use ($room) {
                    $q->where('property_id', $room->id)
                      ->where(function ($qStatus) {
                          $qStatus->whereNotIn('status', ['cancelled', 'finished', 'booked'])
                                  ->orWhere(function ($qBooked) {
                                      $qBooked->where('status', 'booked')
                                              ->where('payment_time_limit', '>=', now());
                                  });
                      });
                })
                ->where('start_time', '<', $endDate)
                ->where('end_time', '>', $startDate)
                ->exists();

                if (!$conflicting) {
                    $availableRoomsCount++;
                }
            }

            return response()->json([
                'property_id' => $id,
                'available' => $availableRoomsCount > 0,
                'available_count' => $availableRoomsCount,
                'message' => $availableRoomsCount > 0 ? ('Tersedia ' . $availableRoomsCount . ' kamar') : 'Kamar sudah habis pada tanggal tersebut'
            ]);
            
        } else {
            return response()->json(['message' => 'ID Properti tidak valid untuk sistem eksternal.'], 404);
        }
    }
}
