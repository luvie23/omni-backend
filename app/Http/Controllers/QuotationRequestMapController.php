<?php

namespace App\Http\Controllers;

use App\Models\QuotationRequest;
use Illuminate\Http\Request;

class QuotationRequestMapController extends Controller
{
    public function index(Request $request)
    {
        $query = QuotationRequest::query()
            ->join('zip_codes', 'quotation_requests.zip', '=', 'zip_codes.zip')
            ->select([
                'quotation_requests.id',
                'quotation_requests.name',
                'quotation_requests.company_name',
                'quotation_requests.city',
                'quotation_requests.state',
                'quotation_requests.zip',
                'quotation_requests.status',
                'quotation_requests.created_at',
                'zip_codes.latitude',
                'zip_codes.longitude',
            ])
            ->whereNotNull('zip_codes.latitude')
            ->whereNotNull('zip_codes.longitude');

        // Date filters
        if ($request->filled('start_date')) {
            $query->whereDate('quotation_requests.created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('quotation_requests.created_at', '<=', $request->end_date);
        }

        // ✅ Status filter (supports multiple)
        if ($request->filled('status')) {
            $statuses = explode(',', $request->status);
            $query->whereIn('quotation_requests.status', $statuses);
        }

        $requests = $query
            ->orderBy('quotation_requests.created_at', 'desc')
            ->get()
            ->map(fn ($request) => [
                'id' => $request->id,
                'name' => $request->name,
                'company_name' => $request->company_name,
                'city' => $request->city,
                'state' => $request->state,
                'zip' => $request->zip,
                'status' => $request->status,
                'created_at' => $request->created_at,
                'latitude' => (float) $request->latitude,
                'longitude' => (float) $request->longitude,
            ]);

        return response()->json($requests);
    }
}
