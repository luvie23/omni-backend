<?php

namespace App\Http\Controllers;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ContractorController extends Controller
{
    // Contractor: view own profile
    public function me(Request $request)
    {
        $user = $request->user()->load('contractorProfile');

        return response()->json([
            'data' => $this->contractorPayload($user),
        ]);
    }

    // Contractor: update own profile
    public function updateMe(Request $request)
    {
        $user = $request->user();
        $contractor = $user->contractorProfile;

        if (!$contractor) {
            // In case role exists but profile doesn't yet
            $contractor = Contractor::create([
                'user_id' => $user->id,
                'company_name' => '',     // will be overwritten after validation if required
                'mailing_address' => '',
                'city' => '',
                'state' => 'NA',
                'zip' => '',
                'service_area' => '',
            ]);
        }

        $data = $this->validateContractor($request, $partial = true);

        $contractor->fill($data)->save();

        return response()->json([
            'data' => $this->contractorPayload($user->fresh()->load('contractorProfile')),
        ]);
    }

    public function index(Request $request)
    {
    // Log::info('In contractors index');
        $users = User::query()
            ->role('contractor')
            ->with('contractorProfile')
            ->paginate($request->integer('per_page', 15));

        return response()->json($users->through(fn ($u) => $this->contractorPayload($u)));
    }

    // Admin: show a contractor by user id
    public function show(User $user)
    {
        $user->load('contractorProfile');

        abort_unless($user->hasRole('contractor'), 404);

        return response()->json([
            'data' => $this->contractorPayload($user),
        ]);
    }

    // Admin: update contractor profile by user id
    public function update(Request $request, User $user)
    {
        $user->load('contractorProfile');

        abort_unless($user->hasRole('contractor'), 404);

        $contractor = $user->contractorProfile ?? Contractor::create([
            'user_id' => $user->id,
            'company_name' => '',
            'mailing_address' => '',
            'city' => '',
            'state' => 'NA',
            'zip' => '',
            'service_area' => '',
        ]);

        $data = $this->validateContractor($request, $partial = true);

        $contractor->fill($data)->save();

        return response()->json([
            'data' => $this->contractorPayload($user->fresh()->load('contractorProfile')),
        ]);
    }

    private function validateContractor(Request $request, bool $partial = false): array
    {
        // If partial updates (PATCH), allow nullable fields & "sometimes"
        $sometimes = $partial ? 'sometimes|' : '';

        return $request->validate([
            'company_name' => $sometimes . 'required|string|max:255',
            'company_website_url' => $sometimes . 'nullable|url|max:255',
            'mailing_address' => $sometimes . 'required|string|max:255',
            'city' => $sometimes . 'required|string|max:100',
            'state' => $sometimes . 'required|string|size:2',
            'zip' => $sometimes . 'required|string|max:10',
            'service_area' => $sometimes . 'required|string|max:255',
        ]);
    }

    private function contractorPayload(User $u): array
    {
        $contractor = $u->contractorProfile;

        return [
            // 🔥 IMPORTANT: this is now contractors.id
            'id' => $contractor?->id,

            // if you still want the user id, expose it separately
            'user_id' => $u->id,

            'name' => $u->name,
            'email' => $u->email,
            'roles' => $u->getRoleNames(),

            'contractor_profile' => [
                'company_name' => $contractor?->company_name,
                'company_website_url' => $contractor?->company_website_url,
                'mailing_address' => $contractor?->mailing_address,
                'city' => $contractor?->city,
                'state' => $contractor?->state,
                'zip' => $contractor?->zip,
                'service_area' => $contractor?->service_area,
            ],
        ];
    }
}
