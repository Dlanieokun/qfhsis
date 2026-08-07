import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Lock, Mail, Stethoscope } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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
        <div className="min-h-screen w-full flex bg-slate-50">
            <Head title="Log in" />

            {/* Left Side - Deep Teal Branding (Matches the FHSIS sidebar from your image) */}
            <div className="hidden lg:flex flex-col justify-between w-1/2 lg:w-5/12 p-12 bg-gradient-to-b from-teal-950 to-teal-800 text-white relative overflow-hidden">
                {/* Decorative background elements */}
                <div className="absolute top-[-10%] left-[-10%] w-96 h-96 bg-teal-600/20 rounded-full blur-3xl" />
                <div className="absolute bottom-[-10%] right-[-10%] w-96 h-96 bg-emerald-500/20 rounded-full blur-3xl" />

                <div className="relative z-10 animate-in fade-in slide-in-from-left-8 duration-1000">
                    <div className="flex items-center gap-3 mb-8">
                        <div className="p-2 bg-white/10 rounded-lg backdrop-blur-sm border border-white/20">
                            <Stethoscope className="w-6 h-6 text-emerald-300" />
                        </div>
                        <span className="text-2xl font-bold tracking-wider">FHSIS</span>
                    </div>
                    <h1 className="text-4xl font-bold leading-tight mt-12 mb-6">
                        Field Health Service <br/> Information System
                    </h1>
                    <p className="text-teal-100/80 text-lg max-w-md">
                        Securely manage personnel roles, facility allocations, and health records in one centralized dashboard.
                    </p>
                </div>
                
                <div className="relative z-10 text-sm text-teal-200/60 animate-in fade-in duration-1000 delay-500">
                    &copy; {new Date().getFullYear()} FHSIS Portal. All rights reserved.
                </div>
            </div>

            {/* Right Side - Form Container */}
            <div className="flex-1 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-20 xl:px-32 bg-white">
                <div className="mx-auto w-full max-w-md animate-in fade-in slide-in-from-bottom-8 duration-700">
                    
                    {/* Mobile Header (Hidden on Desktop) */}
                    <div className="flex lg:hidden items-center gap-2 mb-8">
                        <div className="p-2 bg-teal-900 rounded-lg">
                            <Stethoscope className="w-5 h-5 text-emerald-300" />
                        </div>
                        <span className="text-xl font-bold text-teal-950">FHSIS</span>
                    </div>

                    <div className="mb-8">
                        <h2 className="text-3xl font-bold text-slate-900 tracking-tight">Welcome Back</h2>
                        <p className="text-slate-500 mt-2">Enter your credentials to access your account dashboard</p>
                    </div>

                    {status && (
                        <div className="mb-6 rounded-lg bg-emerald-50 p-4 text-sm font-medium text-emerald-800 border border-emerald-200 animate-in fade-in zoom-in-95 duration-300">
                            {status}
                        </div>
                    )}

                    <form className="space-y-6" onSubmit={submit}>
                        <div className="space-y-5">
                            {/* Email Input Field */}
                            <div className="grid gap-2 group">
                                <Label htmlFor="email" className="text-sm font-semibold text-slate-700">
                                    Email address
                                </Label>
                                <div className="relative">
                                    <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400 group-focus-within:text-blue-600 transition-colors">
                                        <Mail className="h-5 w-5" />
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
                                        className="pl-11 h-12 bg-slate-50 border-slate-200 text-slate-900 transition-all duration-300 focus-visible:bg-white focus-visible:ring-2 focus-visible:ring-blue-600/20 focus-visible:border-blue-600 hover:border-slate-300"
                                    />
                                </div>
                                <InputError message={errors.email} />
                            </div>

                            {/* Password Input Field */}
                            <div className="grid gap-2 group">
                                <div className="flex items-center justify-between">
                                    <Label htmlFor="password" className="text-sm font-semibold text-slate-700">
                                        Password
                                    </Label>
                                    {canResetPassword && (
                                        <TextLink 
                                            href={route('password.request')} 
                                            className="text-sm font-medium text-blue-600 hover:text-blue-700 transition-colors" 
                                            tabIndex={6}
                                        >
                                            Forgot password?
                                        </TextLink>
                                    )}
                                </div>
                                <div className="relative">
                                    <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400 group-focus-within:text-blue-600 transition-colors">
                                        <Lock className="h-5 w-5" />
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
                                        className="pl-11 h-12 bg-slate-50 border-slate-200 text-slate-900 transition-all duration-300 focus-visible:bg-white focus-visible:ring-2 focus-visible:ring-blue-600/20 focus-visible:border-blue-600 hover:border-slate-300"
                                    />
                                </div>
                                <InputError message={errors.password} />
                            </div>
                        </div>

                        {/* Remember Me Checkbox */}
                        <div className="flex items-center space-x-3 py-1">
                            <Checkbox 
                                id="remember" 
                                name="remember" 
                                tabIndex={3} 
                                checked={data.remember}
                                onCheckedChange={(checked) => setData('remember', !!checked)}
                                className="h-5 w-5 rounded border-slate-300 transition-colors data-[state=checked]:bg-blue-600 data-[state=checked]:border-blue-600 data-[state=checked]:text-white"
                            />
                            <Label 
                                htmlFor="remember" 
                                className="text-sm font-medium text-slate-600 cursor-pointer select-none hover:text-slate-900 transition-colors"
                            >
                                Remember me for 30 days
                            </Label>
                        </div>

                        {/* Submit Action Button (Matches the Blue "Add New Account" button) */}
                        <Button 
                            type="submit" 
                            className="w-full h-12 text-base font-semibold shadow-md bg-blue-600 hover:bg-blue-700 text-white transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98]" 
                            tabIndex={4} 
                            disabled={processing}
                        >
                            {processing ? (
                                <LoaderCircle className="mr-2 h-5 w-5 animate-spin" />
                            ) : null}
                            Sign In
                        </Button>

                    </form>
                </div>
            </div>
        </div>
    );
}