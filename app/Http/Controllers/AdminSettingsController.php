<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;

class AdminSettingsController extends Controller
{
    public function getDistance()
    {
        $setting = Setting::where('key', 'contractor_search_radius_miles')->first();

        return response()->json([
            'data' => [
                'radius_miles' => (int) $setting->value
            ]
        ]);
    }

    public function updateDistance(Request $request)
    {
        $data = $request->validate([
            'radius_miles' => 'required|integer|min:1|max:500'
        ]);

        $setting = Setting::updateOrCreate(
            ['key' => 'contractor_search_radius_miles'],
            ['value' => $data['radius_miles']]
        );

        return response()->json([
            'message' => 'Distance setting updated.',
            'data' => [
                'radius_miles' => (int) $setting->value
            ]
        ]);
    }
}
