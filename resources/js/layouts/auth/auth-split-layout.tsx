import AppLogoIcon from '@/components/app-logo-icon';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    title?: string;
    description?: string;
}

export default function AuthSplitLayout({ children, title, description }: AuthLayoutProps) {
    const { name, quote } = usePage<SharedData>().props;

    return (
        <div className="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0 bg-neutral-50 text-slate-800 antialiased">
            <div className="relative hidden h-full flex-col p-10 text-white lg:flex bg-slate-900 border-r border-slate-800">
                <div className="absolute inset-0 bg-gradient-to-b from-slate-900 via-slate-900 to-blue-950" />
                <Link href={route('home')} className="relative z-20 flex items-center text-lg font-bold tracking-tight text-white gap-2">
                    <AppLogoIcon className="size-6 fill-current text-blue-400" />
                    <span>{name}</span>
                </Link>
                {quote && (
                    <div className="relative z-20 mt-auto">
                        <blockquote className="space-y-2">
                            <p className="text-lg font-medium text-slate-200 leading-relaxed">&ldquo;{quote.message}&rdquo;</p>
                            <footer className="text-sm text-blue-300 font-semibold">{quote.author}</footer>
                        </blockquote>
                    </div>
                )}
            </div>
            <div className="w-full lg:p-8 bg-neutral-50">
                <div className="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <Link href={route('home')} className="relative z-20 flex items-center justify-center lg:hidden">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                            <AppLogoIcon className="h-7 w-7 fill-current text-blue-600" />
                        </div>
                    </Link>
                    <div className="flex flex-col items-start gap-1.5 text-left sm:items-center sm:text-center">
                        <h1 className="text-xl font-bold text-slate-900 tracking-tight">{title}</h1>
                        <p className="text-slate-500 text-sm text-balance">{description}</p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}