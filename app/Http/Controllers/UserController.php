<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the resource directory.
     */
    public function index(Request $request): Response
    {
        $query = User::query();

        // Handle Active Personnel Live Query Lookup Filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('assigned_facility', 'like', "%{$search}%")
                  ->orWhere('barangay', 'like', "%{$search}%")
                  ->orWhere('municipality', 'like', "%{$search}%")
                  ->orWhere('province', 'like', "%{$search}%");
            });
        }

        // Format payloads matching the User Management interface mapping precisely
        $users = $query->select([
            'id', 
            'name', 
            'email', 
            'role', 
            'status', 
            'assigned_facility', 
            'barangay',
            'municipality',
            'province',
            'created_at'
        ])
        ->latest()
        ->get();

        return Inertia::render('UserManagement', [
            'users' => $users,
            'filters' => [
                'search' => $request->input('search', '')
            ]
        ]);
    }

    /**
     * Store a newly created personnel credential registry item.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'role' => ['required', Rule::in(['Administrator', 'Doctor', 'Public Health Nurse', 'BHS', 'BHW'])],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'assigned_facility' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'status' => $validated['status'],
            'assigned_facility' => $validated['assigned_facility'],
            'barangay' => $validated['barangay'],
            'municipality' => $validated['municipality'],
            'province' => $validated['province'],
            'password' => Hash::make('Aa@123!'),
        ]);

        return redirect()->back()->with('success', 'New system credential context compiled successfully.');
    }

    /**
     * Update specified system profile properties within operational parameters.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(['Administrator', 'Doctor', 'Public Health Nurse', 'BHS', 'BHW'])],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'assigned_facility' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
        ]);

        // Persist validated form parameters to the database
        $user->update($validated);

        // Flash explicit success payload context back to Inertia usePage context
        return redirect()->back()->with('success', 'Personnel account profile updated successfully.');
    
    }

    /**
     * Revoke structural security authorizations for individual records.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return redirect()->back()->withErrors([
                'error' => 'Self-revocation protection active. You cannot destroy your own active operator key context.'
            ]);
        }

        $user->delete();

        return redirect()->back()->with('success', 'Credential authorization key matching sequence destroyed.');
    }
}