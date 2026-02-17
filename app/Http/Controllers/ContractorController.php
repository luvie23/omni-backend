<?php

namespace App\Http\Controllers;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Http\Request;
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

    // Admin: list contractors (users who have contractor role)
    public function index(Request $request)
    {
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

    private function contractorPayload(User $user): array
    {
        $profile = $user->contractorProfile;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(), // collection -> JSON array
            'contractor_profile' => $profile ? [
                'company_name' => $profile->company_name,
                'company_website_url' => $profile->company_website_url,
                'mailing_address' => $profile->mailing_address,
                'city' => $profile->city,
                'state' => $profile->state,
                'zip' => $profile->zip,
                'service_area' => $profile->service_area,
            ] : null,
        ];
    }
}
