import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Lock, Mail } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
}

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout 
            title="Welcome Back" 
            description="Enter your credentials to access your account dashboard"
        >
            <Head title="Log in" />

            {status && (
                <div className="mb-4 rounded-xl bg-emerald-50 p-4 text-center text-sm font-medium text-emerald-700 border border-emerald-100 dark:bg-emerald-950/30 dark:border-emerald-900/50">
                    {status}
                </div>
            )}

            <form className="space-y-5" onSubmit={submit}>
                <div className="space-y-4">
                    {/* Email Input Field */}
                    <div className="grid gap-2">
                        <Label htmlFor="email" className="text-sm font-medium text-foreground/90">
                            Email address
                        </Label>
                        <div className="relative">
                            <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted-foreground/70">
                                <Mail className="h-4 w-4" />
                            </div>
                            <Input
                                id="email"
                                type="email"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="name@example.com"
                                className="pl-10 h-11 bg-background border-input transition-all duration-200 focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary"
                            />
                        </div>
                        <InputError message={errors.email} />
                    </div>

                    {/* Password Input Field */}
                    <div className="grid gap-2">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="password" className="text-sm font-medium text-foreground/90">
                                Password
                            </Label>
                            {canResetPassword && (
                                <TextLink 
                                    href={route('password.request')} 
                                    className="text-xs font-medium text-primary hover:text-primary/80 transition-colors" 
                                    tabIndex={6}
                                >
                                    Forgot password?
                                </TextLink>
                            )}
                        </div>
                        <div className="relative">
                            <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted-foreground/70">
                                <Lock className="h-4 w-4" />
                            </div>
                            <Input
                                id="password"
                                type="password"
                                required
                                tabIndex={2}
                                autoComplete="current-password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="••••••••"
                                className="pl-10 h-11 bg-background border-input transition-all duration-200 focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:border-primary"
                            />
                        </div>
                        <InputError message={errors.password} />
                    </div>
                </div>

                {/* Remember Me Checkbox */}
                <div className="flex items-center space-x-2.5 py-1">
                    <Checkbox 
                        id="remember" 
                        name="remember" 
                        tabIndex={3} 
                        checked={data.remember}
                        onCheckedChange={(checked) => setData('remember', !!checked)}
                        className="h-4 w-4 rounded border-input transition-colors data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                    />
                    <Label 
                        htmlFor="remember" 
                        className="text-sm font-medium text-muted-foreground cursor-pointer select-none hover:text-foreground transition-colors"
                    >
                        Remember me for 30 days
                    </Label>
                </div>

                {/* Submit Action Button */}
                <Button 
                    type="submit" 
                    className="w-full h-11 text-sm font-medium shadow-sm transition-all duration-200 hover:shadow active:scale-[0.99]" 
                    tabIndex={4} 
                    disabled={processing}
                >
                    {processing ? (
                        <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />
                    ) : null}
                    Sign In
                </Button>

                {/* Registration Link Footer */}
                <div className="text-center text-sm text-muted-foreground pt-2">
                    Don't have an account?{' '}
                    <TextLink 
                        href={route('register')} 
                        className="font-semibold text-primary hover:underline transition-all" 
                        tabIndex={5}
                    >
                        Create an account
                    </TextLink>
                </div>
            </form>
        </AuthLayout>
    );
}