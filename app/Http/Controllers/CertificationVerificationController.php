<?php

namespace App\Http\Controllers;

use App\Models\CertifiedPerson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CertificationVerificationController extends Controller
{
    public function verify(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2|max:255',
        ]);

        $q = trim($request->input('q'));

        Log::info('Certification verify hit', ['q' => $q]);

        // Search logic:
        // - certification_number: EXACT
        // - name: EXACT (case-insensitive)
        // - contractor.company_name: PARTIAL

        $results = CertifiedPerson::query()
            ->with('contractor')
            ->where(function ($query) use ($q) {

                // Exact certification number
                $query->where('certification_number', $q)

                    // Exact name (case-insensitive)
                    ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($q)])

                    // Partial company name
                    ->orWhereHas('contractor', function ($cq) use ($q) {
                        $cq->where('company_name', 'like', "%{$q}%");
                    });
            })
            ->limit(25)
            ->get();

        if ($results->isEmpty()) {
            return response()->json([
                'found' => false,
                'query' => $q,
                'match_type' => 'none',
                'results' => [],
                'message' => 'No matching certification found.',
                'suggestions' => [
                    'Certification number must match exactly.',
                    'Full name must match exactly.',
                    'Try searching by company name instead.',
                ],
            ], 404); // change to 200 if preferred
        }

        return response()->json([
            'found' => true,
            'query' => $q,
            'match_type' => 'exact_or_company_partial',
            'results' => $results->map(fn ($p) => $this->payload($p))->values(),
            'message' => null,
        ]);
    }

    private function payload($p): array
    {
        return [
            'certified_person' => [
                'name' => $p->name,
                'certification_number' => $p->certification_number,
            ],
            'contractor' => [
                'company_name' => $p->contractor?->company_name,
                'city' => $p->contractor?->city,
                'state' => $p->contractor?->state,
                'service_area' => $p->contractor?->service_area,
            ],
        ];
    }
}
