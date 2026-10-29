<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page where administrators (and users they have allowed) approve or reject
 * self-registered (Inactive) accounts.
 * The applicant is emailed either way.
 */
class AccountApprovalController extends Controller
{
    private const ROLES = ['Administrator', 'DOH', 'Doctor', 'Public Health Nurse', 'BHS', 'BHW'];

    private const ROLE_LABELS = ['BHS' => 'Midwife'];

    /** Administrators, or users an admin has granted "allow" to. */
    private function authorizeAccess(Request $request): User
    {
        $actor = $request->user();

        abort_unless($actor?->canApproveAccounts(), 403, 'You are not allowed to manage account approvals.');

        return $actor;
    }

    /**
     * Administrators see everyone. An allowed user only sees accounts under their own
     * assigned address: the narrowest level they have set (municipality, then province,
     * then region). An allowed user with no assigned address sees nothing.
     */
    private function scopeToActor(Builder $query, User $actor): Builder
    {
        if ($actor->isAdministrator()) {
            return $query;
        }

        $query->whereKeyNot($actor->id);

        if ($actor->municipality_code) {
            $query->where('municipality_code', $actor->municipality_code);
        } elseif ($actor->province_code) {
            $query->where('province_code', $actor->province_code);
        } elseif ($actor->region_code) {
            $query->where('region_code', $actor->region_code);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    /** Aborts unless the target account is within the actor's scope. */
    private function authorizeTarget(User $actor, User $target): void
    {
        $inScope = $this->scopeToActor(User::query(), $actor)->whereKey($target->id)->exists();

        abort_unless($inScope, 403, 'This account is outside your assigned address.');
    }

    /** Only administrators may hand out the Administrator role. */
    private function rolesFor(User $actor): array
    {
        return $actor->isAdministrator()
            ? self::ROLES
            : array_values(array_diff(self::ROLES, ['Administrator']));
    }

    public function index(Request $request): Response
    {
        $actor = $this->authorizeAccess($request);

        $users = $this->scopeToActor(User::query(), $actor)
            ->where('status', 'Inactive')
            ->latest()
            ->get(['id', 'name', 'email', 'role', 'status', 'assigned_facility', 'contact_number',
                   'barangay', 'municipality', 'province', 'region', 'created_at']);

        return Inertia::render('AccountApprovals', [   // adjust the path to match where UserManagement.tsx lives, e.g. 'fhsis/AccountApprovals'
            'users' => $users,
            'stats' => [
                'pending' => $users->count(),
                'active' => $this->scopeToActor(User::query(), $actor)->where('status', 'Active')->count(),
            ],
        ]);
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        $actor = $this->authorizeAccess($request);
        $this->authorizeTarget($actor, $user);

        $data = $request->validate([
            'role' => ['required', Rule::in($this->rolesFor($actor))],
        ]);

        if ($user->status === 'Active') {
            return back()->with('error', 'This account is already active.');
        }

        $user->update(['status' => 'Active', 'role' => $data['role']]);

        $roleLabel = e(self::ROLE_LABELS[$data['role']] ?? $data['role']);
        $mailError = $this->sendMail(
            $user->email,
            config('app.name').' account approved',
            'Your '.e(config('app.name')).' account is approved',
            '<p>Hi '.e($user->name).',</p>'
            .'<p>An administrator approved your account. Your role is <strong>'.$roleLabel.'</strong>. You can now sign in.</p>'
            .$this->button(route('login'), 'Sign in')
        );

        return $this->withMailResult(
            back()->with('success', "{$user->name} was approved as {$data['role']}."),
            $mailError,
            $user->email
        );
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        $actor = $this->authorizeAccess($request);
        $this->authorizeTarget($actor, $user);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($user->status !== 'Inactive') {
            return back()->with('error', 'Only pending accounts can be rejected here.');
        }

        // Keep what the email needs, because the account is deleted next.
        $name = $user->name;
        $email = $user->email;
        $reason = trim((string) ($data['reason'] ?? ''));

        $user->delete();

        $reasonHtml = $reason !== ''
            ? '<p style="background:#fef2f2;border-left:4px solid #e11d48;padding:12px 16px;border-radius:6px"><strong>Reason:</strong><br>'.nl2br(e($reason)).'</p>'
            : '';

        $mailError = $this->sendMail(
            $email,
            config('app.name').' account request not approved',
            'Your '.e(config('app.name')).' account request was not approved',
            '<p>Hi '.e($name).',</p>'
            .'<p>Unfortunately an administrator could not approve your account request.</p>'
            .$reasonHtml
            .'<p>If you think this is a mistake, please contact your administrator. You can also register again with the correct details.</p>'
            .$this->button(route('register'), 'Register again')
        );

        return $this->withMailResult(
            back()->with('success', "The account request from {$name} was rejected and removed."),
            $mailError,
            $email
        );
    }

    /**
     * Sends a styled email. Returns null on success, or an error message.
     * A mail problem never blocks the approval/rejection itself.
     */
    private function sendMail(string $to, string $subject, string $heading, string $bodyHtml): ?string
    {
        try {
            $html = <<<HTML
            <div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;padding:24px;color:#0f172a">
                <h2 style="margin:0 0 12px;color:#0f766e">{$heading}</h2>
                {$bodyHtml}
            </div>
            HTML;

            Mail::html($html, fn ($m) => $m->to($to)->subject($subject));

            return null;
        } catch (\Throwable $e) {
            Log::warning('Account decision email failed: '.$e->getMessage(), ['to' => $to]);

            return $e->getMessage();
        }
    }

    private function button(string $url, string $label): string
    {
        return '<p><a href="'.e($url).'" style="display:inline-block;background:#2563eb;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600">'
            .e($label).'</a></p>';
    }

    /** Adds a visible warning to the admin if the email could not be delivered. */
    private function withMailResult(RedirectResponse $response, ?string $mailError, string $email): RedirectResponse
    {
        if ($mailError === null) {
            return $response;
        }

        return $response->with('error', config('app.debug')
            ? "The decision was saved, but the email to {$email} failed: {$mailError}"
            : "The decision was saved, but the email to {$email} could not be sent.");
    }
}