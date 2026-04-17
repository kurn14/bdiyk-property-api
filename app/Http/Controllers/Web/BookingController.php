<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingSchedule;
use App\Models\Property;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BookingController extends Controller
{
    /**
     * Helper: check if a specific property is available during a time range.
     */
    private function isPropertyAvailable($propertyId, $startTime, $endTime)
    {
        return !BookingSchedule::whereHas('item', function ($q) use ($propertyId) {
            $q->where('property_id', $propertyId)
              ->whereHas('booking', function ($bq) {
                  $bq->where(function ($qStatus) {
                      $qStatus->whereNotIn('status', ['cancelled', 'finished', 'booked'])
                              ->orWhere(function ($qBooked) {
                                  $qBooked->where('status', 'booked')
                                          ->where('payment_time_limit', '>=', now());
                              });
                  });
              });
        })
        ->where('start_time', '<', $endTime)
        ->where('end_time', '>', $startTime)
        ->exists();
    }

    /**
     * Create a new booking with multiple items.
     *
     * Expected payload:
     * {
     *   "contact_name": "...",
     *   "contact_email": "...",
     *   "contact_phone": "...",
     *   "institution": "...",
     *   "items": [
     *     {
     *       "property_id": 6,      // virtual product ID or real property ID
     *       "quantity": 3,          // only for virtual (kamar) types
     *       "schedules": [
     *         { "start_time": "...", "end_time": "..." }
     *       ]
     *     },
     *     {
     *       "property_id": 1,      // real property
     *       "schedules": [
     *         { "start_time": "...", "end_time": "..." },
     *         { "start_time": "...", "end_time": "..." }
     *       ]
     *     }
     *   ]
     * }
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'contact_name'  => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'required|string|max:20',
            'institution'   => 'nullable|string|max:255',
            'items'         => 'required|array|min:1',
            'items.*.property_id' => 'required|integer|min:1',
            'items.*.quantity'    => 'nullable|integer|min:1',
            'items.*.schedules'   => 'required|array|min:1',
            'items.*.schedules.*.start_time' => 'required|date',
            'items.*.schedules.*.end_time'   => 'required|date|after:items.*.schedules.*.start_time',
        ]);

        $virtualMapping = [
            6 => 3, // Request ID 6 -> property_type_id 3 (Kamar VIP)
            7 => 4, // Request ID 7 -> property_type_id 4 (Kamar 2 Bed)
            8 => 5, // Request ID 8 -> property_type_id 5 (Kamar 3 Bed)
        ];

        // Resolve all items to actual properties
        $resolvedItems = []; // Each element: ['property' => Property, 'schedules' => [...]]

        foreach ($validated['items'] as $itemData) {
            $requestedId = $itemData['property_id'];
            $quantity = $itemData['quantity'] ?? 1;
            $schedules = $itemData['schedules'];

            if (in_array($requestedId, [1, 2, 3, 4, 5])) {
                // Specific physical property
                $property = Property::find($requestedId);
                if (!$property) {
                    return response()->json(['message' => 'Property ID ' . $requestedId . ' tidak ditemukan'], 404);
                }
                if ($property->status !== 'available') {
                    return response()->json(['message' => 'Property "' . $property->name . '" tidak tersedia (status: ' . $property->status . ')'], 422);
                }
                // Check schedule conflicts
                foreach ($schedules as $schedule) {
                    if (!$this->isPropertyAvailable($property->id, $schedule['start_time'], $schedule['end_time'])) {
                        return response()->json(['message' => 'Property "' . $property->name . '" tidak tersedia pada jadwal tersebut'], 422);
                    }
                }
                $resolvedItems[] = ['property' => $property, 'schedules' => $schedules];

            } elseif (isset($virtualMapping[$requestedId])) {
                // Virtual category: auto-assign rooms
                $targetTypeId = $virtualMapping[$requestedId];
                $allRooms = Property::where('property_type_id', $targetTypeId)->orderBy('id')->get();
                $assignedRooms = [];

                foreach ($allRooms as $room) {
                    if ($room->status !== 'available') continue;

                    $isAvailable = true;
                    foreach ($schedules as $schedule) {
                        if (!$this->isPropertyAvailable($room->id, $schedule['start_time'], $schedule['end_time'])) {
                            $isAvailable = false;
                            break;
                        }
                    }

                    if ($isAvailable) {
                        $assignedRooms[] = $room;
                        if (count($assignedRooms) >= $quantity) break;
                    }
                }

                if (count($assignedRooms) < $quantity) {
                    return response()->json([
                        'message' => 'Kapasitas kamar tidak cukup. Hanya tersedia ' . count($assignedRooms) . ' unit.'
                    ], 422);
                }

                foreach ($assignedRooms as $room) {
                    $resolvedItems[] = ['property' => $room, 'schedules' => $schedules];
                }

            } else {
                return response()->json(['message' => 'ID Properti (' . $requestedId . ') tidak valid untuk sistem eksternal.'], 422);
            }
        }

        // Create single booking with all items
        $booking = Booking::create([
            'contact_name'       => $validated['contact_name'],
            'contact_email'      => $validated['contact_email'],
            'contact_phone'      => $validated['contact_phone'],
            'institution'        => $validated['institution'] ?? null,
            'status'             => 'booked',
            'payment_time_limit' => now()->addHours((int) env('PAYMENT_TIME_LIMIT_HOURS', 2)),
            'user_id'            => 1, // Default to admin for Web API bookings
        ]);

        foreach ($resolvedItems as $resolved) {
            $item = $booking->items()->create([
                'property_id' => $resolved['property']->id,
            ]);
            foreach ($resolved['schedules'] as $schedule) {
                $item->schedules()->create($schedule);
            }
        }

        $booking->load('items.property.type', 'items.schedules');

        return response()->json(['data' => $booking], 201);
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
        $booking = Booking::with('items.property', 'items.schedules')->find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        if ($booking->status !== 'booked') {
            return response()->json([
                'message' => 'Booking status is not applicable for payment (current status: ' . $booking->status . ')'
            ], 422);
        }

        // Re-check availability for each item
        $allItemsAvailable = true;
        $failedItem = null;

        foreach ($booking->items as $item) {
            foreach ($item->schedules as $schedule) {
                $conflicting = BookingSchedule::whereHas('item', function ($q) use ($item, $booking) {
                    $q->where('property_id', $item->property_id)
                      ->whereHas('booking', function ($bq) use ($booking) {
                          $bq->where('id', '!=', $booking->id)
                             ->where(function ($qStatus) {
                                 $qStatus->whereNotIn('status', ['cancelled', 'finished', 'booked'])
                                         ->orWhere(function ($qBooked) {
                                             $qBooked->where('status', 'booked')
                                                     ->where('payment_time_limit', '>=', now());
                                         });
                             });
                      });
                })
                ->where('start_time', '<', $schedule->end_time)
                ->where('end_time', '>', $schedule->start_time)
                ->exists();

                if ($conflicting) {
                    $allItemsAvailable = false;
                    $failedItem = $item;
                    break 2;
                }
            }
        }

        if (!$allItemsAvailable && $failedItem) {
            // Attempt auto-reassign for the failed item only (kamar types)
            $typeId = $failedItem->property->property_type_id;
            $allRooms = Property::where('property_type_id', $typeId)->orderBy('id')->get();
            $newRoomAssigned = null;

            foreach ($allRooms as $room) {
                if ($room->status !== 'available' || $room->id === $failedItem->property_id) continue;

                $roomAvailable = true;
                foreach ($failedItem->schedules as $schedule) {
                    $conflicting = BookingSchedule::whereHas('item', function ($q) use ($room) {
                        $q->where('property_id', $room->id)
                          ->whereHas('booking', function ($bq) {
                              $bq->where(function ($qStatus) {
                                  $qStatus->whereNotIn('status', ['cancelled', 'finished', 'booked'])
                                          ->orWhere(function ($qBooked) {
                                              $qBooked->where('status', 'booked')
                                                      ->where('payment_time_limit', '>=', now());
                                          });
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
                $failedItem->property_id = $newRoomAssigned;
                $failedItem->save();
            } else {
                return response()->json([
                    'message' => 'Pembayaran ditolak. Property "' . $failedItem->property->name . '" sudah penuh karena pengguna lain telah membayar lebih dulu.'
                ], 409);
            }
        }

        $booking->status = 'scheduled';
        $booking->save();

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil. Status booking menjadi scheduled.',
            'data'    => $booking->load('items.property.type', 'items.schedules')
        ]);
    }
}
