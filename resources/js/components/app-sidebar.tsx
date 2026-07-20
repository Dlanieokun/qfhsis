import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { BookOpen, Folder, LayoutGrid, FileText, Users, ClipboardList, HeartPulseIcon } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/fhsis-system/public/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'PHO Forms',           
        url: '/fhsis-system/public/fhsis/pho',              
        icon: ClipboardList,            
    },
    {
        title: 'Nurse Submittion',           
        url: '/fhsis-system/public/fhsis/public-nurse',              
        icon: HeartPulseIcon,            
    },
    {
        title: 'General Report',
        url: '/fhsis-system/public/fhsis/reports',
        icon: FileText,
    },
    {
        title: 'User Management',
        url: '/fhsis-system/public/fhsis/users',
        icon: Users,
    },
];

const footerNavItems: NavItem[] = [
    // {
    //     title: 'Repository',
    //     url: 'https://github.com/laravel/react-starter-kit',
    //     icon: Folder,
    // },
    // {
    //     title: 'Documentation',
    //     url: 'https://laravel.com/docs/starter-kits',
    //     icon: BookOpen,
    // },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset" className="bg-white border-r border-slate-100 text-slate-800">
            <SidebarHeader className="border-b border-slate-50 px-4 py-3 bg-white">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="hover:bg-slate-50 transition-colors">
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="bg-white px-2 py-4">
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter className="bg-white border-t border-slate-50 p-2">
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}