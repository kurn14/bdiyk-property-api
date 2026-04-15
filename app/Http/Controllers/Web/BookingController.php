<?php

namespace App\Http\Controllers\Web;

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
            'property_id' => 'required|integer|min:1',
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'required|string|max:20',
            'institution' => 'nullable|string|max:255',
            'quantity' => 'nullable|integer|min:1',
            'schedules' => 'required|array|min:1',
            'schedules.*.start_time' => 'required|date',
            'schedules.*.end_time' => 'required|date|after:schedules.*.start_time',
        ]);

        $requestedPropertyId = $validated['property_id'];
        $virtualMapping = [
            6 => 3, // Request ID 6 -> property_type_id 3 (Kamar VIP)
            7 => 4, // Request ID 7 -> property_type_id 4 (Kamar 2 Bed)
            8 => 5, // Request ID 8 -> property_type_id 5 (Kamar 3 Bed)
        ];

        $propertiesToBook = [];
        $quantityNeeded = $validated['quantity'] ?? 1;

        if (in_array($requestedPropertyId, [1, 2, 3, 4, 5])) {
            // Specific property booking
            $property = Property::find($requestedPropertyId);
            if (!$property) {
                return response()->json(['message' => 'Property tidak ditemukan'], 404);
            }
            if ($property->status !== 'available') {
                return response()->json(['message' => 'Property tidak tersedia (status: ' . $property->status . ')'], 422);
            }
            $propertiesToBook[] = $property;
        } elseif (isset($virtualMapping[$requestedPropertyId])) {
            // Virtual category booking
            $targetTypeId = $virtualMapping[$requestedPropertyId];
            $availableRooms = [];
            $allRooms = Property::where('property_type_id', $targetTypeId)->orderBy('id')->get();
            
            foreach($allRooms as $room) {
                if ($room->status !== 'available') continue;
                
                $isAvailable = true;
                foreach ($validated['schedules'] as $schedule) {
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
                    ->where('start_time', '<', $schedule['end_time'])
                    ->where('end_time', '>', $schedule['start_time'])
                    ->exists();

                    if ($conflicting) {
                        $isAvailable = false;
                        break;
                    }
                }
                
                if ($isAvailable) {
                    $availableRooms[] = $room;
                    if (count($availableRooms) == $quantityNeeded) break;
                }
            }

            if (count($availableRooms) < $quantityNeeded) {
                return response()->json(['message' => 'Kapasitas kamar tidak cukup. Hanya tersedia ' . count($availableRooms) . ' unit.'], 422);
            }
            $propertiesToBook = $availableRooms;
        } else {
            return response()->json(['message' => 'ID Properti (' . $requestedPropertyId . ') tidak valid untuk sistem eksternal.'], 422);
        }

        // Check date overlap for specific room if using physical room IDs (1-5)
        if (in_array($requestedPropertyId, [1, 2, 3, 4, 5])) {
            $prop = $propertiesToBook[0];
            foreach ($validated['schedules'] as $schedule) {
                $conflicting = \App\Models\BookingSchedule::whereHas('booking', function ($q) use ($prop) {
                    $q->where('property_id', $prop->id)
                      ->where(function ($qStatus) {
                          $qStatus->whereNotIn('status', ['cancelled', 'finished', 'booked'])
                                  ->orWhere(function ($qBooked) {
                                      $qBooked->where('status', 'booked')
                                              ->where('payment_time_limit', '>=', now());
                                  });
                      });
                })
                ->where('start_time', '<', $schedule['end_time'])
                ->where('end_time', '>', $schedule['start_time'])
                ->exists();

                if ($conflicting) {
                    return response()->json(['message' => 'Property tidak tersedia pada jadwal tanggal tersebut'], 422);
                }
            }
        }

        $createdBookings = [];
        foreach ($propertiesToBook as $prop) {
            $bData = $validated;
            $bData['property_id'] = $prop->id;
            $bData['status'] = 'booked';
            $bData['payment_time_limit'] = now()->addHours((int) env('PAYMENT_TIME_LIMIT_HOURS', 2));
            $bData['user_id'] = 1; // Default to admin for API bookings

            $booking = Booking::create($bData);
            foreach ($validated['schedules'] as $schedule) {
                $booking->schedules()->create($schedule);
            }
            $createdBookings[] = $booking->load('schedules', 'property.type');
        }

        return response()->json(['data' => $createdBookings], 201);
    }

    /**
     * Cancel a booking.
     */
    public function cancel(Request $request, $booking_code)
    {
        $booking = Booking::where('booking_code', $booking_code)->first();

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

    /**
     * Konfirmasi pembayaran. Status booking menjadi scheduled.
     */
    public function payment(Request $request, $id)
    {
        $booking = Booking::with('schedules', 'property')->find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        if ($booking->status !== 'booked') {
            return response()->json(['message' => 'Booking status is not applicable for payment (current status: ' . $booking->status . ')'], 422);
        }

        // Check availability strictly (booked is ignored from conflict checks, we must check if its newly assigned property is still free)
        $isStillAvailable = true;
        foreach ($booking->schedules as $schedule) {
            $conflicting = \App\Models\BookingSchedule::whereHas('booking', function ($q) use ($booking) {
                $q->where('property_id', $booking->property_id)
                  ->where('id', '!=', $booking->id)
                  ->where(function ($qStatus) {
                      $qStatus->whereNotIn('status', ['cancelled', 'finished', 'booked'])
                              ->orWhere(function ($qBooked) {
                                  $qBooked->where('status', 'booked')
                                          ->where('payment_time_limit', '>=', now());
                              });
                  });
            })
            ->where('start_time', '<', $schedule->end_time)
            ->where('end_time', '>', $schedule->start_time)
            ->exists();

            if ($conflicting) {
                $isStillAvailable = false;
                break;
            }
        }

        if (!$isStillAvailable) {
            // Attempt auto-reassign for virtual kamars (id > 5 are kamar types usually, but here we can just map any)
            $typeId = $booking->property->property_type_id;
            $allRooms = Property::where('property_type_id', $typeId)->orderBy('id')->get();
            $newRoomAssigned = null;

            foreach ($allRooms as $room) {
                if ($room->status !== 'available' || $room->id === $booking->property_id) continue;
                
                $roomAvailable = true;
                foreach ($booking->schedules as $schedule) {
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
                    ->where('start_time', '<', $schedule->end_time)
                    ->where('end_time', '>', $schedule->start_time)
                    ->exists();

                    if ($conflicting) {
                        $roomAvailable = false;
                        break;
                    }
                }

                if ($roomAvailable) {
                    $newRoomAssigned = $room->id;
                    break;
                }
            }

            if ($newRoomAssigned) {
                $booking->property_id = $newRoomAssigned;
            } else {
                return response()->json([
                    'message' => 'Pembayaran ditolak. Kapasitas property sudah penuh karena pengguna lain telah membayar lebih dulu.'
                ], 409);
            }
        }

        $booking->status = 'scheduled';
        $booking->save();

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil. Status booking menjadi scheduled.',
            'data' => $booking->load('property.type', 'schedules')
        ]);
    }
}
