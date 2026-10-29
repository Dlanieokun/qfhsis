import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { KeyRound, MapPin, Power, Search, ShieldCheck, UserCheck, UserMinus, Users, X } from 'lucide-react';
import { useState } from 'react';

type Role = 'Administrator' | 'Doctor' | 'Public Health Nurse' | 'BHS' | 'BHW' | 'DOH' | 'PHO';
type Status = 'Active' | 'Inactive';

interface User {
    id: number;
    name: string;
    username?: string;
    email: string;
    role: Role;
    status: Status;
    barangay?: string[] | string | null;
    municipality?: string | null;
    province?: string | null;
    region?: string | null;
}

interface ManageUserProps {
    users: User[];
    filters?: { search?: string };
}

type ActionType = 'role' | 'status' | 'password';

const BASE = '/qfhsis/public/fhsis/manage-users';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'FHSIS Dashboard', href: '/qfhsis/public/fhsis/dashboard' },
    { title: 'Manage Users', href: BASE },
];

// Administrator is not assignable from this page.
const ROLE_OPTIONS: { value: Role; label: string }[] = [
    { value: 'DOH', label: 'DOH' },
    { value: 'PHO', label: 'PHO' },
    { value: 'Doctor', label: 'Doctor' },
    { value: 'Public Health Nurse', label: 'Public Health Nurse' },
    { value: 'BHS', label: 'Midwife' },
    { value: 'BHW', label: 'BHW' },
];

const roleLabel = (role: Role) => ROLE_OPTIONS.find((r) => r.value === role)?.label ?? role;

const parseArray = (val: unknown): string[] => {
    if (Array.isArray(val)) return val;
    if (typeof val === 'string') {
        try {
            const parsed = JSON.parse(val);
            return Array.isArray(parsed) ? parsed : [];
        } catch {
            return [];
        }
    }
    return [];
};

const formatAddress = (user: User): string => {
    const brgys = parseArray(user.barangay);
    const parts = [
        brgys.length > 0 ? `Brgy. ${brgys.join(', ')}` : '',
        user.municipality,
        user.province,
        user.region,
    ].filter(Boolean);
    return parts.join(', ');
};

