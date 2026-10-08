import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, KeyRound, LoaderCircle, Lock, Mail, Send } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthSplitLayout, { authButtonClass, authIconWrapClass, authInputClass, authLabelClass } from '@/layouts/auth-split-layout';

type Step = 'email' | 'code' | 'reset';
type Busy = 'send' | 'verify' | 'reset' | null;

const RESEND_SECONDS = 60;

const inlineButtonClass =
    'h-12 shrink-0 px-5 font-semibold shadow-md bg-blue-600 hover:bg-blue-700 text-white transition-all duration-300 ' +
    'hover:shadow-lg active:scale-[0.98]';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors, clearErrors } = useForm({
        email: '',
        code: '',
        password: '',
        password_confirmation: '',
    });

    const [step, setStep] = useState<Step>('email');
    const [busy, setBusy] = useState<Busy>(null);
    const [cooldown, setCooldown] = useState(0);

    // Resend countdown
    useEffect(() => {
        if (cooldown <= 0) return;
        const timer = setTimeout(() => setCooldown((c) => c - 1), 1000);
        return () => clearTimeout(timer);
    }, [cooldown]);

    const sendCode = () => {
        clearErrors();
        setBusy('send');
        post(route('password.code.send'), {
            preserveScroll: true,
            onSuccess: () => {
                setStep('code');
                setCooldown(RESEND_SECONDS);
            },
            onFinish: () => setBusy(null),
        });
    };

    const verifyCode = () => {
        clearErrors();
        setBusy('verify');
        post(route('password.code.verify'), {
            preserveScroll: true,
            onSuccess: () => setStep('reset'),
            onFinish: () => setBusy(null),
        });
    };

    const resetPassword = () => {
        clearErrors();
        setBusy('reset');
        post(route('password.code.reset'), {
            onError: (errs: Record<string, string>) => {
                // The code expired or was wrong: send the user back to the code step.
                if (errs.code) setStep('code');
            },
            onFinish: () => setBusy(null),
        });
    };

    const startOver = () => {
        clearErrors();
        setStep('email');
        setCooldown(0);
        setData((d) => ({ ...d, code: '', password: '', password_confirmation: '' }));
    };

    // Pressing Enter runs the action for the current step.
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (processing) return;
        if (step === 'email') {
            if (data.email) sendCode();
        } else if (step === 'code') {
            if (data.code.length === 6) verifyCode();
        } else {
            resetPassword();
        }
    };

    const emailLocked = step !== 'email';
    const canSend = data.email.includes('@') && !processing && (step === 'email' || cooldown === 0);

    return (
        <AuthSplitLayout title="Forgot password?" description="We'll email you a 6-digit code to verify it's you">
            <Head title="Forgot password" />

            {status && (
                <div className="mb-6 rounded-lg bg-emerald-50 p-4 text-sm font-medium text-emerald-800 border border-emerald-200 animate-in fade-in zoom-in-95 duration-300">
                    {status}
                </div>
            )}

            <form className="space-y-6" onSubmit={submit}>
                {/* Step 1: email + Send code */}
                <div className="grid gap-2 group">
                    <Label htmlFor="email" className={authLabelClass}>
                        Email address
                    </Label>
                    <div className="flex gap-2">
                        <div className="relative flex-1">
                            <div className={authIconWrapClass}>
                                <Mail className="h-5 w-5" />
                            </div>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="off"
                                readOnly={emailLocked}
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="name@example.com"
                                className={`pl-11 ${authInputClass} ${emailLocked ? 'cursor-not-allowed text-slate-500' : ''}`}
                            />
                        </div>

                        {step !== 'reset' && (
                            <Button type="button" className={inlineButtonClass} tabIndex={2} disabled={!canSend} onClick={sendCode}>
                                {busy === 'send' ? <LoaderCircle className="mr-2 h-4 w-4 animate-spin" /> : <Send className="mr-2 h-4 w-4" />}
                                {step === 'email' ? 'Send code' : cooldown > 0 ? `Resend in ${cooldown}s` : 'Resend code'}
                            </Button>
                        )}
                    </div>
                    <InputError message={errors.email} />

                    {emailLocked && (
                        <button type="button" onClick={startOver} className="w-fit text-xs font-medium text-blue-600 hover:text-blue-700">
                            Use a different email
                        </button>
                    )}
                </div>

                {/* Step 2: enter code + Verify */}
                {step !== 'email' && (
                    <div className="grid gap-2 group animate-in fade-in slide-in-from-bottom-4 duration-500">
                        <Label htmlFor="code" className={authLabelClass}>
                            Verification code
                        </Label>
                        <div className="flex gap-2">
                            <div className="relative flex-1">
                                <div className={authIconWrapClass}>
                                    <KeyRound className="h-5 w-5" />
                                </div>
                                <Input
                                    id="code"
                                    name="code"
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    maxLength={6}
                                    autoFocus
                                    tabIndex={3}
                                    readOnly={step === 'reset'}
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))}
                                    placeholder="000000"
                                    className={`pl-11 text-center text-xl font-semibold tracking-[0.5em] ${authInputClass} ${
                                        step === 'reset' ? 'cursor-not-allowed text-slate-500' : ''
                                    }`}
                                />
                            </div>

                            {step === 'code' ? (
                                <Button
                                    type="button"
                                    className={inlineButtonClass}
                                    tabIndex={4}
                                    disabled={processing || data.code.length !== 6}
                                    onClick={verifyCode}
                                >
                                    {busy === 'verify' && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                    Verify
                                </Button>
                            ) : (
                                <div className="flex h-12 shrink-0 items-center gap-1.5 rounded-md bg-emerald-50 px-4 text-sm font-semibold text-emerald-700 border border-emerald-200">
                                    <CheckCircle2 className="h-4 w-4" />
                                    Verified
                                </div>
                            )}
                        </div>
                        <InputError message={errors.code} />
                        {step === 'code' && (
                            <p className="text-xs text-slate-500">Check your inbox (and spam folder). The code expires in 10 minutes.</p>
                        )}
                    </div>
                )}

                {/* Step 3: new password */}
                {step === 'reset' && (
                    <div className="space-y-5 animate-in fade-in slide-in-from-bottom-4 duration-500">
                        <div className="grid gap-2 group">
                            <Label htmlFor="password" className={authLabelClass}>
                                New password
                            </Label>
                            <div className="relative">
                                <div className={authIconWrapClass}>
                                    <Lock className="h-5 w-5" />
                                </div>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    autoFocus
                                    tabIndex={5}
                                    autoComplete="new-password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder="••••••••"
                                    className={`pl-11 ${authInputClass}`}
                                />
                            </div>
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2 group">
                            <Label htmlFor="password_confirmation" className={authLabelClass}>
                                Confirm password
                            </Label>
                            <div className="relative">
                                <div className={authIconWrapClass}>
                                    <Lock className="h-5 w-5" />
                                </div>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    required
                                    tabIndex={6}
                                    autoComplete="new-password"
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    placeholder="••••••••"
                                    className={`pl-11 ${authInputClass}`}
                                />
                            </div>
                            <InputError message={errors.password_confirmation} />
                        </div>

                        <Button type="submit" className={authButtonClass} tabIndex={7} disabled={processing}>
                            {busy === 'reset' && <LoaderCircle className="mr-2 h-5 w-5 animate-spin" />}
                            Reset password
                        </Button>
                    </div>
                )}

                <div className="text-center text-sm text-slate-500">
                    Or, return to{' '}
                    <TextLink href={route('login')} tabIndex={8} className="font-medium text-blue-600 hover:text-blue-700">
                        log in
                    </TextLink>
                </div>
            </form>
        </AuthSplitLayout>
    );
}