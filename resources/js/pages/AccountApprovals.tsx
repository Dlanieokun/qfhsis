import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Building2, Check, CheckCircle2, ClipboardCheck, Clock, Mail, MapPin, Phone, Search, UserCheck, X } from 'lucide-react';
import { useMemo, useState } from 'react';

interface PendingUser {
    id: number;
    name: string;
    email: string;
    role: string;
    status: string;
    assigned_facility?: string | null;
    contact_number?: string | number | null;
    barangay?: string[] | string | null;
    municipality?: string | null;
    province?: string | null;
    region?: string | null;
    created_at: string;
}

interface AccountApprovalsProps {
    users: PendingUser[];
    stats: { pending: number; active: number };
}

type FlashProps = { flash?: { success?: string | null; error?: string | null } };

const BASE = '/qfhsis/public/fhsis/approvals';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'FHSIS Dashboard', href: '/qfhsis/public/fhsis/dashboard' },
    { title: 'Account Approvals', href: BASE },
];

const ROLES = [
    { value: 'Administrator', label: 'Administrator' },
    { value: 'DOH', label: 'DOH' },
    { value: 'Doctor', label: 'Doctor' },
    { value: 'Public Health Nurse', label: 'Public Health Nurse' },
    { value: 'BHS', label: 'Midwife' },
    { value: 'BHW', label: 'BHW' },
];

const parseArray = (val: unknown): string[] => {
    if (Array.isArray(val)) return val as string[];
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

// Older accounts may hold the number as an integer (leading 0 lost): 9171234567 -> 09171234567
const formatPhone = (v: string | number) => {
    const s = String(v);
    return s.length === 10 && s.startsWith('9') ? `0${s}` : s;
};

const roleLabel = (value: string) => ROLES.find((r) => r.value === value)?.label ?? value;

const initials = (name: string) =>
    name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase())
        .join('');

