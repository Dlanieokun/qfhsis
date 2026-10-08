<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Show the password reset link request page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            Log::error('Password reset mail failed: '.$e->getMessage(), ['email' => $request->email]);

            // In local/debug mode show the real SMTP error right on the form so
            // it can be fixed; in production show a generic message instead.
            throw ValidationException::withMessages([
                'email' => config('app.debug')
                    ? 'Mail error: '.$e->getMessage()
                    : 'We could not send the email right now. Please try again later.',
            ]);
        }

        // Always show the same message to the user (so the form can't be used
        // to discover which emails exist). The real reason (unknown email, or
        // throttled: one link per email per 60 seconds) goes to the log.
        // PasswordBroker::RESET_LINK_SENT exists in every Laravel version,
        // unlike the Password facade's constants which differ between versions.
        if ($status !== PasswordBroker::RESET_LINK_SENT) {
            Log::info('Password reset link not sent', ['email' => $request->email, 'status' => $status]);
        }

        return back()->with('status', __('A reset link will be sent if the account exists.'));
    }
}