import { Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    LayoutDashboard,
    FileText,
    ClipboardList,
    BarChart3,
    Users,
    LogOut,
    Stethoscope,
} from 'lucide-react';
import { type SharedData } from '@/types';

const NAV_ITEMS = [
    { label: 'Dashboard',        href: '/fhsis-system/public/fhsis/dashboard',         icon: LayoutDashboard },
    { label: 'PHO Forms',        href: '/fhsis-system/public/fhsis/pho',               icon: FileText },
    { label: 'Nurse Submittion', href: '/fhsis-system/public/fhsis/public-nurse',      icon: ClipboardList },
    { label: 'General Report',   href: '/fhsis-system/public/fhsis/reports',           icon: BarChart3 },
    { label: 'User Management',  href: '/fhsis-system/public/fhsis/users',             icon: Users },
];

export default function AppSidebar() {
    const { auth, url } = usePage<SharedData & { url: string }>().props;
    const currentPath = typeof window !== 'undefined' ? window.location.pathname : (url ?? '');

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
            <div className="flex items-center gap-2.5 px-5 py-5">
                <motion.div 
                    initial={{ scale: 0, rotate: -180 }}
                    animate={{ scale: 1, rotate: 0 }}
                    transition={{ type: "spring", stiffness: 260, damping: 20, delay: 0.2 }}
                    className="flex h-9 w-9 items-center justify-center rounded-full bg-white/15"
                >
                    <Stethoscope className="h-5 w-5 text-white" />
                </motion.div>
                <motion.span 
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ delay: 0.4 }}
                    className="text-lg font-bold tracking-wide"
                >
                    FHSIS
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

                    {/* Logout */}
                    <Link
                        href="/fhsis-system/public/logout"
                        method="post"
                        as="button"
                        className="rounded p-1 text-white/50 transition-all hover:text-red-400 hover:scale-110"
                        title="Log out"
                    >
                        <LogOut className="h-4 w-4" />
                    </Link>
                </div>
            </motion.div>
        </motion.aside>
        
    );
}