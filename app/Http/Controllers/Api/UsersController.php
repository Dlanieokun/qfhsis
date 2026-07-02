<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UsersController extends Controller
{
    /**
     * Authenticate Android App Client and Issue Sanctum Bearer Token
     */
    public function login(Request $request)
    {
        // 1. Validate incoming parameters
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation constraints failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        // 2. Locate the entity record
        $user = User::where('email', $request->email)->first();

        // 3. Verify user status and matching password bounds
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid email or password credentials.'
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
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'assigned_facility' => $user->assigned_facility,
                'barangay' => $user->barangay,
                'municipality' => $user->municipality,
                'province' => $user->province
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
}