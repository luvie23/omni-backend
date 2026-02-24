<?php

namespace App\Http\Controllers;

use App\Models\CertifiedPerson;
use App\Models\Contractor;
use Illuminate\Http\Request;

class CertifiedPersonAdminController extends Controller
{
    /**
     * Resolve contractor by contractors.id from the route param {contractor}.
     * No route-model binding used.
     */
    private function contractorFromRoute(Request $request): Contractor
    {
        $contractorId = (int) $request->route('contractor');

        $contractor = Contractor::find($contractorId);

        logger()->info('Admin CertifiedPerson manual contractor lookup', [
            'route_contractor_raw' => $request->route('contractor'),
            'interpreted_contractor_id' => $contractorId,
            'found' => (bool) $contractor,
            'contractor_id' => $contractor?->id,
            'contractor_user_id' => $contractor?->user_id,
        ]);

        abort_unless($contractor, 404, 'Contractor not found.');

        return $contractor;
    }

    /**
     * GET /api/admin/contractors/{contractor}/certified-people
     * where {contractor} is contractors.id
     */
    public function index(Request $request)
    {
        $contractor = $this->contractorFromRoute($request);

        return response()->json([
            'data' => $contractor->certifiedPeople()->latest()->get(),
        ]);
    }

    /**
     * POST /api/admin/contractors/{contractor}/certified-people
     * Body: { "name": "..." }
     * where {contractor} is contractors.id
     */
    public function store(Request $request)
    {
        $contractor = $this->contractorFromRoute($request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $certNumber = $this->generateCertificateNumber();

        // extra safety: retry on extremely unlikely collisions
        $attempts = 0;
        while (CertifiedPerson::where('certification_number', $certNumber)->exists()) {
            $attempts++;
            if ($attempts > 5) {
                abort(500, 'Could not generate a unique certification number. Please try again.');
            }
            $certNumber = $this->generateCertificateNumber();
        }

        $person = $contractor->certifiedPeople()->create([
            'name' => $data['name'],
            'certification_number' => $certNumber,
        ]);

        return response()->json([
            'message' => 'Certified person created successfully.',
            'data' => $person,
        ], 201);
    }

    /**
     * PATCH /api/admin/certified-people/{certifiedPerson}
     */
    public function update(Request $request, CertifiedPerson $certifiedPerson)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'certification_number' => 'sometimes|nullable|string|max:255',
        ]);

        $certifiedPerson->update($data);

        return response()->json([
            'message' => 'Certified person updated successfully.',
            'data' => $certifiedPerson,
        ]);
    }

    /**
     * DELETE /api/admin/certified-people/{certifiedPerson}
     */
    public function destroy(CertifiedPerson $certifiedPerson)
    {
        $certifiedPerson->delete();

        return response()->json([
            'message' => 'Certified person deleted successfully.',
        ]);
    }

    /**
     * Generate: OMNI-YYMM-XXXXXX
     * Example: OMNI-2602-K7M9Q2
     */
    private function generateCertificateNumber(): string
    {
        return 'OMNI-' . now()->format('ym') . '-' . $this->randomCode(6);
    }

    /**
     * Random code from readable charset (no O/0, I/1, L).
     */
    private function randomCode(int $length = 6): string
    {
        $characters = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $code;
    }
}
