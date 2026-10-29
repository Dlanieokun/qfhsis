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
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('assigned_facility', 'like', "%{$search}%")
                  ->orWhere('barangay', 'like', "%{$search}%")
                  ->orWhere('municipality', 'like', "%{$search}%")
                  ->orWhere('province', 'like', "%{$search}%")
                  ->orWhere('region', 'like', "%{$search}%");
            });
        }

        $users = $query->select([
            'id', 'name', 'username', 'email', 'contact_number', 'role', 'status', 'allow', 'assigned_facility',
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
            'username' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'contact_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'role' => ['required', Rule::in(['Administrator', 'DOH', 'PHO', 'Doctor', 'Public Health Nurse', 'BHS', 'BHW'])],
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
            'username' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'contact_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'role' => ['required', Rule::in(['Administrator', 'DOH', 'PHO', 'Doctor', 'Public Health Nurse', 'BHS', 'BHW'])],
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

    public function resetPassword(User $user): RedirectResponse
    {
        $user->update(['password' => Hash::make('password123')]);

        return redirect()->back()->with('success', "Password for {$user->name} has been reset to the default.");
    }

    public function allow(User $user): RedirectResponse
    {
        $user->update(['allow' => true]);

        return redirect()->back()->with('success', "{$user->name} is now allowed to manage users under the assigned address.");
    }

    public function revokeAllow(User $user): RedirectResponse
    {
        $user->update(['allow' => false]);

        return redirect()->back()->with('success', "{$user->name}'s permission to manage users has been removed.");
    }

    /**
     * Manage Users page: lists only the users that fall under the logged-in
     * user's own address (region / province / municipality / barangays).
     */
    public function manage(Request $request): Response
    {
        $me = $this->authorizeManager($request);

        $query = $this->scopedUsers($me);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        $users = $query->select([
            'id', 'name', 'username', 'email', 'role', 'status',
            'barangay', 'municipality', 'province', 'region',
            'barangay_codes', 'municipality_code', 'province_code', 'region_code',
        ])->orderBy('name')->get();

        return Inertia::render('ManageUser', [
            'users' => $users,
            'filters' => ['search' => $request->input('search', '')],
        ]);
    }

    public function changeRole(Request $request, User $user): RedirectResponse
    {
        $me = $this->authorizeManager($request);
        $this->scopedUsers($me)->whereKey($user->id)->firstOrFail();

        $validated = $request->validate([
            'role' => ['required', Rule::in(['DOH', 'PHO', 'Doctor', 'Public Health Nurse', 'BHS', 'BHW'])],
        ]);

        $user->update($validated);

        return redirect()->back()->with('success', "{$user->name}'s role has been updated.");
    }

    public function changeStatus(Request $request, User $user): RedirectResponse
    {
        $me = $this->authorizeManager($request);
        $this->scopedUsers($me)->whereKey($user->id)->firstOrFail();

        $validated = $request->validate([
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $user->update($validated);

        return redirect()->back()->with('success', "{$user->name}'s status has been updated.");
    }

    public function manageResetPassword(Request $request, User $user): RedirectResponse
    {
        $me = $this->authorizeManager($request);
        $this->scopedUsers($me)->whereKey($user->id)->firstOrFail();

        $user->update(['password' => Hash::make('password123')]);

        return redirect()->back()->with('success', "Password for {$user->name} has been reset to the default.");
    }

    /**
     * Only administrators or users an admin has granted "allow" may manage users.
     */
    private function authorizeManager(Request $request): User
    {
        $me = $request->user();
        abort_unless($me && $me->canApproveAccounts(), 403);

        return $me;
    }

    /**
     * Users the given manager may act on, based on the manager's saved address.
     * The most specific address level the manager has set is used:
     * municipality > province > region (barangays are not part of the scope).
     * A manager with no address set sees nobody. Administrators see everyone.
     * The manager and administrator accounts are always excluded.
     */
    private function scopedUsers(User $me)
    {
        $query = User::query()->where('id', '!=', $me->id);

        if ($me->isAdministrator()) {
            return $query;
        }

        $query->where('role', '!=', 'Administrator');

        if ($me->municipality_code) {
            $query->where('municipality_code', $me->municipality_code);
        } elseif ($me->province_code) {
            $query->where('province_code', $me->province_code);
        } elseif ($me->region_code) {
            $query->where('region_code', $me->region_code);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
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