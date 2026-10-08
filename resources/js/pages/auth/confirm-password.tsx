import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Lock } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthSplitLayout, { authButtonClass, authIconWrapClass, authInputClass, authLabelClass } from '@/layouts/auth-split-layout';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthSplitLayout
            title="Confirm your password"
            description="This is a secure area of the application. Please confirm your password before continuing."
        >
            <Head title="Confirm password" />

            <form className="space-y-6" onSubmit={submit}>
                <div className="grid gap-2 group">
                    <Label htmlFor="password" className={authLabelClass}>
                        Password
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
                            autoComplete="current-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder="••••••••"
                            className={`pl-11 ${authInputClass}`}
                        />
                    </div>
                    <InputError message={errors.password} />
                </div>

                <Button type="submit" className={authButtonClass} tabIndex={2} disabled={processing}>
                    {processing && <LoaderCircle className="mr-2 h-5 w-5 animate-spin" />}
                    Confirm password
                </Button>
            </form>
        </AuthSplitLayout>
    );
}