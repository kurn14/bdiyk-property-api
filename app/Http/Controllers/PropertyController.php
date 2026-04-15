<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index()
    {
        $properties = Property::with('type', 'bookings')->get();
        
        // Transform the properties to include dynamically calculated status based on SRS 3.5
        $properties->each(function($property) {
            $now = now();
            $isActiveBooking = \App\Models\BookingSchedule::whereHas('booking', function ($q) use ($property) {
                $q->where('property_id', $property->id)
                  ->whereIn('status', ['scheduled', 'in_use']);
            })
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->exists();
                
            // If the manual status is maintenance or used, we keep it, otherwise calculated.
            if (!in_array($property->status, ['maintenance', 'used'])) {
                $property->status = $isActiveBooking ? 'occupied' : 'available';
            }
        });

        return response()->json($properties);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'property_type_id' => 'required|exists:property_types,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'capacity' => 'required|integer|min:1',
            'status' => 'nullable|string|in:available,occupied,used,maintenance'
        ]);

        $property = Property::create($validated);

        return response()->json($property, 201);
    }

    public function show($id)
    {
        $property = Property::with('type', 'bookings')->find($id);

        if (!$property) {
            return response()->json(['message' => 'Property not found'], 404);
        }

        $now = now();
        $isActiveBooking = \App\Models\BookingSchedule::whereHas('booking', function ($q) use ($property) {
            $q->where('property_id', $property->id)
              ->whereIn('status', ['scheduled', 'in_use']);
        })
        ->where('start_time', '<=', $now)
        ->where('end_time', '>=', $now)
        ->exists();
            
        if (!in_array($property->status, ['maintenance', 'used'])) {
            $property->status = $isActiveBooking ? 'occupied' : 'available';
        }

        return response()->json($property);
    }

    public function update(Request $request, $id)
    {
        $property = Property::find($id);

        if (!$property) {
            return response()->json(['message' => 'Property not found'], 404);
        }

        $validated = $request->validate([
            'property_type_id' => 'sometimes|exists:property_types,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'capacity' => 'sometimes|integer|min:1',
            'status' => 'sometimes|string|in:available,occupied,used,maintenance'
        ]);

        $property->update($validated);

        return response()->json($property);
    }

    public function destroy($id)
    {
        $property = Property::find($id);

        if (!$property) {
            return response()->json(['message' => 'Property not found'], 404);
        }

        $property->delete();

        return response()->json(['message' => 'Property deleted successfully']);
    }
}