export default function ManageUser({ users = [], filters }: ManageUserProps) {
    const [searchTerm, setSearchTerm] = useState(filters?.search || '');
    const [target, setTarget] = useState<User | null>(null);
    const [action, setAction] = useState<ActionType | null>(null);
    const [newRole, setNewRole] = useState<Role>('Public Health Nurse');
    const [newStatus, setNewStatus] = useState<Status>('Active');
    const [saving, setSaving] = useState(false);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(BASE, { search: searchTerm }, { preserveState: true });
    };

    const openAction = (user: User, type: ActionType) => {
        setTarget(user);
        setAction(type);
        setNewRole(user.role === 'Administrator' ? 'Public Health Nurse' : user.role);
        setNewStatus(user.status);
    };

    const closeAction = () => {
        if (saving) return;
        setTarget(null);
        setAction(null);
    };

    const submit = () => {
        if (!target || !action) return;

        const options = {
            preserveScroll: true,
            onStart: () => setSaving(true),
            onFinish: () => {
                setSaving(false);
                setTarget(null);
                setAction(null);
            },
        };

        if (action === 'password') {
            router.post(`${BASE}/${target.id}/reset-password`, {}, options);
        } else if (action === 'role') {
            router.put(`${BASE}/${target.id}/role`, { role: newRole }, options);
        } else {
            router.put(`${BASE}/${target.id}/status`, { status: newStatus }, options);
        }
    };

    const roleBadge = (role: Role) => {
        const styles: Record<Role, string> = {
            Administrator: 'bg-rose-50 text-rose-700 border-rose-100',
            Doctor: 'bg-blue-50 text-blue-700 border-blue-100',
            'Public Health Nurse': 'bg-emerald-50 text-emerald-700 border-emerald-100',
            BHS: 'bg-amber-50 text-amber-700 border-amber-100',
            BHW: 'bg-purple-50 text-purple-700 border-purple-100',
            DOH: 'bg-cyan-50 text-cyan-700 border-cyan-100',
            PHO: 'bg-indigo-50 text-indigo-700 border-indigo-100',
        };
        return (
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${styles[role] || 'bg-slate-50 text-slate-700 border-slate-100'}`}>
                {roleLabel(role)}
            </span>
        );
    };

    const modalTitle =
        action === 'role' ? 'Change Role' : action === 'status' ? 'Change Status' : 'Reset Password';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Manage Users" />

            <div className="max-w-5xl mx-auto p-4 sm:p-6 lg:p-8 bg-neutral-50 min-h-screen text-slate-800 antialiased">
                {/* Header */}
                <div className="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm mb-6">
                    <div className="flex items-center gap-2 text-blue-600 font-semibold text-sm tracking-wide uppercase">
                        <Users className="w-4 h-4" />
                        <span>Account Controls</span>
                    </div>
                    <h1 className="text-2xl font-bold text-slate-900 tracking-tight mt-1">Manage Users</h1>
                    <p className="text-slate-500 text-xs mt-0.5">
                        Users under your assigned address. Change a role or status, or reset a password.
                    </p>
                </div>

                {/* Search */}
                <div className="bg-white p-4 rounded-xl border border-slate-100 shadow-sm mb-6">
                    <form onSubmit={handleSearch} className="relative max-w-md w-full">
                        <input
                            type="text"
                            placeholder="Search by name..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl pl-10 pr-4 py-2 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition"
                        />
                        <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
                    </form>
                </div>

                {/* Table */}
                <div className="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    {users.length === 0 ? (
                        <div className="p-16 text-center text-slate-400 text-sm space-y-1">
                            <Users className="w-10 h-10 mx-auto text-slate-300 mb-2" />
                            <p className="font-semibold text-slate-600">No users found under your address</p>
                            <p className="text-xs">Users appear here when their address matches your assigned area.</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse text-sm text-slate-600">
                                <thead>
                                    <tr className="bg-slate-50/70 text-slate-400 font-bold uppercase text-[11px] border-b border-slate-100 tracking-wider">
                                        <th className="px-6 py-4">Name</th>
                                        <th className="px-6 py-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {users.map((user) => {
                                        const address = formatAddress(user);
                                        return (
                                            <tr key={user.id} className="hover:bg-slate-50/40 transition">
                                                <td className="px-6 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <div className="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center font-bold text-slate-700 text-sm border border-slate-200/50">
                                                            {user.name ? user.name.charAt(0) : 'U'}
                                                        </div>
                                                        <div>
                                                            <p className="font-semibold text-slate-900 text-sm">{user.name}</p>
                                                            <div className="flex items-center gap-2 mt-1">
                                                                {roleBadge(user.role)}
                                                                <span className={`inline-flex items-center gap-1 text-xs font-semibold ${user.status === 'Active' ? 'text-emerald-600' : 'text-slate-400'}`}>
                                                                    {user.status === 'Active' ? <UserCheck className="w-3.5 h-3.5" /> : <UserMinus className="w-3.5 h-3.5" />}
                                                                    {user.status}
                                                                </span>
                                                            </div>
                                                            {address && (
                                                                <p className="text-slate-400 text-[11px] flex items-center gap-1 mt-1">
                                                                    <MapPin className="w-3 h-3 text-slate-300 shrink-0" />
                                                                    {address}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="flex items-center justify-end gap-2 flex-wrap">
                                                        <button
                                                            onClick={() => openAction(user, 'role')}
                                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-100 rounded-lg hover:bg-blue-100 transition"
                                                        >
                                                            <ShieldCheck className="w-3.5 h-3.5" />
                                                            Change Role
                                                        </button>
                                                        <button
                                                            onClick={() => openAction(user, 'status')}
                                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-100 rounded-lg hover:bg-emerald-100 transition"
                                                        >
                                                            <Power className="w-3.5 h-3.5" />
                                                            Change Status
                                                        </button>
                                                        <button
                                                            onClick={() => openAction(user, 'password')}
                                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-100 rounded-lg hover:bg-amber-100 transition"
                                                        >
                                                            <KeyRound className="w-3.5 h-3.5" />
                                                            Reset Password
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Action modal */}
                {target && action && (
                    <div className="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
                        <div className="bg-white rounded-2xl border border-slate-100 shadow-xl w-full max-w-sm overflow-hidden">
                            <div className="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                                <h2 className="font-bold text-slate-900 tracking-tight">{modalTitle}</h2>
                                <button onClick={closeAction} className="p-1 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition">
                                    <X className="w-4 h-4" />
                                </button>
                            </div>

                            <div className="p-6 space-y-3">
                                <p className="text-sm text-slate-500">
                                    User: <span className="font-semibold text-slate-800">{target.name}</span>
                                </p>

                                {action === 'role' && (
                                    <div className="space-y-1">
                                        <label className="text-xs font-bold text-slate-600 uppercase tracking-wide">New Role</label>
                                        <select
                                            value={newRole}
                                            onChange={(e) => setNewRole(e.target.value as Role)}
                                            className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition"
                                        >
                                            {ROLE_OPTIONS.map((r) => (
                                                <option key={r.value} value={r.value}>{r.label}</option>
                                            ))}
                                        </select>
                                    </div>
                                )}

                                {action === 'status' && (
                                    <div className="space-y-1">
                                        <label className="text-xs font-bold text-slate-600 uppercase tracking-wide">New Status</label>
                                        <select
                                            value={newStatus}
                                            onChange={(e) => setNewStatus(e.target.value as Status)}
                                            className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition"
                                        >
                                            <option value="Active">Active</option>
                                            <option value="Inactive">Inactive</option>
                                        </select>
                                    </div>
                                )}

                                {action === 'password' && (
                                    <p className="text-sm text-slate-500">
                                        Are you sure you want to reset the password for this user?
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center gap-3 justify-end p-4 border-t border-slate-100 bg-slate-50/50">
                                <button
                                    type="button"
                                    onClick={closeAction}
                                    disabled={saving}
                                    className="px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 disabled:opacity-50 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    onClick={submit}
                                    disabled={saving}
                                    className={`px-4 py-2 text-sm font-semibold text-white rounded-xl disabled:bg-slate-300 transition shadow-sm ${action === 'password' ? 'bg-amber-600 hover:bg-amber-700' : 'bg-blue-600 hover:bg-blue-700'}`}
                                >
                                    {saving ? 'Saving...' : action === 'password' ? 'Reset' : 'Save'}
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
