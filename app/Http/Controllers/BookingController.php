<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingSchedule;
use App\Models\Property;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Booking::with('items.property.type', 'items.schedules', 'user');

        // Search by booking_code
        if ($request->has('search')) {
            $query->where('booking_code', 'ilike', '%' . $request->search . '%');
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by property_type_id (booking contains at least one item of this type)
        if ($request->has('property_type_id')) {
            $query->whereHas('items.property', function ($q) use ($request) {
                $q->where('property_type_id', $request->property_type_id);
            });
        }

        // Filter by property_id (booking contains specific property)
        if ($request->has('property_id')) {
            $query->whereHas('items', function ($q) use ($request) {
                $q->where('property_id', $request->property_id);
            });
        }

        // Sorting
        $allowedSortFields = ['start_date', 'end_date', 'created_at', 'status', 'booking_code'];
        $sortBy = in_array($request->get('sort_by'), $allowedSortFields) ? $request->get('sort_by') : 'created_at';
        $sortOrder = $request->get('sort_order') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = (int) $request->get('per_page', 10);
        $bookings = $query->paginate($perPage);

        return response()->json($bookings);
    }

    /**
     * Check if a specific property is available during a given time range.
     */
    private function isRoomAvailable($propertyId, $startTime, $endTime, $excludeBookingId = null)
    {
        $query = BookingSchedule::whereHas('item', function ($q) use ($propertyId, $excludeBookingId) {
            $q->where('property_id', $propertyId);
            if ($excludeBookingId) {
                $q->whereHas('booking', function ($bq) use ($excludeBookingId) {
                    $bq->where('id', '!=', $excludeBookingId)
                       ->where(function ($qStatus) {
                           $qStatus->whereNotIn('status', ['cancelled', 'finished', 'booked'])
                                   ->orWhere(function ($qBooked) {
                                       $qBooked->where('status', 'booked')
                                               ->where('payment_time_limit', '>=', now());
                                   });
                       });
                });
            } else {
                $q->whereHas('booking', function ($bq) {
                    $bq->where(function ($qStatus) {
                        $qStatus->whereNotIn('status', ['cancelled', 'finished', 'booked'])
                                ->orWhere(function ($qBooked) {
                                    $qBooked->where('status', 'booked')
                                            ->where('payment_time_limit', '>=', now());
                                });
                    });
                });
            }
        })
        ->where('start_time', '<', $endTime)
        ->where('end_time', '>', $startTime);

        return !$query->exists();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'contact_name'  => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'required|string|max:20',
            'institution'   => 'nullable|string|max:255',
            'status'        => 'nullable|string|in:booked,scheduled,in_use,finished,cancelled',
            'items'         => 'required|array|min:1',
            'items.*.property_id' => 'required|exists:properties,id',
            'items.*.schedules'   => 'required|array|min:1',
            'items.*.schedules.*.start_time' => 'required|date',
            'items.*.schedules.*.end_time'   => 'required|date|after:items.*.schedules.*.start_time',
        ]);

        // Validate availability for every item
        foreach ($validated['items'] as $itemData) {
            $property = Property::with('type')->find($itemData['property_id']);
            foreach ($itemData['schedules'] as $schedule) {
                if (!$this->isRoomAvailable($itemData['property_id'], $schedule['start_time'], $schedule['end_time'])) {
                    return response()->json([
                        'message' => 'Ruangan "' . $property->name . '" sudah dipesan pada jadwal tanggal dan jam tersebut'
                    ], 409);
                }
            }
        }

        // Create booking
        $booking = Booking::create([
            'contact_name'  => $validated['contact_name'],
            'contact_email' => $validated['contact_email'],
            'contact_phone' => $validated['contact_phone'],
            'institution'   => $validated['institution'] ?? null,
            'status'        => $validated['status'] ?? 'scheduled',
            'user_id'       => $request->user()->id,
            'source'        => 'flutter',
        ]);

        // Create items with schedules
        foreach ($validated['items'] as $itemData) {
            $item = $booking->items()->create([
                'property_id' => $itemData['property_id'],
            ]);
            foreach ($itemData['schedules'] as $schedule) {
                $item->schedules()->create($schedule);
            }
        }

        $booking->load('items.property.type', 'items.schedules');
        return response()->json($booking, 201);
    }

    public function show($id)
    {
        $booking = Booking::with('items.property.type', 'items.schedules', 'user')->find($id);

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
            'contact_name'  => 'sometimes|string|max:255',
            'contact_email' => 'sometimes|email|max:255',
            'contact_phone' => 'sometimes|string|max:20',
            'institution'   => 'nullable|string|max:255',
            'status'        => 'sometimes|string|in:booked,scheduled,in_use,finished,cancelled',
            'items'         => 'sometimes|array|min:1',
            'items.*.property_id' => 'required_with:items|exists:properties,id',
            'items.*.schedules'   => 'required_with:items|array|min:1',
            'items.*.schedules.*.start_time' => 'required_with:items|date',
            'items.*.schedules.*.end_time'   => 'required_with:items|date',
        ]);

        if (isset($validated['items'])) {
            foreach ($validated['items'] as $itemData) {
                foreach ($itemData['schedules'] as $schedule) {
                    if (!$this->isRoomAvailable($itemData['property_id'], $schedule['start_time'], $schedule['end_time'], $booking->id)) {
                        $prop = Property::find($itemData['property_id']);
                        return response()->json(['message' => 'Ruangan "' . $prop->name . '" sudah dipesan pada jadwal tanggal dan jam tersebut'], 409);
                    }
                }
            }
        }

        // Update booking fields (without items)
        $bookingFields = collect($validated)->except('items')->toArray();
        $booking->update($bookingFields);

        // Replace items if provided
        if (isset($validated['items'])) {
            // Delete old items (cascades to schedules)
            $booking->items()->delete();
            foreach ($validated['items'] as $itemData) {
                $item = $booking->items()->create([
                    'property_id' => $itemData['property_id'],
                ]);
                foreach ($itemData['schedules'] as $schedule) {
                    $item->schedules()->create($schedule);
                }
            }
        }

        $booking->load('items.property.type', 'items.schedules');
        return response()->json($booking);
    }

    public function destroy($id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        // Items and schedules cascade delete via FK
        $booking->delete();

        return response()->json(['message' => 'Booking deleted successfully']);
    }

    /**
     * Monitoring: filter bookings by period (daily/weekly/monthly).
     */
    public function monitoring(Request $request)
    {
        $request->validate([
            'period' => 'required|in:daily,weekly,monthly',
            'date'   => 'nullable|date',
            'status' => 'nullable|string|in:booked,scheduled,in_use,finished,cancelled',
        ]);

        $period = $request->period;
        $date = Carbon::parse($request->date ?? now());

        switch ($period) {
            case 'daily':
                $startDate = $date->copy()->startOfDay();
                $endDate   = $date->copy()->endOfDay();
                break;
            case 'weekly':
                $startDate = $date->copy()->startOfWeek();
                $endDate   = $date->copy()->endOfWeek();
                break;
            case 'monthly':
                $startDate = $date->copy()->startOfMonth();
                $endDate   = $date->copy()->endOfMonth();
                break;
        }

        $bookings = Booking::with('items.property.type', 'items.schedules', 'user')
            ->whereHas('items.schedules', function ($q) use ($startDate, $endDate) {
                $q->where('start_time', '<', $endDate)
                  ->where('end_time', '>', $startDate);
            });

        if ($request->has('status')) {
            $bookings->where('status', $request->status);
        }

        $bookings = $bookings->orderBy('start_date', 'desc')->get();

        return response()->json([
            'period'     => $period,
            'start_date' => $startDate->toDateString(),
            'end_date'   => $endDate->toDateString(),
            'total'      => $bookings->count(),
            'data'       => $bookings,
        ]);
    }

    /**
     * Calendar: show property usage count per date for a month/week.
     */
    public function calendar(Request $request)
    {
        $request->validate([
            'mode' => 'required|in:monthly,weekly',
            'date' => 'nullable|date',
        ]);

        $mode = $request->mode;
        $date = Carbon::parse($request->date ?? now());

        if ($mode === 'monthly') {
            $startDate = $date->copy()->startOfMonth();
            $endDate   = $date->copy()->endOfMonth();
        } else {
            $startDate = $date->copy()->startOfWeek();
            $endDate   = $date->copy()->endOfWeek();
        }

        // Get all schedules in range with active bookings
        $schedules = BookingSchedule::with('item.booking', 'item.property')
            ->whereHas('item.booking', function ($q) {
                $q->where(function ($qStatus) {
                    $qStatus->whereNotIn('status', ['cancelled', 'booked'])
                            ->orWhere(function ($qBooked) {
                                $qBooked->where('status', 'booked')
                                        ->where('payment_time_limit', '>=', now());
                            });
                });
            })
            ->where('start_time', '<', $endDate->copy()->endOfDay())
            ->where('end_time', '>', $startDate->copy()->startOfDay())
            ->get();

        // Build per-date summary
        $dates = [];
        $cursor = $startDate->copy();
        while ($cursor->lte($endDate)) {
            $dateStr = $cursor->toDateString();
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd   = $cursor->copy()->endOfDay();

            $activeSchedules = $schedules->filter(function ($schedule) use ($dayStart, $dayEnd) {
                return Carbon::parse($schedule->start_time)->lt($dayEnd)
                    && Carbon::parse($schedule->end_time)->gt($dayStart);
            });

            $dates[] = [
                'date'           => $dateStr,
                'property_count' => $activeSchedules->pluck('item.property_id')->unique()->count(),
                'booking_count'  => $activeSchedules->pluck('item.booking_id')->unique()->count(),
            ];

            $cursor->addDay();
        }

        return response()->json([
            'mode'       => $mode,
            'start_date' => $startDate->toDateString(),
            'end_date'   => $endDate->toDateString(),
            'dates'      => $dates,
        ]);
    }

    /**
     * Calendar Detail: list bookings for a specific date.
     */
    public function calendarDetail($date)
    {
        $targetDate = Carbon::parse($date);
        $dayStart = $targetDate->copy()->startOfDay();
        $dayEnd   = $targetDate->copy()->endOfDay();

        $bookings = Booking::with('items.property.type', 'items.schedules', 'user')
            ->where(function ($qStatus) {
                $qStatus->whereNotIn('status', ['cancelled', 'booked'])
                        ->orWhere(function ($qBooked) {
                            $qBooked->where('status', 'booked')
                                    ->where('payment_time_limit', '>=', now());
                        });
            })
            ->whereHas('items.schedules', function ($q) use ($dayStart, $dayEnd) {
                $q->where('start_time', '<', $dayEnd)
                  ->where('end_time', '>', $dayStart);
            })
            ->orderBy('start_date', 'desc')
            ->get();

        return response()->json([
            'date'  => $targetDate->toDateString(),
            'total' => $bookings->count(),
            'data'  => $bookings,
        ]);
    }
}
