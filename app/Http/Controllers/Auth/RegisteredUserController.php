<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'assigned_facility' => ['nullable', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:255'],
            'region_code' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'province_code' => ['required', 'string', 'max:255'],
            'municipality' => ['required', 'string', 'max:255'],
            'municipality_code' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'array', 'min:1'],
            'barangay.*' => ['string'],
            'barangay_codes' => ['required', 'array', 'min:1'],
            'barangay_codes.*' => ['string'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            // Role and status are never taken from the request — letting a
            // self-registering user pick their own role would be a privilege
            // escalation. New accounts start at the lowest-privilege role and
            // Inactive; an Administrator approves them on the Account Approvals page.
            'role' => 'BHW',
            'status' => 'Inactive',
            'assigned_facility' => $request->assigned_facility,
            'region' => $request->region,
            'region_code' => $request->region_code,
            'province' => $request->province,
            'province_code' => $request->province_code,
            'municipality' => $request->municipality,
            'municipality_code' => $request->municipality_code,
            'barangay' => $request->barangay,
            'barangay_codes' => $request->barangay_codes,
        ]);

        event(new Registered($user));

        // Not logged in on purpose: the account is pending until an administrator approves it.
        return to_route('login')->with('status', __('Your account has been successfully created. An administrator must approve your account before you can sign in. You will receive an email notification once your account has been approved.'));
    }
}