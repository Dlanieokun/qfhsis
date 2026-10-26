<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\HouseholdProfile;
use App\Models\FamilyPlanningRecord;
use App\Models\FamilyPlanningDropOut;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UsersController extends Controller
{
    /**
     * Authenticate Android App Client and Issue Sanctum Bearer Token.
     * Accepts either a username or an email address in the 'login' field.
     * For backwards-compatibility the old 'email' key is also accepted and
     * treated as an email address automatically.
     */
    public function login(Request $request)
    {
        // 1. Validate incoming parameters.
        //    Accept either the new 'login' key or the legacy 'email' key so
        //    existing Android clients keep working without an app update.
        $validator = Validator::make($request->all(), [
            'login'    => 'required_without:email|nullable|string',
            'email'    => 'required_without:login|nullable|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation constraints failed.',
                'errors'  => $validator->errors()
            ], 422);
        }

        // 2. Resolve the login identifier — prefer 'login', fall back to 'email'.
        $loginValue = strtolower(trim($request->input('login') ?? $request->input('email')));
        $isEmail    = str_contains($loginValue, '@');

        // 3. Locate the entity record by email or username.
        $user = $isEmail
            ? User::where('email', $loginValue)->first()
            : User::where('username', $loginValue)->first();

        // 4. Verify user status and matching password bounds
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid credentials. Check your username/email and password.'
            ], 401);
        }

        if ($user->status !== 'Active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is currently suspended or inactive.'
            ], 403);
        }

        // 4. Clean up old tokens to enforce a fresh single-session
        $user->tokens()->where('name', 'auth_token')->delete();

        // 5. Generate plain-text API token (Sanctum)
        $token = $user->createToken('auth_token')->plainTextToken;

        // 6. Structure payload response including geographic user variables
        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id'       => $user->id,
                'name'     => $user->name,
                'username' => $user->username,
                'email'    => $user->email,
                'role'     => $user->role,
                'status' => $user->status,
                'assigned_facility' => $user->assigned_facility,
                'barangay' => $user->barangay,
                'municipality' => $user->municipality,
                'province' => $user->province,
                'region' => $user->region,
                'barangay_codes' => $user->barangay_codes,
                'municipality_code' => $user->municipality_code,
                'province_code' => $user->province_code,
                'region_code' => $user->region_code,
            ]
        ], 200);
    }

    /**
     * Terminate Mobile Bearer Session Token safely
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out.'
        ], 200);
    }

    public function insertloop(int $loop)
    {
        try {
            $data = [];
            $now = now();

            // 1. Build the array in memory instead of querying the DB every loop
            for ($i = 0; $i < $loop; $i++) {
                $data[] = [
                    'sitio' => 'Sitio ' . ($i + 1),
                    'barangay' => 'Example Barangay',
                    'municipality' => 'Example Municipality',
                    'province' => 'Example Province',
                    'region' => 'Example Region',
                    'hhNumber' => 'HH-' . rand(1000, 9999),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // 2. Insert everything in chunks to prevent query size limit errors if $loop is massive
            $chunks = array_chunk($data, 500); 
            foreach ($chunks as $chunk) {
                DB::table('household_profiles')->insert($chunk);
            }

            return response()->json([
                'status' => 'success',
                'message' => "Successfully inserted {$loop} records."
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to insert records.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}