<?php

namespace App\Http\Controllers;


use App\Models\Contractor;

class ContractorMapController extends Controller
{
    public function index()
    {
        $contractors = Contractor::query()
            ->select([
                'id',
                'user_id',
                'company_name',
                'city',
                'state',
                'zip',
            ])
            ->with(['zipCode:zip,latitude,longitude'])
            ->get()
            ->filter(function ($contractor) {
                return $contractor->zipCode
                    && $contractor->zipCode->latitude
                    && $contractor->zipCode->longitude;
            })
            ->values()
            ->map(function ($contractor) {
                return [
                    'id' => $contractor->id,
                    'company_name' => $contractor->company_name,
                    'city' => $contractor->city,
                    'state' => $contractor->state,
                    'zip' => $contractor->zip,
                    'latitude' => (float) $contractor->zipCode->latitude,
                    'longitude' => (float) $contractor->zipCode->longitude,
                ];
            });

        return response()->json($contractors);
    }
}
