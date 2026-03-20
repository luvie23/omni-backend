<?php

namespace App\Http\Controllers;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;


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

        // Validate contractor fields
        $data = $this->validateContractor($request, $partial = true);

        // Validate email separately (only if provided)
        $request->validate([
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
        ]);

        // Update contractor
        $contractor->fill($data)->save();

        // Update user email if present
        if ($request->has('email')) {
            $user->email = $request->email;
            $user->save();
        }

        return response()->json([
            'data' => $this->contractorPayload(
                $user->fresh()->load('contractorProfile')
            ),
        ]);
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:20'],
            'service_area' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->role('contractor')
            ->with('contractorProfile')
            ->when(!empty($validated['search']), function ($query) use ($validated) {
                $search = trim($validated['search']);

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('contractorProfile', function ($contractorQuery) use ($search) {
                            $contractorQuery->where('company_name', 'like', "%{$search}%")
                                ->orWhere('city', 'like', "%{$search}%")
                                ->orWhere('state', 'like', "%{$search}%")
                                ->orWhere('service_area', 'like', "%{$search}%");
                        });
                });
            })
            ->when(!empty($validated['company_name']), function ($query) use ($validated) {
                $query->whereHas('contractorProfile', fn ($q) =>
                    $q->where('company_name', 'like', '%' . $validated['company_name'] . '%')
                );
            })
            ->when(!empty($validated['city']), function ($query) use ($validated) {
                $query->whereHas('contractorProfile', fn ($q) =>
                    $q->where('city', 'like', '%' . $validated['city'] . '%')
                );
            })
            ->when(!empty($validated['state']), function ($query) use ($validated) {
                $query->whereHas('contractorProfile', fn ($q) =>
                    $q->where('state', 'like', '%' . $validated['state'] . '%')
                );
            })
            ->when(!empty($validated['zip']), function ($query) use ($validated) {
                $query->whereHas('contractorProfile', fn ($q) =>
                    $q->where('zip', $validated['zip'])
                );
            })
            ->when(!empty($validated['service_area']), function ($query) use ($validated) {
                $query->whereHas('contractorProfile', fn ($q) =>
                    $q->where('service_area', 'like', '%' . $validated['service_area'] . '%')
                );
            })
            ->paginate($validated['per_page'] ?? 15);

        return response()->json(
            $users->through(fn ($u) => $this->contractorPayload($u))
        );
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

    public function updatePassword(Request $request)
    {
        $userId = (int) $request->route('user');


        $user = User::findOrFail($userId);

        abort_unless($user->hasRole('contractor'), 404);

        $data = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        return response()->json([
            'message' => 'Contractor password updated successfully.',
        ]);
    }

    public function updateMyPassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Verify current password
        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        return response()->json([
            'message' => 'Password updated successfully.'
        ]);
    }
}
