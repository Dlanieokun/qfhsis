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
    public function index(Request $request): Response
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('assigned_facility', 'like', "%{$search}%")
                  ->orWhere('barangay', 'like', "%{$search}%") 
                  ->orWhere('municipality', 'like', "%{$search}%")
                  ->orWhere('province', 'like', "%{$search}%")
                  ->orWhere('region', 'like', "%{$search}%");
            });
        }

        $users = $query->select([
            'id', 'name', 'email', 'role', 'status', 'assigned_facility', 
            'barangay', 'municipality', 'province', 'region',
            'barangay_codes', 'municipality_code', 'province_code', 'region_code',
            'created_at'
        ])->latest()->get();

        return Inertia::render('UserManagement', [
            'users' => $users,
            'filters' => [
                'search' => $request->input('search', '')
            ]
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'role' => ['required', Rule::in(['Administrator', 'Doctor', 'Public Health Nurse', 'BHS', 'BHW'])],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'assigned_facility' => ['nullable', 'string', 'max:255'],
            
            'barangay' => ['nullable', 'array'],
            'barangay.*' => ['string'],
            'barangay_codes' => ['nullable', 'array'],
            'barangay_codes.*' => ['string'],
            
            'municipality' => ['nullable', 'string', 'max:255'],
            'municipality_code' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'province_code' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'region_code' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['password'] = Hash::make('password123');
        User::create($validated);

        return redirect()->back()->with('success', 'New system credential context compiled successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(['Administrator', 'Doctor', 'Public Health Nurse', 'BHS', 'BHW'])],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'assigned_facility' => ['nullable', 'string', 'max:255'],
            
            'barangay' => ['nullable', 'array'],
            'barangay.*' => ['string'],
            'barangay_codes' => ['nullable', 'array'],
            'barangay_codes.*' => ['string'],
            
            'municipality' => ['nullable', 'string', 'max:255'],
            'municipality_code' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'province_code' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'region_code' => ['nullable', 'string', 'max:255'],
        ]);

        $user->update($validated);

        return redirect()->back()->with('success', 'Personnel account profile updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return redirect()->back()->withErrors(['error' => 'Cannot delete your own account.']);
        }

        $user->delete();
        return redirect()->back()->with('success', 'User deleted successfully.');
    }
}