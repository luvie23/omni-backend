<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Contractor;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.'
            ], 401);
        }

        // Optional: delete old tokens (forces single session)
        // $user->tokens()->delete();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
            ]
        ]);
    }

    public function registerContractor(Request $request)
    {
        $data = $request->validate([
            // user
            'name' => 'required|string|max:255',
            'email' => 'required|email:rfc,dns|unique:users,email',
            'password' => 'required|string|min:8|confirmed',

            // contractor profile
            'company_name' => 'required|string|max:255',
            'contact_number' => 'required|string|max:20',
            'company_website_url' => 'nullable|url|max:255',
            'mailing_address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|size:2',
            'zip' => 'required|string|max:10',
            'service_area' => 'required|string|max:255',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            // requires roles seeded already
            $user->assignRole('contractor');

            Contractor::create([
                'user_id' => $user->id,
                'company_name' => $data['company_name'],
                'contact_number' => $data['contact_number'],
                'company_website_url' => $data['company_website_url'] ?? null,
                'mailing_address' => $data['mailing_address'],
                'city' => $data['city'],
                'state' => $data['state'],
                'zip' => $data['zip'],
                'service_area' => $data['service_area'],
            ]);

            return $user;
        });

        // Sanctum token (mobile/web apps use this)
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Contractor registered successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'contractor_profile' => $user->load('contractorProfile')->contractorProfile,
            ],
            'token' => $token,
        ], 201);
    }


    public function importContractorsCsv(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = fopen($request->file('file')->getRealPath(), 'r');

        if (!$file) {
            return response()->json([
                'message' => 'Unable to read uploaded file.',
            ], 422);
        }

        $header = fgetcsv($file);

        if (!$header) {
            return response()->json([
                'message' => 'CSV file is empty or missing a header row.',
            ], 422);
        }

        // normalize headers
        $header = array_map(fn ($h) => strtolower(trim($h)), $header);

        $requiredHeaders = [
            'name',
            'email',
            'company_name',
            'company_website_url',
            'mailing_address',
            'city',
            'state',
            'zip',
            'service_area',
        ];

        $missingHeaders = array_diff($requiredHeaders, $header);

        if (!empty($missingHeaders)) {
            return response()->json([
                'message' => 'CSV is missing required columns.',
                'missing_columns' => array_values($missingHeaders),
            ], 422);
        }

        $created = 0;
        $errors = [];
        $createdUsers = [];

        DB::beginTransaction();

        try {
            $rowIndex = 1;

            while (($row = fgetcsv($file)) !== false) {
                $rowIndex++;

                // skip empty rows
                if (count(array_filter($row)) === 0) {
                    continue;
                }

                // column mismatch
                if (count($row) !== count($header)) {
                    $errors[] = [
                        'row' => $rowIndex,
                        'email' => null,
                        'errors' => [
                            'row' => ['Column count does not match header count.'],
                        ],
                    ];
                    continue;
                }

                $data = array_combine($header, $row);

                $validator = Validator::make($data, [
                    'name' => 'required|string|max:255',
                    'email' => 'required|email|unique:users,email',

                    'company_name' => 'required|string|max:255',
                    'contact_number' => 'nullable|string|max:20',
                    'company_website_url' => 'nullable|string|max:255',
                    'mailing_address' => 'nullable|string|max:255',
                    'city' => 'nullable|string|max:100',
                    'state' => 'nullable|string|size:2',
                    'zip' => 'required|string|max:10',
                    'service_area' => 'required|string|max:255',
                ]);

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $rowIndex,
                        'email' => $data['email'] ?? null,
                        'errors' => $validator->errors()->toArray(),
                    ];
                    continue;
                }

                $validated = $validator->validated();

                try {
                    $companyPart = ucfirst(strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $validated['company_name'])));
                    $statePart = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $validated['state']));

                    $password = $companyPart . $statePart . '!2026';

                    $user = User::create([
                        'name' => $validated['name'],
                        'email' => $validated['email'],
                        'password' => Hash::make($password),
                    ]);

                    $user->assignRole('contractor');

                    Contractor::create([
                        'user_id' => $user->id,
                        'company_name' => $validated['company_name'],
                        'contact_number' => 'required|string|max:20',
                        'company_website_url' => $validated['company_website_url'] ?? null,
                        'mailing_address' => $validated['mailing_address'],
                        'city' => $validated['city'],
                        'state' => strtoupper($validated['state']),
                        'zip' => $validated['zip'],
                        'service_area' => $validated['service_area'],
                    ]);

                    $created++;

                    $createdUsers[] = [
                        'name' => $validated['name'],
                        'email' => $validated['email'],
                        'company_name' => $validated['company_name'],
                        'city' => $validated['city'],
                        'initial_password' => $password,
                    ];

                } catch (\Throwable $e) {
                    $errors[] = [
                        'row' => $rowIndex,
                        'email' => $data['email'] ?? null,
                        'errors' => [
                            'exception' => [$e->getMessage()],
                        ],
                    ];
                }
            }

            fclose($file);
            DB::commit();

            return response()->json([
                'message' => 'Import completed.',
                'created' => $created,
                'failed' => count($errors),
                'users' => $createdUsers,
                'errors' => $errors,
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();

            if (is_resource($file)) {
                fclose($file);
            }

            return response()->json([
                'message' => 'Import failed completely.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateMyPassword(Request $request)
    {

    $user = $request->user();

    $data = $request->validate([
        'current_password' => 'required|string',
        'password' => 'required|string|min:8|confirmed',
    ]);


    if (!Hash::check($data['current_password'], $user->password)) {
        return response()->json([
            'message' => 'Current password is incorrect.'
        ], 422);
    }

    // Update password
    $user->update([
        'password' => Hash::make($data['password']),
    ]);

    $user->tokens()
    ->where('id', '!=', $request->user()->currentAccessToken()->id)
    ->delete();

    return response()->json([
        'message' => 'Password updated successfully.'
    ]);
}
}
