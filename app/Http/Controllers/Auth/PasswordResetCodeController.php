<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

/**
 * Password reset using a 6-digit code sent by email:
 *   1. send()   - emails a code
 *   2. verify() - checks the code (so the user gets instant feedback)
 *   3. reset()  - checks the code AGAIN server-side, then sets the new password
 */
class PasswordResetCodeController extends Controller
{
    private const TABLE = 'password_reset_codes';
    private const EXPIRES_MINUTES = 10;
    private const RESEND_SECONDS = 60;
    private const MAX_ATTEMPTS = 5;

    public function send(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);
        $email = Str::lower($request->email);

        $user = User::where('email', $email)->first();

        // Unknown emails get the exact same response as known ones, so the
        // form can't be used to discover which emails are registered.
        if ($user) {
            $existing = DB::table(self::TABLE)->where('email', $email)->first();
            $sentJustNow = $existing
                && Carbon::parse($existing->sent_at)->gt(now()->subSeconds(self::RESEND_SECONDS));

            if (! $sentJustNow) {
                $code = (string) random_int(100000, 999999);

                DB::table(self::TABLE)->updateOrInsert(
                    ['email' => $email],
                    [
                        'code' => Hash::make($code),
                        'attempts' => 0,
                        'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
                        'sent_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                try {
                    $this->sendMail($user, $code);
                } catch (\Throwable $e) {
                    DB::table(self::TABLE)->where('email', $email)->delete();
                    Log::error('Password reset code mail failed: '.$e->getMessage(), ['email' => $email]);

                    // Debug mode shows the real SMTP error on the form.
                    throw ValidationException::withMessages([
                        'email' => config('app.debug')
                            ? 'Mail error: '.$e->getMessage()
                            : 'We could not send the email right now. Please try again later.',
                    ]);
                }
            }
        }

        return back()->with('status', __('If that email is registered, a 6-digit code has been sent. It expires in :minutes minutes.', [
            'minutes' => self::EXPIRES_MINUTES,
        ]));
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|digits:6',
        ]);

        $this->assertValidCode(Str::lower($request->email), $request->code);

        return back()->with('status', __('Code verified. Please choose a new password.'));
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|digits:6',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $email = Str::lower($request->email);

        // Never trust that verify() ran: check the code again here.
        $this->assertValidCode($email, $request->code);

        $user = User::where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'code' => __('The code is invalid or has expired. Please request a new one.'),
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        DB::table(self::TABLE)->where('email', $email)->delete();

        // Sign out any existing sessions of this account.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        event(new PasswordReset($user));

        return to_route('login')->with('status', __('Your password has been reset. You can now log in.'));
    }

    /**
     * Throws a validation error unless the code is correct, unexpired and
     * the attempt limit has not been used up. Wrong guesses are counted.
     */
    private function assertValidCode(string $email, string $code): void
    {
        $row = DB::table(self::TABLE)->where('email', $email)->first();
        $valid = false;

        if ($row
            && Carbon::parse($row->expires_at)->isFuture()
            && $row->attempts < self::MAX_ATTEMPTS
        ) {
            if (Hash::check($code, $row->code)) {
                $valid = true;
            } else {
                DB::table(self::TABLE)->where('email', $email)->increment('attempts');
            }
        }

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => __('The code is invalid or has expired. Please request a new one.'),
            ]);
        }
    }

    private function sendMail(User $user, string $code): void
    {
        $app = e(config('app.name'));
        $name = e($user->name);
        $minutes = self::EXPIRES_MINUTES;

        $html = <<<HTML
        <div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;padding:24px;color:#0f172a">
            <h2 style="margin:0 0 8px;color:#0f766e">{$app} password reset</h2>
            <p>Hi {$name},</p>
            <p>Use this code to reset your password:</p>
            <p style="font-size:34px;font-weight:700;letter-spacing:10px;background:#f1f5f9;border-radius:8px;padding:16px;text-align:center;margin:20px 0">{$code}</p>
            <p>The code expires in {$minutes} minutes. If you did not ask for this, you can ignore this email and your password will stay the same.</p>
        </div>
        HTML;

        Mail::html($html, function ($message) use ($user) {
            $message->to($user->email)->subject(config('app.name').' password reset code');
        });
    }
}
