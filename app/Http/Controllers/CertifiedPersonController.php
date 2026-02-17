<?php

namespace App\Http\Controllers;

use App\Models\CertifiedPerson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CertifiedPersonController extends Controller
{
    // List all certified people under the authenticated contractor
    public function index(Request $request)
    {

        $contractor = $request->user()->contractorProfile;

        return response()->json([
            'data' => $contractor->certifiedPeople()->latest()->get()
        ]);
    }

    // Create a new certified person
    public function store(Request $request)
    {



        $contractor = $request->user()->contractorProfile;

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'certification_number' => 'nullable|string|max:255',
        ]);

        $person = $contractor->certifiedPeople()->create($data);

        return response()->json([
            'message' => 'Certified person created successfully.',
            'data' => $person
        ], 201);
    }

    // Update a certified person
    public function update(Request $request, CertifiedPerson $certifiedPerson)
    {
        $contractor = $request->user()->contractorProfile;

        // Security: ensure they own this record
        abort_unless(
            $certifiedPerson->contractor_id === $contractor->id,
            403
        );

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'certification_number' => 'sometimes|nullable|string|max:255',
        ]);

        $certifiedPerson->update($data);

        return response()->json([
            'message' => 'Certified person updated successfully.',
            'data' => $certifiedPerson
        ]);
    }

    // Delete a certified person
    public function destroy(Request $request, CertifiedPerson $certifiedPerson)
    {
        $contractor = $request->user()->contractorProfile;

        abort_unless(
            $certifiedPerson->contractor_id === $contractor->id,
            403
        );

        $certifiedPerson->delete();

        return response()->json([
            'message' => 'Certified person deleted successfully.'
        ]);
    }
}
