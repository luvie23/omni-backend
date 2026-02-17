<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Contractor;
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
        $user->tokens()->delete();

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
}
