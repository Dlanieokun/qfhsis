import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { motion } from 'framer-motion';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    return (
        <motion.header 
            initial={{ opacity: 0, y: -20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.3 }}
            className="border-slate-100 bg-white flex h-16 shrink-0 items-center gap-2 border-b px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
        >
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1 text-slate-500 hover:text-blue-600 hover:bg-slate-50 rounded-lg transition-colors hover:scale-105 active:scale-95" />
                <div className="h-4 w-[1px] bg-slate-200 mx-1 md:block hidden" />
                
                {/* Wrap breadcrumbs to fade them in smoothly */}
                <motion.div
                    initial={{ opacity: 0, x: -10 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ delay: 0.2, duration: 0.3 }}
                >
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </motion.div>
            </div>
        </motion.header>
    );
}