import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import AuthSplitLayout, { authButtonClass } from '@/layouts/auth-split-layout';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <AuthSplitLayout title="Verify your email" description="Please verify your email address by clicking the link we just emailed to you.">
            <Head title="Email verification" />

            {status === 'verification-link-sent' && (
                <div className="mb-6 rounded-lg bg-emerald-50 p-4 text-sm font-medium text-emerald-800 border border-emerald-200 animate-in fade-in zoom-in-95 duration-300">
                    A new verification link has been sent to the email address you provided during registration.
                </div>
            )}

            <form onSubmit={submit} className="space-y-6">
                <Button type="submit" className={authButtonClass} disabled={processing}>
                    {processing && <LoaderCircle className="mr-2 h-5 w-5 animate-spin" />}
                    Resend verification email
                </Button>

                <div className="text-center text-sm">
                    <TextLink href={route('logout')} method="post" className="font-medium text-slate-500 hover:text-slate-800">
                        Log out
                    </TextLink>
                </div>
            </form>
        </AuthSplitLayout>
    );
}