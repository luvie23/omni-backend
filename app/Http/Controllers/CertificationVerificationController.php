<?php

namespace App\Http\Controllers;

use App\Models\CertifiedPerson;
use App\Models\Contractor;
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
        $qNorm = $this->normalize($q);

        // Log::info('Certification verify hit', ['q' => $q]);

        // 1) Exact match: certification number
        $certMatches = CertifiedPerson::query()
            ->with('contractor')
            ->where('certification_number', $q)
            ->get();

        if ($certMatches->isNotEmpty()) {
            return response()->json([
                'found' => true,
                'query' => $q,
                'match_type' => 'certification_number_exact',
                'results' => $certMatches->map(fn ($p) => $this->payload($p))->values(),
                'message' => null,
            ]);
        }

        // 2) Exact match: person name (case-insensitive)
        $nameMatches = CertifiedPerson::query()
            ->with('contractor')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($q)])
            ->limit(25)
            ->get();

        if ($nameMatches->isNotEmpty()) {
            return response()->json([
                'found' => true,
                'query' => $q,
                'match_type' => 'person_name_exact',
                'results' => $nameMatches->map(fn ($p) => $this->payload($p))->values(),
                'message' => null,
            ]);
        }

        // 3) Company query: pick the closest ONE company, return all its certified persons
        // Pull a reasonable set of candidate companies first (so levenshtein stays cheap).
        $candidates = Contractor::query()
            ->select(['id', 'company_name'])
            ->whereRaw(
                "LOWER(company_name) REGEXP ?",
                ['(^|[[:space:][:punct:]])' . preg_quote(mb_strtolower($q), '/') . '([[:space:][:punct:]]|$)']
            )
            ->limit(50)
            ->get();

        if ($candidates->isEmpty()) {
            // (Optional) fallback: if user typo doesn't match LIKE, try a broader net:
            // $candidates = Contractor::query()->select(['id','company_name'])->limit(200)->get();
            // ...but that can be expensive on big datasets.

            return $this->noMatchResponse($q);
        }

        $best = null;
        $bestScore = null;

        foreach ($candidates as $c) {
            $nameNorm = $this->normalize($c->company_name);

            // Lower score = closer match
            $score = levenshtein($qNorm, $nameNorm);

            // Tie-breaker: prefer shorter company name (often closer)
            if ($best === null || $score < $bestScore || ($score === $bestScore && strlen($nameNorm) < strlen($this->normalize($best->company_name)))) {
                $best = $c;
                $bestScore = $score;
            }
        }

        if (!$best) {
            return $this->noMatchResponse($q);
        }

        $companyPeople = CertifiedPerson::query()
            ->with('contractor')
            ->where('contractor_id', $best->id)
            ->limit(25)
            ->get();

        if ($companyPeople->isEmpty()) {
            return response()->json([
                'found' => false,
                'query' => $q,
                'match_type' => 'company_best_but_no_people',
                'company' => [
                    'id' => $best->id,
                    'company_name' => $best->company_name,
                ],
                'results' => [],
                'message' => 'Company matched, but no certified persons found for that company.',
            ], 404);
        }

        return response()->json([
            'found' => true,
            'query' => $q,
            'match_type' => 'company_best',
            'company' => [
                'id' => $best->id,
                'company_name' => $best->company_name,
                'distance' => $bestScore, // useful for debugging / tuning
            ],
            'results' => $companyPeople->map(fn ($p) => $this->payload($p))->values(),
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

    private function noMatchResponse(string $q)
    {
        return response()->json([
            'found' => false,
            'query' => $q,
            'match_type' => 'none',
            'results' => [],
            'message' => 'No matching certification found.',
            'suggestions' => [
                'Certification number must match exactly.',
                'Full name must match exactly.',
                'Try searching by company name.',
            ],
        ], 200); // change to 200 if you prefer
    }

    private function normalize(?string $s): string
    {
        $s = $s ?? '';
        $s = mb_strtolower($s);
        // Remove common punctuation and extra spaces for better matching
        $s = preg_replace('/[^a-z0-9\s]/i', '', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }
}
