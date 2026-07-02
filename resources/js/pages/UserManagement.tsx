import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { 
    Edit2, 
    Mail, 
    MapPin,
    Plus, 
    Search, 
    ShieldAlert, 
    ShieldCheck, 
    Trash2, 
    UserCheck, 
    UserMinus, 
    Users, 
    X 
} from 'lucide-react';
import { useState } from 'react';

interface User {
    id: number;
    name: string;
    email: string;
    role: 'Administrator' | 'Doctor' | 'Public Health Nurse' | 'BHS' | 'BHW';
    status: 'Active' | 'Inactive';
    assigned_facility?: string;
    barangay?: string;
    municipality?: string;
    province?: string;
    created_at: string;
}

interface UserManagementProps {
    users: User[];
    filters: {
        search?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'FHSIS Dashboard',
        href: '/fhsis/dashboard',
    },
    {
        title: 'User Management',
        href: '/fhsis/users',
    },
];

export default function UserManagement({ users = [], filters }: UserManagementProps) {
    const [searchTerm, setSearchTerm] = useState(filters?.search || '');
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<User | null>(null);

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        name: '',
        email: '',
        role: 'Public Health Nurse' as User['role'],
        status: 'Active' as User['status'],
        assigned_facility: '',
        barangay: '',
        municipality: '',
        province: '',
    });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/fhsis/users', { search: searchTerm }, { preserveState: true });
    };

    const openCreateModal = () => {
        setEditingUser(null);
        clearErrors();
        reset();
        setIsModalOpen(true);
    };

    const openEditModal = (user: User) => {
        setEditingUser(user);
        clearErrors();
        setData({
            name: user.name,
            email: user.email,
            role: user.role,
            status: user.status,
            assigned_facility: user.assigned_facility || '',
            barangay: user.barangay || '',
            municipality: user.municipality || '',
            province: user.province || '',
        });
        setIsModalOpen(true);
    };

    const closeModal = () => {
        setIsModalOpen(false);
        setEditingUser(null);
        reset();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingUser) {
            // FIX: Explicitly passed the form data object as the 2nd parameter
            put(`/fhsis/users/${editingUser.id}`, data, {
                onSuccess: () => closeModal(),
            });
        } else {
            // FIX: Explicitly passed the form data object as the 2nd parameter
            post('/fhsis/users', data, {
                onSuccess: () => closeModal(),
            });
        }
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to revoke system privileges for this user profile?')) {
            router.delete(`/fhsis/users/${id}`);
        }
    };

    const getRoleBadge = (role: User['role']) => {
        const styles = {
            'Administrator': 'bg-rose-50 text-rose-700 border-rose-100/80',
            'Doctor': 'bg-blue-50 text-blue-700 border-blue-100/80',
            'Public Health Nurse': 'bg-emerald-50 text-emerald-700 border-emerald-100/80',
            'BHS': 'bg-amber-50 text-amber-700 border-amber-100/80',
            'BHW': 'bg-purple-50 text-purple-700 border-purple-100/80',
        };
        return (
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${styles[role] || 'bg-slate-50 text-slate-700 border-slate-100'}`}>
                {role}
            </span>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="System User Management" />

            <div className="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 bg-neutral-50 min-h-screen text-slate-800 antialiased">
                
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl border border-slate-100 shadow-sm mb-6">
                    <div>
                        <div className="flex items-center gap-2 text-blue-600 font-semibold text-sm tracking-wide uppercase">
                            <Users className="w-4 h-4" />
                            <span>Facility Access Controls</span>
                        </div>
                        <h1 className="text-2xl font-bold text-slate-900 tracking-tight mt-1">User Management Accounts</h1>
                        <p className="text-slate-500 text-xs mt-0.5">Provision personnel roles, system credentials, and regional health allocations</p>
                    </div>

                    <button
                        onClick={openCreateModal}
                        className="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm self-stretch sm:self-auto"
                    >
                        <Plus className="w-4 h-4" />
                        <span>Add New Account</span>
                    </button>
                </div>

                <div className="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex items-center justify-between mb-6">
                    <form onSubmit={handleSearch} className="relative flex-1 max-w-md w-full">
                        <input
                            type="text"
                            placeholder="Search name, email, facility, or locality registry..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl pl-10 pr-4 py-2 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition"
                        />
                        <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
                    </form>
                </div>

                <div className="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    {users.length === 0 ? (
                        <div className="p-16 text-center text-slate-400 text-sm space-y-1">
                            <Users className="w-10 h-10 mx-auto text-slate-300 mb-2" />
                            <p className="font-semibold text-slate-600">No active accounts located</p>
                            <p className="text-xs">Adjust your lookup values or provision a new registry account profile above.</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse text-sm text-slate-600">
                                <thead>
                                    <tr className="bg-slate-50/70 text-slate-400 font-bold uppercase text-[11px] border-b border-slate-100 tracking-wider">
                                        <th className="px-6 py-4">Personnel Information</th>
                                        <th className="px-6 py-4">Assigned Role</th>
                                        <th className="px-6 py-4">Facility Allocation</th>
                                        <th className="px-6 py-4">Jurisdiction Catchment Area</th>
                                        <th className="px-6 py-4">Status</th>
                                        <th className="px-6 py-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 text-xs">
                                    {users.map((user) => (
                                        <tr key={user.id} className="hover:bg-slate-50/40 transition">
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center font-bold text-slate-700 text-sm border border-slate-200/50">
                                                        {user.name ? user.name.charAt(0) : 'U'}
                                                    </div>
                                                    <div>
                                                        <p className="font-semibold text-slate-900 text-sm">{user.name}</p>
                                                        <p className="text-slate-400 flex items-center gap-1 mt-0.5">
                                                            <Mail className="w-3 h-3" />
                                                            {user.email}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">{getRoleBadge(user.role)}</td>
                                            <td className="px-6 py-4 font-medium text-slate-700">
                                                {user.assigned_facility || <span className="text-slate-400 italic">Unassigned (HQ)</span>}
                                            </td>
                                            <td className="px-6 py-4">
                                                {user.barangay || user.municipality || user.province ? (
                                                    <div className="space-y-0.5">
                                                        <p className="font-medium text-slate-800">
                                                            {user.barangay && `Brgy. ${user.barangay}`}
                                                        </p>
                                                        <p className="text-slate-400 text-[11px] flex items-center gap-1">
                                                            <MapPin className="w-3 h-3 text-slate-300 shrink-0" />
                                                            {[user.municipality, user.province].filter(Boolean).join(', ')}
                                                        </p>
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400 italic">Global Access</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4">
                                                <span className={`inline-flex items-center gap-1 font-semibold ${user.status === 'Active' ? 'text-emerald-600' : 'text-slate-400'}`}>
                                                    {user.status === 'Active' ? <UserCheck className="w-3.5 h-3.5" /> : <UserMinus className="w-3.5 h-3.5" />}
                                                    {user.status}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <button
                                                        onClick={() => openEditModal(user)}
                                                        className="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 border border-transparent hover:border-blue-100 rounded-lg transition"
                                                    >
                                                        <Edit2 className="w-4 h-4" />
                                                    </button>
                                                    <button
                                                        onClick={() => handleDelete(user.id)}
                                                        className="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-rose-50 border border-transparent hover:border-rose-100 rounded-lg transition"
                                                    >
                                                        <Trash2 className="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {isModalOpen && (
                    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
                        <div className="bg-white rounded-2xl border border-slate-100 shadow-xl w-full max-w-md overflow-hidden transform transition-all animate-in fade-in zoom-in-95 duration-150">
                            
                            <div className="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                                <div className="flex items-center gap-2">
                                    <div className="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                                        {editingUser ? <ShieldCheck className="w-4 h-4" /> : <ShieldAlert className="w-4 h-4" />}
                                    </div>
                                    <h2 className="font-bold text-slate-900 tracking-tight">
                                        {editingUser ? 'Update Account Clearance' : 'Provision System Account'}
                                    </h2>
                                </div>
                                <button onClick={closeModal} className="p-1 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition">
                                    <X className="w-4 h-4" />
                                </button>
                            </div>

                            <form onSubmit={handleSubmit} className="p-6 space-y-4 max-h-[calc(100vh-160px)] overflow-y-auto">
                                <div className="space-y-1">
                                    <label className="text-xs font-bold text-slate-600 uppercase tracking-wide">Personnel Name</label>
                                    <input
                                        type="text"
                                        required
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition"
                                        placeholder="Dr. Eleanor Vance"
                                    />
                                    {errors.name && <p className="text-rose-600 text-xs mt-0.5">{errors.name}</p>}
                                </div>

                                <div className="space-y-1">
                                    <label className="text-xs font-bold text-slate-600 uppercase tracking-wide">Email Destination Registry</label>
                                    <input
                                        type="email"
                                        required
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition"
                                        placeholder="eleanor.vance@facility.gov"
                                    />
                                    {errors.email && <p className="text-rose-600 text-xs mt-0.5">{errors.email}</p>}
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-1">
                                        <label className="text-xs font-bold text-slate-600 uppercase tracking-wide">System Role</label>
                                        <select
                                            value={data.role}
                                            onChange={(e) => setData('role', e.target.value as User['role'])}
                                            className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition"
                                        >
                                            <option value="Administrator">Administrator</option>
                                            <option value="Doctor">Doctor</option>
                                            <option value="Public Health Nurse">Public Health Nurse</option>
                                            <option value="BHS">BHS</option>
                                            <option value="BHW">BHW</option>
                                        </select>
                                    </div>

                                    <div className="space-y-1">
                                        <label className="text-xs font-bold text-slate-600 uppercase tracking-wide">Account Status</label>
                                        <select
                                            value={data.status}
                                            onChange={(e) => setData('status', e.target.value as User['status'])}
                                            className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition"
                                        >
                                            <option value="Active">Authorized Active</option>
                                            <option value="Inactive">Suspended Hold</option>
                                        </select>
                                    </div>
                                </div>

                                <div className="space-y-1">
                                    <label className="text-xs font-bold text-slate-600 uppercase tracking-wide">Assigned Health Facility</label>
                                    <input
                                        type="text"
                                        value={data.assigned_facility}
                                        onChange={(e) => setData('assigned_facility', e.target.value)}
                                        className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition"
                                        placeholder="District Health Center Hub 1"
                                    />
                                    {errors.assigned_facility && <p className="text-rose-600 text-xs mt-0.5">{errors.assigned_facility}</p>}
                                </div>

                                {/* Geographic Scope Section */}
                                <div className="pt-2 border-t border-slate-100">
                                    <span className="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest block mb-3">Regional Catchment Jurisdiction</span>
                                    
                                    <div className="space-y-3">
                                        <div className="space-y-1">
                                            <label className="text-xs font-bold text-slate-600 uppercase tracking-wide">Barangay</label>
                                            <input
                                                type="text"
                                                value={data.barangay}
                                                onChange={(e) => setData('barangay', e.target.value)}
                                                className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition"
                                                placeholder="Central Barangay"
                                            />
                                            {errors.barangay && <p className="text-rose-600 text-xs mt-0.5">{errors.barangay}</p>}
                                        </div>

                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-1">
                                                <label className="text-xs font-bold text-slate-600 uppercase tracking-wide">Municipality / City</label>
                                                <input
                                                    type="text"
                                                    value={data.municipality}
                                                    onChange={(e) => setData('municipality', e.target.value)}
                                                    className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition"
                                                    placeholder="City of Baybay"
                                                />
                                                {errors.municipality && <p className="text-rose-600 text-xs mt-0.5">{errors.municipality}</p>}
                                            </div>

                                            <div className="space-y-1">
                                                <label className="text-xs font-bold text-slate-600 uppercase tracking-wide">Province</label>
                                                <input
                                                    type="text"
                                                    value={data.province}
                                                    onChange={(e) => setData('province', e.target.value)}
                                                    className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 transition"
                                                    placeholder="Leyte"
                                                />
                                                {errors.province && <p className="text-rose-600 text-xs mt-0.5">{errors.province}</p>}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center gap-3 justify-end pt-4 border-t border-slate-100 mt-6">
                                    <button
                                        type="button"
                                        onClick={closeModal}
                                        className="px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 disabled:bg-slate-300 transition shadow-sm"
                                    >
                                        {processing ? 'Processing Record...' : editingUser ? 'Update Profile' : 'Commit Credentials'}
                                    </button>
                                </div>
                            </form>

                        </div>
                    </div>
                )}

            </div>
        </AppLayout>
    );
}