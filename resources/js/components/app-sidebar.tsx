import { useState, useRef, useEffect } from 'react';
import { Link, usePage, useForm } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    LayoutDashboard,
    FileText,
    ClipboardList,
    BarChart3,
    Users,
    LogOut,
    KeyRound,
    MoreVertical,
    X,
    Lock,
    Eye,
    EyeOff,
} from 'lucide-react';
import { type SharedData } from '@/types';

const NAV_ITEMS = [
    { label: 'Dashboard',        href: '/qfhsis/public/fhsis/dashboard',         icon: LayoutDashboard },
    { label: 'PHO Forms',        href: '/qfhsis/public/fhsis/pho',               icon: FileText },
    { label: 'Nurse Submittion', href: '/qfhsis/public/fhsis/public-nurse',      icon: ClipboardList },
    { label: 'General Report',   href: '/qfhsis/public/fhsis/reports',           icon: BarChart3 },
    { label: 'User Management',  href: '/qfhsis/public/fhsis/users',             icon: Users },
];

export default function AppSidebar() {
    const { auth, url } = usePage<SharedData & { url: string }>().props;
    const currentPath = typeof window !== 'undefined' ? window.location.pathname : (url ?? '');

    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [isPasswordModalOpen, setIsPasswordModalOpen] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);

    // Password visibility states
    const [showCurrentPassword, setShowCurrentPassword] = useState(false);
    const [showNewPassword, setShowNewPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);

    // Form state handling for password change via Inertia
    const { data, setData, post, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const resetFormAndVisibility = () => {
        reset();
        setShowCurrentPassword(false);
        setShowNewPassword(false);
        setShowConfirmPassword(false);
        setIsPasswordModalOpen(false);
    };

    const handlePasswordSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/qfhsis/public/fhsis/change-password', {
            onSuccess: () => {
                resetFormAndVisibility();
            },
        });
    };

    // Close menu when clicking outside
    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
                setIsMenuOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    function isActive(href: string) {
        return currentPath === href || currentPath.startsWith(href + '/');
    }

    const user = auth?.user;
    const initials = user?.name
        ?.split(' ')
        .slice(0, 2)
        .map((w: string) => w[0]?.toUpperCase() ?? '')
        .join('') ?? '?';

    // Animation Variants for staggered nav items
    const navContainerVariant = {
        hidden: { opacity: 0 },
        show: {
            opacity: 1,
            transition: { staggerChildren: 0.08 }
        }
    };

    const navItemVariant = {
        hidden: { opacity: 0, x: -10 },
        show: { opacity: 1, x: 0, transition: { type: "spring", stiffness: 300 } }
    };

    return (
        <>
            <motion.aside
                initial={{ x: -250 }}
                animate={{ x: 0 }}
                transition={{ type: 'spring', stiffness: 200, damping: 25 }}
                className="flex h-screen w-60 shrink-0 flex-col text-white"
                style={{
                    background: 'linear-gradient(160deg, #0f2d6b 0%, #1a5276 25%, #117a65 60%, #0e6655 100%)',
                }}
            >
                {/* ── Logo ─────────────────────────────────────────────────── */}
                <div className="flex items-center gap-3.5 px-5 py-6">
                    <motion.div 
                        initial={{ scale: 0, rotate: -180 }}
                        animate={{ scale: 1, rotate: 0 }}
                        transition={{ type: "spring", stiffness: 260, damping: 20, delay: 0.2 }}
                        className="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-white/15"
                    >
                        <img 
                            src="/qfhsis/public/logo.png" 
                            alt="Logo" 
                            className="h-12 w-12 object-contain" 
                        />
                    </motion.div>
                    <motion.span 
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        transition={{ delay: 0.4 }}
                        className="text-2xl font-bold tracking-wide"
                    >
                        QFHSIS
                    </motion.span>
                </div>

                {/* ── Nav ──────────────────────────────────────────────────── */}
                <motion.nav 
                    variants={navContainerVariant}
                    initial="hidden"
                    animate="show"
                    className="flex-1 px-3 py-2 space-y-0.5"
                >
                    {NAV_ITEMS.map(({ label, href, icon: Icon }) => {
                        const active = isActive(href);
                        return (
                            <motion.div key={href} variants={navItemVariant}>
                                <Link
                                    href={href}
                                    className={[
                                        'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-200 hover:scale-[1.02]',
                                        active
                                            ? 'text-white'
                                            : 'text-white/70 hover:bg-white/10 hover:text-white',
                                    ].join(' ')}
                                    style={active ? {
                                        background: 'linear-gradient(90deg, rgba(255,255,255,0.22) 0%, rgba(255,255,255,0.08) 100%)',
                                        boxShadow: 'inset 0 0 0 1px rgba(255,255,255,0.15)',
                                    } : {}}
                                >
                                    <Icon className="h-4 w-4 shrink-0" />
                                    {label}
                                </Link>
                            </motion.div>
                        );
                    })}
                </motion.nav>
                
                {/* ── Province Footer ──────────────────────────────────────── */}
                <motion.div 
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.8 }}
                    className="border-t border-white/10 px-4 py-4 text-center"
                >
                    <div className="flex flex-col items-center gap-2">
                        <img 
                            src="/qfhsis/public/leyte_provl_logo.jpg" 
                            alt="Province of Leyte" 
                            className="h-14 w-14 object-contain rounded-full shadow-lg"
                        />
                        <span className="text-xs font-semibold text-white/90">
                            Province of Leyte
                        </span>
                    </div>
                </motion.div>

                {/* ── User footer ──────────────────────────────────────────── */}
                <motion.div 
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.6 }}
                    className="border-t border-white/10 px-4 py-4"
                >
                    <div className="flex items-center gap-3">
                        {/* Avatar */}
                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/20 text-xs font-semibold text-white">
                            {initials}
                        </div>

                        {/* Name */}
                        <span className="flex-1 truncate text-sm font-medium text-white/90">
                            {user?.name ?? 'User'}
                        </span>

                        {/* User Menu Trigger */}
                        <div className="relative" ref={menuRef}>
                            <button
                                type="button"
                                onClick={() => setIsMenuOpen((prev) => !prev)}
                                className="rounded-md p-1.5 text-white/60 transition-all hover:bg-white/10 hover:text-white"
                                title="User options"
                            >
                                <MoreVertical className="h-4 w-4" />
                            </button>

                            <AnimatePresence>
                                {isMenuOpen && (
                                    <motion.div
                                        initial={{ opacity: 0, scale: 0.95, y: 8 }}
                                        animate={{ opacity: 1, scale: 1, y: 0 }}
                                        exit={{ opacity: 0, scale: 0.95, y: 8 }}
                                        transition={{ duration: 0.15 }}
                                        className="absolute bottom-full right-0 mb-2 w-48 overflow-hidden rounded-xl border border-white/15 bg-[#0f2d6b] py-1 shadow-2xl backdrop-blur-lg"
                                    >
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setIsMenuOpen(false);
                                                setIsPasswordModalOpen(true);
                                            }}
                                            className="flex w-full items-center gap-2.5 px-3.5 py-2.5 text-left text-sm font-medium text-white/80 transition-colors hover:bg-white/10 hover:text-white"
                                        >
                                            <KeyRound className="h-4 w-4 shrink-0 text-white/70" />
                                            <span>Change Password</span>
                                        </button>

                                        <div className="my-1 border-t border-white/10" />

                                        <Link
                                            href="/qfhsis/public/logout"
                                            method="post"
                                            as="button"
                                            className="flex w-full items-center gap-2.5 px-3.5 py-2.5 text-left text-sm font-medium text-red-300 transition-colors hover:bg-red-500/20 hover:text-red-200"
                                            onClick={() => setIsMenuOpen(false)}
                                        >
                                            <LogOut className="h-4 w-4 shrink-0" />
                                            <span>Logout</span>
                                        </Link>
                                    </motion.div>
                                )}
                            </AnimatePresence>
                        </div>
                    </div>
                </motion.div>

            </motion.aside>

            {/* ── Change Password Modal ────────────────────────────────────── */}
            <AnimatePresence>
                {isPasswordModalOpen && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                        <motion.div
                            initial={{ opacity: 0, scale: 0.9, y: 20 }}
                            animate={{ opacity: 1, scale: 1, y: 0 }}
                            exit={{ opacity: 0, scale: 0.9, y: 20 }}
                            transition={{ type: "spring", duration: 0.3 }}
                            className="relative w-full max-w-md overflow-hidden rounded-2xl border border-white/20 bg-[#0f2d6b] p-6 text-white shadow-2xl"
                        >
                            {/* Modal Header */}
                            <div className="flex items-center justify-between border-b border-white/10 pb-4">
                                <div className="flex items-center gap-2.5">
                                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-white/10">
                                        <Lock className="h-5 w-5 text-white/90" />
                                    </div>
                                    <h3 className="text-lg font-semibold text-white">Change Password</h3>
                                </div>
                                <button
                                    type="button"
                                    onClick={resetFormAndVisibility}
                                    className="rounded-lg p-1 text-white/60 transition-colors hover:bg-white/10 hover:text-white"
                                >
                                    <X className="h-5 w-5" />
                                </button>
                            </div>

                            {/* Modal Body / Form */}
                            <form onSubmit={handlePasswordSubmit} className="mt-4 space-y-4">
                                {/* Current Password */}
                                <div>
                                    <label className="block text-xs font-medium text-white/80">
                                        Current Password
                                    </label>
                                    <div className="relative mt-1">
                                        <input
                                            type={showCurrentPassword ? 'text' : 'password'}
                                            value={data.current_password}
                                            onChange={(e) => setData('current_password', e.target.value)}
                                            className="w-full rounded-lg border border-white/15 bg-white/10 px-3 py-2 pr-10 text-sm text-white placeholder-white/40 focus:border-white/30 focus:outline-none focus:ring-2 focus:ring-white/20"
                                            placeholder="••••••••"
                                            required
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                                            className="absolute right-3 top-1/2 -translate-y-1/2 text-white/50 transition-colors hover:text-white"
                                            title={showCurrentPassword ? "Hide password" : "Show password"}
                                        >
                                            {showCurrentPassword ? (
                                                <EyeOff className="h-4 w-4" />
                                            ) : (
                                                <Eye className="h-4 w-4" />
                                            )}
                                        </button>
                                    </div>
                                    {errors.current_password && (
                                        <p className="mt-1 text-xs text-red-300">{errors.current_password}</p>
                                    )}
                                </div>

                                {/* New Password */}
                                <div>
                                    <label className="block text-xs font-medium text-white/80">
                                        New Password
                                    </label>
                                    <div className="relative mt-1">
                                        <input
                                            type={showNewPassword ? 'text' : 'password'}
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            className="w-full rounded-lg border border-white/15 bg-white/10 px-3 py-2 pr-10 text-sm text-white placeholder-white/40 focus:border-white/30 focus:outline-none focus:ring-2 focus:ring-white/20"
                                            placeholder="••••••••"
                                            required
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowNewPassword(!showNewPassword)}
                                            className="absolute right-3 top-1/2 -translate-y-1/2 text-white/50 transition-colors hover:text-white"
                                            title={showNewPassword ? "Hide password" : "Show password"}
                                        >
                                            {showNewPassword ? (
                                                <EyeOff className="h-4 w-4" />
                                            ) : (
                                                <Eye className="h-4 w-4" />
                                            )}
                                        </button>
                                    </div>
                                    {errors.password && (
                                        <p className="mt-1 text-xs text-red-300">{errors.password}</p>
                                    )}
                                </div>

                                {/* Confirm New Password */}
                                <div>
                                    <label className="block text-xs font-medium text-white/80">
                                        Confirm New Password
                                    </label>
                                    <div className="relative mt-1">
                                        <input
                                            type={showConfirmPassword ? 'text' : 'password'}
                                            value={data.password_confirmation}
                                            onChange={(e) => setData('password_confirmation', e.target.value)}
                                            className="w-full rounded-lg border border-white/15 bg-white/10 px-3 py-2 pr-10 text-sm text-white placeholder-white/40 focus:border-white/30 focus:outline-none focus:ring-2 focus:ring-white/20"
                                            placeholder="••••••••"
                                            required
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                                            className="absolute right-3 top-1/2 -translate-y-1/2 text-white/50 transition-colors hover:text-white"
                                            title={showConfirmPassword ? "Hide password" : "Show password"}
                                        >
                                            {showConfirmPassword ? (
                                                <EyeOff className="h-4 w-4" />
                                            ) : (
                                                <Eye className="h-4 w-4" />
                                            )}
                                        </button>
                                    </div>
                                    {errors.password_confirmation && (
                                        <p className="mt-1 text-xs text-red-300">{errors.password_confirmation}</p>
                                    )}
                                </div>

                                {/* Modal Actions */}
                                <div className="mt-6 flex justify-end gap-3 pt-2">
                                    <button
                                        type="button"
                                        onClick={resetFormAndVisibility}
                                        className="rounded-lg px-4 py-2 text-sm font-medium text-white/70 transition-colors hover:bg-white/10 hover:text-white"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-md transition-all hover:bg-emerald-500 disabled:opacity-50"
                                    >
                                        {processing ? 'Updating...' : 'Update Password'}
                                    </button>
                                </div>
                            </form>
                        </motion.div>
                    </div>
                )}
            </AnimatePresence>
        </>
    );
}