const timeAgo = (iso: string) => {
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins} min ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs} hr${hrs > 1 ? 's' : ''} ago`;
    const days = Math.floor(hrs / 24);
    if (days < 30) return `${days} day${days > 1 ? 's' : ''} ago`;
    return new Date(iso).toLocaleDateString();
};

export default function AccountApprovals({ users, stats }: AccountApprovalsProps) {
    const { flash } = usePage<FlashProps>().props;

    const [search, setSearch] = useState('');
    const [roles, setRoles] = useState<Record<number, string>>({});
    const [busyId, setBusyId] = useState<number | null>(null);

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return users;
        return users.filter((u) =>
            [u.name, u.email, u.assigned_facility, u.municipality, u.province, u.region]
                .filter(Boolean)
                .some((v) => String(v).toLowerCase().includes(q)),
        );
    }, [users, search]);

    const roleFor = (u: PendingUser) => roles[u.id] ?? u.role ?? 'BHW';

    const approve = (u: PendingUser) => {
        setBusyId(u.id);
        router.post(`${BASE}/${u.id}/approve`, { role: roleFor(u) }, { preserveScroll: true, onFinish: () => setBusyId(null) });
    };

    const reject = (u: PendingUser) => {
        // prompt() returns null if the admin cancels; an empty string means "no reason".
        const reason = prompt(`Reject the account request from ${u.name}?\n\nOptional reason (included in the email to the applicant):`, '');
        if (reason === null) return;
        setBusyId(u.id);
        router.delete(`${BASE}/${u.id}`, { data: { reason }, preserveScroll: true, onFinish: () => setBusyId(null) });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Account Approvals" />

            <div className="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 bg-neutral-50 min-h-screen text-slate-800 antialiased">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl border border-slate-100 shadow-sm mb-6">
                    <div>
                        <div className="flex items-center gap-2 text-blue-600 font-semibold text-sm tracking-wide uppercase">
                            <ClipboardCheck className="w-4 h-4" />
                            <span>Facility Access Controls</span>
                        </div>
                        <h1 className="text-2xl font-bold text-slate-900 tracking-tight mt-1">Account Approvals</h1>
                        <p className="text-slate-500 text-xs mt-0.5">Review self-registered accounts, assign a role, and grant system access</p>
                    </div>

                    <div className="flex gap-3 self-stretch sm:self-auto">
                        <div className="flex-1 sm:flex-none rounded-xl bg-amber-50 border border-amber-100 px-4 py-2.5">
                            <div className="flex items-center gap-1.5 text-amber-700 text-[11px] font-bold uppercase tracking-wider">
                                <Clock className="w-3.5 h-3.5" /> Pending
                            </div>
                            <div className="text-2xl font-bold text-amber-800 leading-tight">{stats.pending}</div>
                        </div>
                        <div className="flex-1 sm:flex-none rounded-xl bg-emerald-50 border border-emerald-100 px-4 py-2.5">
                            <div className="flex items-center gap-1.5 text-emerald-700 text-[11px] font-bold uppercase tracking-wider">
                                <UserCheck className="w-3.5 h-3.5" /> Active
                            </div>
                            <div className="text-2xl font-bold text-emerald-800 leading-tight">{stats.active}</div>
                        </div>
                    </div>
                </div>

                {/* Flash messages */}
                {flash?.success && (
                    <div className="mb-4 flex items-center gap-2 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm font-medium text-emerald-800 animate-in fade-in zoom-in-95 duration-300">
                        <CheckCircle2 className="w-4 h-4 shrink-0" />
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-4 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm font-medium text-rose-700">{flash.error}</div>
                )}

                {/* Search */}
                <div className="bg-white p-4 rounded-xl border border-slate-100 shadow-sm mb-6">
                    <div className="relative max-w-md w-full">
                        <input
                            type="text"
                            placeholder="Search name, email, facility, or locality..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl pl-10 pr-4 py-2 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition"
                        />
                        <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
                    </div>
                </div>

                {/* Pending list */}
                {filtered.length === 0 ? (
                    <div className="bg-white rounded-2xl border border-slate-100 shadow-sm p-16 text-center text-slate-400 text-sm space-y-1">
                        <ClipboardCheck className="w-10 h-10 mx-auto text-slate-300 mb-2" />
                        <p className="font-semibold text-slate-600">{users.length === 0 ? 'No accounts waiting for approval' : 'No matching accounts'}</p>
                        <p className="text-xs">
                            {users.length === 0 ? 'New registrations will appear here until you approve them.' : 'Try a different search term.'}
                        </p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
                        {filtered.map((u) => {
                            const barangays = parseArray(u.barangay);
                            const busy = busyId === u.id;

                            return (
                                <div key={u.id} className="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex flex-col gap-4">
                                    <div className="flex items-start gap-3">
                                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-teal-50 text-teal-700 text-sm font-bold border border-teal-100">
                                            {initials(u.name)}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <div className="font-semibold text-slate-900 truncate">{u.name}</div>
                                            <div className="flex items-center gap-1.5 text-xs text-slate-500 truncate">
                                                <Mail className="w-3.5 h-3.5 shrink-0" />
                                                <span className="truncate">{u.email}</span>
                                            </div>
                                        </div>
                                        <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-amber-50 border border-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                                            <Clock className="w-3 h-3" />
                                            {timeAgo(u.created_at)}
                                        </span>
                                    </div>

                                    <div className="space-y-2 text-xs text-slate-600">
                                        <div className="flex items-center gap-1.5">
                                            <Building2 className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                            <span>{u.assigned_facility || <span className="italic text-slate-400">No facility provided</span>}</span>
                                        </div>
                                        <div className="flex items-center gap-1.5">
                                            <Phone className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                            <span>
                                                {u.contact_number ? formatPhone(u.contact_number) : <span className="italic text-slate-400">No contact number</span>}
                                            </span>
                                        </div>
                                        <div className="flex items-start gap-1.5">
                                            <MapPin className="w-3.5 h-3.5 text-slate-400 shrink-0 mt-0.5" />
                                            <div>
                                                <div>{[u.municipality, u.province, u.region].filter(Boolean).join(', ') || '—'}</div>
                                                {barangays.length > 0 && (
                                                    <div className="mt-1.5 flex flex-wrap gap-1">
                                                        {barangays.slice(0, 4).map((b) => (
                                                            <span key={b} className="rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600">
                                                                {b}
                                                            </span>
                                                        ))}
                                                        {barangays.length > 4 && (
                                                            <span className="rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-500">
                                                                +{barangays.length - 4} more
                                                            </span>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex flex-col sm:flex-row sm:items-end gap-3 pt-3 border-t border-slate-100">
                                        <div className="flex-1 space-y-1">
                                            <label className="text-[11px] font-bold text-slate-500 uppercase tracking-wide">
                                                Assign role
                                                <span className="ml-1 font-normal normal-case tracking-normal text-slate-400">(requested: {roleLabel(u.role)})</span>
                                            </label>
                                            <select
                                                value={roleFor(u)}
                                                disabled={busy}
                                                onChange={(e) => setRoles((r) => ({ ...r, [u.id]: e.target.value }))}
                                                className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition"
                                            >
                                                {ROLES.map((r) => (
                                                    <option key={r.value} value={r.value}>
                                                        {r.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        <div className="flex gap-2">
                                            <button
                                                onClick={() => reject(u)}
                                                disabled={busy}
                                                className="inline-flex items-center justify-center gap-1.5 px-4 py-2 text-sm font-semibold text-rose-600 bg-white border border-rose-200 rounded-xl hover:bg-rose-50 transition disabled:opacity-50"
                                            >
                                                <X className="w-4 h-4" />
                                                Reject
                                            </button>
                                            <button
                                                onClick={() => approve(u)}
                                                disabled={busy}
                                                className="inline-flex items-center justify-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm disabled:opacity-50"
                                            >
                                                <Check className="w-4 h-4" />
                                                {busy ? 'Working…' : 'Approve'}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}