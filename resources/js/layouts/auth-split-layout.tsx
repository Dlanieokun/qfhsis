import { Stethoscope } from 'lucide-react';
import { PropsWithChildren } from 'react';

// Shared styling, identical to login.tsx / register.tsx
export const authInputClass =
    'h-12 bg-slate-50 border-slate-200 text-slate-900 transition-all duration-300 focus-visible:bg-white ' +
    'focus-visible:ring-2 focus-visible:ring-blue-600/20 focus-visible:border-blue-600 hover:border-slate-300';

export const authIconWrapClass =
    'absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400 group-focus-within:text-blue-600 transition-colors';

export const authLabelClass = 'text-sm font-semibold text-slate-700';

export const authButtonClass =
    'w-full h-12 text-base font-semibold shadow-md bg-blue-600 hover:bg-blue-700 text-white transition-all duration-300 ' +
    'hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98]';

interface AuthSplitLayoutProps {
    title: string;
    description?: string;
}

export default function AuthSplitLayout({ title, description, children }: PropsWithChildren<AuthSplitLayoutProps>) {
    return (
        <div className="min-h-screen w-full flex bg-slate-50">
            {/* Left Side - Deep Teal Branding */}
            <div className="hidden lg:flex flex-col justify-between w-1/2 lg:w-5/12 p-12 bg-gradient-to-b from-teal-950 to-teal-800 text-white relative overflow-hidden lg:sticky lg:top-0 lg:h-screen">
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
                        Field Health Service <br /> Information System
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
                    {/* Mobile Header */}
                    <div className="flex lg:hidden items-center gap-2 mb-8">
                        <div className="p-2 bg-teal-900 rounded-lg">
                            <Stethoscope className="w-5 h-5 text-emerald-300" />
                        </div>
                        <span className="text-xl font-bold text-teal-950">FHSIS</span>
                    </div>

                    <div className="mb-8">
                        <h2 className="text-3xl font-bold text-slate-900 tracking-tight">{title}</h2>
                        {description && <p className="text-slate-500 mt-2">{description}</p>}
                    </div>

                    {children}
                </div>
            </div>
        </div>
    );
}