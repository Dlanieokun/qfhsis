import { type PropsWithChildren } from 'react';
import { Head } from '@inertiajs/react';
import { motion } from 'framer-motion';
import AppSidebar from '@/components/app-sidebar';
import { type BreadcrumbItem } from '@/types';

interface AppLayoutProps extends PropsWithChildren {
    breadcrumbs?: BreadcrumbItem[];
}

export default function AppLayout({ children, breadcrumbs: _breadcrumbs }: AppLayoutProps) {
    return (
        <div className="flex h-screen overflow-hidden bg-slate-100">
            {/* Sidebar */}
            <AppSidebar />

            {/* Main scrollable area */}
            <main className="flex-1 overflow-y-auto bg-slate-100">
                {/* Page Transition Wrapper */}
                <motion.div
                    initial={{ opacity: 0, y: 15 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.4, ease: "easeOut" }}
                    className="h-full"
                >
                    {children}
                </motion.div>
            </main>
        </div>
    );
}