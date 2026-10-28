<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Services\DivarPlaces;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function index(DivarPlaces $places): JsonResponse
    {
        return response()->json([
            'provinces' => $places->provinces(),
        ]);
    }

    public function districts(DivarPlaces $places, City $city): JsonResponse
    {
        return response()->json([
            'districts' => $places->districts($city),
        ]);
    }
}
