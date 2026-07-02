import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { type BreadcrumbItem } from '@/types';

export default function AppSidebarLayout({ children, breadcrumbs = [] }: { children: React.ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
    return (
        <AppShell variant="sidebar" className="bg-neutral-50">
            <AppSidebar />
            <AppContent variant="sidebar" className="bg-neutral-50">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <div className="bg-neutral-50 min-h-[calc(100vh-4rem)]">
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}