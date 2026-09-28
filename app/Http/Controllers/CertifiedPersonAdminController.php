<?php

namespace App\Http\Controllers;

use App\Models\CertifiedPerson;
use App\Models\Contractor;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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

        // logger()->info('Admin CertifiedPerson manual contractor lookup', [
        //     'route_contractor_raw' => $request->route('contractor'),
        //     'interpreted_contractor_id' => $contractorId,
        //     'found' => (bool) $contractor,
        //     'contractor_id' => $contractor?->id,
        //     'contractor_user_id' => $contractor?->user_id,
        // ]);

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
            'email' => 'required|email:rfc,dns|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'distributor_code' => 'required|string|size:2|alpha',
        ]);

        $person = DB::transaction(function () use ($data, $contractor) {
            // Generate certification number
            $certNumber = $this->generateCertificateNumber(
                $data['distributor_code']
            );

            // Extra safety: retry on extremely unlikely collisions
            $attempts = 0;

            while (
                CertifiedPerson::where(
                    'certification_number',
                    $certNumber
                )->exists()
            ) {
                $attempts++;

                if ($attempts > 5) {
                    abort(
                        500,
                        'Could not generate a unique certification number. Please try again.'
                    );
                }

                $certNumber = $this->generateCertificateNumber(
                    $data['distributor_code']
                );
            }

            // Create the user
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'contractor_id' => $contractor->id,
            ]);

            // Give the user contractor permissions
            $user->assignRole('contractor');

            // Create the certified person and link it to the user
            return $contractor->certifiedPeople()->create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'certification_number' => $certNumber,
            ]);
        });

        return response()->json([
            'message' => 'Certified person and user account created successfully.',
            'data' => $person->load('user'),
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
    private function generateCertificateNumber(string $distributorCode): string
    {
        return 'OMNI' . strtoupper($distributorCode) . '-' . now()->format('ym') . '-' . $this->randomCode(8);
    }

    /**
     * Random code from readable charset (no O/0, I/1, L).
     */
    private function randomCode(int $length = 8): string
    {
        $characters = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $code;
    }
}
