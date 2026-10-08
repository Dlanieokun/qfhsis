import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Lock, Mail } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthSplitLayout, { authButtonClass, authIconWrapClass, authInputClass, authLabelClass } from '@/layouts/auth-split-layout';

interface ResetPasswordProps {
    token: string;
    email: string;
}

interface ResetPasswordForm {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const { data, setData, post, processing, errors, reset } = useForm<ResetPasswordForm>({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthSplitLayout title="Reset password" description="Please enter your new password below">
            <Head title="Reset password" />

            <form className="space-y-6" onSubmit={submit}>
                <div className="space-y-5">
                    <div className="grid gap-2 group">
                        <Label htmlFor="email" className={authLabelClass}>
                            Email address
                        </Label>
                        <div className="relative">
                            <div className={authIconWrapClass}>
                                <Mail className="h-5 w-5" />
                            </div>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="email"
                                value={data.email}
                                readOnly
                                className={`pl-11 cursor-not-allowed text-slate-500 ${authInputClass}`}
                            />
                        </div>
                        <InputError message={errors.email} />
                    </div>

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
                                tabIndex={1}
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
                                tabIndex={2}
                                autoComplete="new-password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                placeholder="••••••••"
                                className={`pl-11 ${authInputClass}`}
                            />
                        </div>
                        <InputError message={errors.password_confirmation} />
                    </div>
                </div>

                <Button type="submit" className={authButtonClass} tabIndex={3} disabled={processing}>
                    {processing && <LoaderCircle className="mr-2 h-5 w-5 animate-spin" />}
                    Reset password
                </Button>
            </form>
        </AuthSplitLayout>
    );
}