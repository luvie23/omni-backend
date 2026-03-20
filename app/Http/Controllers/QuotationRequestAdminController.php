<?php

namespace App\Http\Controllers;

use App\Mail\QuotationRequestSentToContractorMail;
use App\Models\QuotationRequest;
use App\Models\Setting;
use App\Models\ZipCode;
use App\Models\Contractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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

        $quote = QuotationRequest::with(['contractors.user'])->findOrFail($quotationRequestId);

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
        ->leftJoin('contractor_quotation_request as cqr', function ($join) use ($quotationRequestId) {
            $join->on('cqr.contractor_id', '=', 'contractors.id')
                ->where('cqr.quotation_request_id', '=', $quotationRequestId);
        })
        ->selectRaw("
            contractors.id,
            contractors.user_id,
            users.name,
            users.email,
            contractors.company_name,
            contractors.city,
            contractors.state,
            contractors.zip,
            cqr.sent_at,
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
                'already_sent' => !is_null($contractor->sent_at),
                'sent_at' => $contractor->sent_at,
                'contractor_profile' => [
                    'company_name' => $contractor->company_name,
                    'city' => $contractor->city,
                    'state' => $contractor->state,
                    'zip' => $contractor->zip,
                ],
            ];
        })->values(),
    ]);
}

    public function sendToContractor(Request $request)
    {
        $quotationRequestId = (int) $request->route('quotation_request');
        $contractorId = (int) $request->route('contractor');

        $quote = QuotationRequest::findOrFail($quotationRequestId);
        $contractor = Contractor::with('user')->findOrFail($contractorId);

        if (!$contractor->user || !$contractor->user->email) {
            return response()->json([
                'message' => 'Contractor email not found.',
            ], 422);
        }

        $alreadySent = $quote->contractors()
            ->where('contractors.id', $contractor->id)
            ->exists();


        Mail::to($contractor->user->email)->send(
            new QuotationRequestSentToContractorMail($quote, $contractor)
        );

        if ($alreadySent) {

            $quote->contractors()->updateExistingPivot($contractor->id, [
                'sent_at' => now(),
                'updated_at' => now(),
            ]);
        } else {

            $quote->contractors()->attach($contractor->id, [
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($quote->status === 'new') {
            $quote->update([
                'status' => 'contacted',
            ]);
        }

        return response()->json([
            'message' => $alreadySent
                ? 'Quotation request resent successfully.'
                : 'Quotation request sent successfully.',
            'data' => [
                'quotation_request_id' => $quote->id,
                'contractor' => [
                    'id' => $contractor->id,
                    'name' => $contractor->user->name,
                    'email' => $contractor->user->email,
                    'company_name' => $contractor->company_name,
                ],
                'resent' => $alreadySent,
            ],
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
            'sent_contractors' => $quote->relationLoaded('contractors')
                ? $quote->contractors->map(function ($contractor) {
                    return [
                        'id' => $contractor->id,
                        'name' => $contractor->user?->name,
                        'email' => $contractor->user?->email,
                        'company_name' => $contractor->company_name,
                        'sent_at' => $contractor->pivot->sent_at,
                    ];
                })->values()
                : [],
        ];
    }
}
