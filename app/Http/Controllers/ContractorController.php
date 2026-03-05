<?php

namespace App\Http\Controllers;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
                'company_name' => '',
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

    // Admin: show contractor by contractors.id
    public function show(Contractor $contractor)
    {
        $contractor->load('user');

        return response()->json([
            'data' => $this->contractorPayloadFromContractor($contractor),
        ]);
    }

    // Admin: update contractor profile by contractors.id
    public function update(Request $request, Contractor $contractor)
    {
        $data = $this->validateContractor($request, $partial = true);

        $contractor->fill($data)->save();

        return response()->json([
            'data' => $this->contractorPayloadFromContractor(
                $contractor->fresh()->load('user')
            ),
        ]);
    }

    public function uploadLogo(Request $request)
    {
        $user = $request->user();
        $contractor = $user->contractorProfile;

        abort_unless($contractor, 404, 'Contractor profile not found.');

        $data = $request->validate([
            'logo' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048', // 2MB
            ],
        ]);

        // Delete old logo if exists
        if ($contractor->logo_path && Storage::disk('public')->exists($contractor->logo_path)) {
            Storage::disk('public')->delete($contractor->logo_path);
        }

        // Store new logo
        $path = $data['logo']->store('contractor-logos', 'public');

        $contractor->logo_path = $path;
        $contractor->save();

        return response()->json([
            'message' => 'Logo uploaded successfully.',
            'data' => [
                'logo_path' => $contractor->logo_path,
                'logo_url' => Storage::url($contractor->logo_path),
            ],
        ]);
    }

    private function validateContractor(Request $request, bool $partial = false): array
    {
        // If partial updates (PATCH), allow nullable fields & "sometimes"
        $sometimes = $partial ? 'sometimes|' : '';

        return $request->validate([
            'company_name' => $sometimes . 'required|string|max:255',
            'contact_number' => $sometimes . 'nullable|string|max:30',
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

            'id' => $contractor?->id,

            'user_id' => $u->id,

            'name' => $u->name,
            'email' => $u->email,
            'roles' => $u->getRoleNames(),

            'contractor_profile' => [
                'company_name' => $contractor?->company_name,
                'contact_number' => $contractor?->contact_number,
                'company_website_url' => $contractor?->company_website_url,
                'logo_url' => $contractor?->logo_path ? Storage::url($contractor->logo_path) : null,
                'mailing_address' => $contractor?->mailing_address,
                'city' => $contractor?->city,
                'state' => $contractor?->state,
                'zip' => $contractor?->zip,
                'service_area' => $contractor?->service_area,
            ],
        ];
    }


    private function contractorPayloadFromContractor(Contractor $contractor): array
    {
        $user = $contractor->user;

        return [
            'id' => $contractor->id,       // contractors.id
            'user_id' => $user->id,       // users.id

            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),

            'contractor_profile' => [
                'company_name' => $contractor->company_name,
                'company_website_url' => $contractor->company_website_url,
                'mailing_address' => $contractor->mailing_address,
                'city' => $contractor->city,
                'state' => $contractor->state,
                'zip' => $contractor->zip,
                'service_area' => $contractor->service_area,
            ],
        ];
}
}
