<?php

namespace App\Http\Controllers;

use App\Models\QuotationRequest;
use App\Models\Setting;
use App\Models\ZipCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuotationRequestAdminController extends Controller
{
    public function index(Request $request)
    {
      $query = QuotationRequest::query()->latest();

        if ($status = $request->string('status')->toString()) {
            if (in_array($status, ['new', 'contacted', 'closed'], true)) {
                $query->where('status', $status);
            }
        }

        if ($search = trim($request->string('search')->toString())) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('state', 'like', "%{$search}%")
                    ->orWhere('zip', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('details', 'like', "%{$search}%");
            });
        }

        $quotes = $query->paginate($request->integer('per_page', 15));

        return response()->json(
            $quotes->through(fn ($quote) => $this->payload($quote))
        );
    }

    public function show(Request $request)
    {
        $quotationRequestId = (int) $request->route('quotation_request');

        $quote = QuotationRequest::findOrFail($quotationRequestId);

        return response()->json([
            'data' => $this->payload($quote),
        ]);
    }

    public function update(Request $request)
    {
        $quotationRequestId = (int) $request->route('quotation_request');

        $quote = QuotationRequest::findOrFail($quotationRequestId);

        $data = $request->validate([
            'status' => 'sometimes|required|string|in:new,contacted,closed',
        ]);

        $quote->update($data);

        return response()->json([
            'message' => 'Quotation request updated successfully.',
            'data' => $this->payload($quote->fresh()),
        ]);
    }

    public function destroy(Request $request)
    {
        $quotationRequestId = (int) $request->route('quotation_request');

        $quote = QuotationRequest::findOrFail($quotationRequestId);

        $quote->delete();

        return response()->json([
            'message' => 'Quotation request deleted successfully.',
        ]);
    }

    public function nearbyContractors(Request $request)
    {
        $quotationRequestId = (int) $request->route('quotation_request');

        $quote = QuotationRequest::findOrFail($quotationRequestId);

        $requestZip = ZipCode::find($quote->zip);

        if (!$requestZip) {
            return response()->json([
                'message' => 'ZIP code not found in zip_codes table.',
                'data' => [],
            ], 404);
        }

        $lat = (float) $requestZip->latitude;
        $lng = (float) $requestZip->longitude;

        $radiusMiles = (int) (Setting::where('key', 'contractor_search_radius_miles')->value('value') ?? 100);
        $earthRadiusMiles = 3959;

        $contractors = DB::table('contractors')
            ->join('users', 'users.id', '=', 'contractors.user_id')
            ->join('zip_codes as contractor_zip', 'contractor_zip.zip', '=', 'contractors.zip')
            ->selectRaw("
                contractors.id,
                contractors.user_id,
                users.name,
                users.email,
                contractors.company_name,
                contractors.city,
                contractors.state,
                contractors.zip,
                (
                    {$earthRadiusMiles} * acos(
                        cos(radians(?)) *
                        cos(radians(contractor_zip.latitude)) *
                        cos(radians(contractor_zip.longitude) - radians(?)) +
                        sin(radians(?)) *
                        sin(radians(contractor_zip.latitude))
                    )
                ) as distance_miles
            ", [$lat, $lng, $lat])
            ->having('distance_miles', '<=', $radiusMiles)
            ->orderBy('distance_miles')
            ->get();

        return response()->json([
            'quotation_request' => [
                'id' => $quote->id,
                'zip' => $quote->zip,
            ],
            'radius_miles' => $radiusMiles,
            'data' => $contractors->map(function ($contractor) {
                return [
                    'id' => $contractor->id,
                    'user_id' => $contractor->user_id,
                    'name' => $contractor->name,
                    'email' => $contractor->email,
                    'distance_miles' => round((float) $contractor->distance_miles, 2),
                    'contractor_profile' => [
                        'company_name' => $contractor->company_name,
                        'city' => $contractor->city,
                        'state' => $contractor->state,
                        'zip' => $contractor->zip,
                    ],
                ];
            }),
        ]);
    }

    private function payload(QuotationRequest $quote): array
    {
        return [
            'id' => $quote->id,
            'name' => $quote->name,
            'company_name' => $quote->company_name,
            'address' => $quote->address,
            'city' => $quote->city,
            'state' => $quote->state,
            'zip' => $quote->zip,
            'phone_number' => $quote->phone_number,
            'email' => $quote->email,
            'details' => $quote->details,
            'status' => $quote->status,
            'created_at' => $quote->created_at,
            'updated_at' => $quote->updated_at,
        ];
    }
}
