<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PropertyType;
use Illuminate\Http\Request;

class PropertyTypeController extends Controller
{
    /**
     * List all property types.
     */
    public function index()
    {
        $types = PropertyType::all();
        return response()->json(['data' => $types]);
    }
}
