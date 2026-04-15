<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Property;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Booking::with('property.type', 'user', 'type', 'schedules');

        // Search by booking_code
        if ($request->has('search')) {
            $query->where('booking_code', 'ilike', '%' . $request->search . '%');
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by property_type_id
        if ($request->has('property_type_id')) {
            $query->whereHas('property', function ($q) use ($request) {
                $q->where('property_type_id', $request->property_type_id);
            });
        }

        $query->orderBy('created_at', 'desc');

        $perPage = (int) $request->get('per_page', 10);
        $bookings = $query->paginate($perPage);

        return response()->json($bookings);
    }

    private function isRoomAvailable($propertyId, $startTime, $endTime, $excludeBookingId = null)
    {
        $query = \App\Models\BookingSchedule::whereHas('booking', function ($q) use ($propertyId, $excludeBookingId) {
            $q->where('property_id', $propertyId)
              ->where(function ($qStatus) {
                  $qStatus->whereNotIn('status', ['cancelled', 'finished', 'booked'])
                          ->orWhere(function ($qBooked) {
                              $qBooked->where('status', 'booked')
                                      ->where('payment_time_limit', '>=', now());
                          });
              });
            if ($excludeBookingId) {
                $q->where('id', '!=', $excludeBookingId);
            }
        })
        ->where('start_time', '<', $endTime)
        ->where('end_time', '>', $startTime);

        return !$query->exists();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'required|string|max:20',
            'institution' => 'nullable|string|max:255',
            'schedules' => 'required|array|min:1',
            'schedules.*.start_time' => 'required|date',
            'schedules.*.end_time' => 'required|date|after:schedules.*.start_time',
            'status' => 'nullable|string|in:booked,scheduled,in_use,finished,cancelled'
        ]);

        $property = Property::find($validated['property_id']);
        $propertyTypeId = $property->property_type_id;

        // Validasi Aturan Jam Operasional & Overlap per Jadwal
        foreach ($validated['schedules'] as $schedule) {
            $start = Carbon::parse($schedule['start_time']);
            $end = Carbon::parse($schedule['end_time']);

            if (!$property->type->is_continuous_booking) {
                // Disjoint schedule (Ruang Kelas / Meeting)
                if ($start->format('H:i') < '06:00') {
                    // Start minimum at 06:00 (or whatever business rule applies)
                }
            } else {
                // Continuous schedule (Kamar Inap)
                // Start minimum at 14:00 (Check-in), Check-out at 12:00
            }

            if (!$this->isRoomAvailable($validated['property_id'], $schedule['start_time'], $schedule['end_time'])) {
                return response()->json(['message' => 'Ruangan sudah dipesan pada jadwal tanggal dan jam tersebut'], 409);
            }
        }
        
        $validated['user_id'] = $request->user()->id;

        $booking = Booking::create($validated);
        foreach ($validated['schedules'] as $schedule) {
            $booking->schedules()->create($schedule);
        }
        
        $booking->load('schedules');
        return response()->json($booking, 201);
    }

    public function show($id)
    {
        $booking = Booking::with('property.type', 'user', 'type', 'schedules')->find($id);

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
            'schedules' => 'sometimes|array|min:1',
            'schedules.*.start_time' => 'required_with:schedules|date',
            'schedules.*.end_time' => 'required_with:schedules|date|after:schedules.*.start_time',
            'status' => 'sometimes|string|in:booked,scheduled,in_use,finished,cancelled'
        ]);

        if (isset($validated['schedules'])) {
            $propertyId = $validated['property_id'] ?? $booking->property_id;
            foreach ($validated['schedules'] as $schedule) {
                if (!$this->isRoomAvailable($propertyId, $schedule['start_time'], $schedule['end_time'], $booking->id)) {
                    return response()->json(['message' => 'Ruangan sudah dipesan pada jadwal tanggal dan jam tersebut'], 409);
                }
            }
        }

        $booking->update($validated);
        
        if (isset($validated['schedules'])) {
            $booking->schedules()->delete();
            foreach ($validated['schedules'] as $schedule) {
                $booking->schedules()->create($schedule);
            }
        }
        
        $booking->load('schedules');
        return response()->json($booking);
    }

    public function destroy($id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $booking->schedules()->delete();
        $booking->delete();

        return response()->json(['message' => 'Booking deleted successfully']);
    }

    /**
     * Monitoring: filter bookings by period (daily/weekly/monthly).
     * GET /bookings/monitoring?period=daily|weekly|monthly&date=YYYY-MM-DD&status=scheduled
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

        $bookings = Booking::with('property.type', 'schedules', 'user')
            ->whereHas('schedules', function ($q) use ($startDate, $endDate) {
                $q->where('start_time', '<', $endDate)
                  ->where('end_time', '>', $startDate);
            });

        if ($request->has('status')) {
            $bookings->where('status', $request->status);
        }

        $bookings = $bookings->orderBy('created_at', 'desc')->get();

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
     * GET /bookings/calendar?mode=monthly|weekly&date=YYYY-MM-DD
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
        $schedules = \App\Models\BookingSchedule::with('booking.property')
            ->whereHas('booking', function ($q) {
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

            // Count unique properties used on this date
            $propertyIds = $schedules->filter(function ($schedule) use ($dayStart, $dayEnd) {
                return Carbon::parse($schedule->start_time)->lt($dayEnd)
                    && Carbon::parse($schedule->end_time)->gt($dayStart);
            })->pluck('booking.property_id')->unique();

            $dates[] = [
                'date'           => $dateStr,
                'property_count' => $propertyIds->count(),
                'booking_count'  => $schedules->filter(function ($schedule) use ($dayStart, $dayEnd) {
                    return Carbon::parse($schedule->start_time)->lt($dayEnd)
                        && Carbon::parse($schedule->end_time)->gt($dayStart);
                })->pluck('booking_id')->unique()->count(),
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
     * GET /bookings/calendar/{date}
     */
    public function calendarDetail($date)
    {
        $targetDate = Carbon::parse($date);
        $dayStart = $targetDate->copy()->startOfDay();
        $dayEnd   = $targetDate->copy()->endOfDay();

        $bookings = Booking::with('property.type', 'schedules', 'user')
            ->where(function ($qStatus) {
                $qStatus->whereNotIn('status', ['cancelled', 'booked'])
                        ->orWhere(function ($qBooked) {
                            $qBooked->where('status', 'booked')
                                    ->where('payment_time_limit', '>=', now());
                        });
            })
            ->whereHas('schedules', function ($q) use ($dayStart, $dayEnd) {
                $q->where('start_time', '<', $dayEnd)
                  ->where('end_time', '>', $dayStart);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'date'  => $targetDate->toDateString(),
            'total' => $bookings->count(),
            'data'  => $bookings,
        ]);
    }
}
