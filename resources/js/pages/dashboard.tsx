import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { 
    Activity, 
    Baby, 
    ClipboardList, 
    FileText, 
    HeartPulse, 
    PlusCircle, 
    TrendingUp, 
    Users 
} from 'lucide-react';

interface Report {
    id: number;
    reporting_year: string;
    reporting_quarter: string;
    total_pregnant_tracked: number;
    completed_4_anc_visits: number;
    fully_immunized_children: number;
    infants_exclusive_breastfed: number;
    status: string;
}

interface Props {
    auth: {
        user: {
            name: string;
            role: string;
            assigned_facility?: string;
        };
    };
    reports?: Report[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'FHSIS Dashboard',
        href: '/fhsis/dashboard',
    },
];

export default function Dashboard({ auth, reports = [] }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        reporting_year: new Date().getFullYear().toString(),
        reporting_quarter: 'Q1',
        total_pregnant_tracked: 0,
        completed_4_anc_visits: 0,
        fully_immunized_children: 0,
        infants_exclusive_breastfed: 0,
    });

    const totalSubmittedReports = reports.length;
    const totalMaternalCases = reports.reduce((acc, curr) => acc + curr.total_pregnant_tracked, 0);
    const totalImmunized = reports.reduce((acc, curr) => acc + curr.fully_immunized_children, 0);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/fhsis/reports', {
            onSuccess: () => reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="FHSIS Core Health Indicators" />

            <div className="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 bg-neutral-50 min-h-screen text-slate-800 antialiased">
                
                {/* Upper Narrative Headline */}
                <div className="mb-8">
                    <h1 className="text-2xl font-bold text-slate-900 tracking-tight">FHSIS Management Portal</h1>
                    <p className="text-slate-500 text-sm mt-0.5">Logged in as: <span className="font-semibold text-slate-700">{auth.user.name}</span> • Facility Operator</p>
                </div>

                {/* Aggregated Statistical Cards Summary Banners */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                    <div className="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-4">
                        <div className="p-3 bg-blue-50 text-blue-600 rounded-xl"><ClipboardList className="w-5 h-5" /></div>
                        <div>
                            <p className="text-xs font-medium text-slate-400 uppercase">Submissions</p>
                            <h4 className="text-xl font-bold text-slate-900 mt-0.5">{totalSubmittedReports} Logs</h4>
                        </div>
                    </div>
                    <div className="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-4">
                        <div className="p-3 bg-blue-50 text-blue-600 rounded-xl"><HeartPulse className="w-5 h-5" /></div>
                        <div>
                            <p className="text-xs font-medium text-slate-400 uppercase">Pregnancies Tracked</p>
                            <h4 className="text-xl font-bold text-slate-900 mt-0.5">{totalMaternalCases} Cases</h4>
                        </div>
                    </div>
                    <div className="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-4">
                        <div className="p-3 bg-blue-50 text-blue-600 rounded-xl"><Baby className="w-5 h-5" /></div>
                        <div>
                            <p className="text-xs font-medium text-slate-400 uppercase">Immunized Children</p>
                            <h4 className="text-xl font-bold text-slate-900 mt-0.5">{totalImmunized} Patients</h4>
                        </div>
                    </div>
                    <div className="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-4">
                        <div className="p-3 bg-emerald-50 text-emerald-600 rounded-xl"><TrendingUp className="w-5 h-5" /></div>
                        <div>
                            <p className="text-xs font-medium text-slate-400 uppercase">System Status</p>
                            <h4 className="text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded-full inline-block mt-1">Operational</h4>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                    
                    {/* Form Layout Entry Block */}
                    <div className="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden lg:col-span-1">
                        <div className="p-6 border-b border-slate-100 flex items-center gap-2 font-bold text-slate-900">
                            <PlusCircle className="w-5 h-5 text-blue-600" />
                            <h2>New Quarterly Indicator Entry</h2>
                        </div>
                        
                        <form onSubmit={handleSubmit} className="p-6 space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-1">
                                    <label className="text-xs font-semibold text-slate-600">Year</label>
                                    <input 
                                        type="text" 
                                        maxLength={4}
                                        value={data.reporting_year} 
                                        onChange={e => setData('reporting_year', e.target.value)}
                                        className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 transition" 
                                    />
                                    {errors.reporting_year && <p className="text-rose-600 text-xs">{errors.reporting_year}</p>}
                                </div>
                                <div className="space-y-1">
                                    <label className="text-xs font-semibold text-slate-600">Quarter Target</label>
                                    <select 
                                        value={data.reporting_quarter} 
                                        onChange={e => setData('reporting_quarter', e.target.value)}
                                        className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 transition"
                                    >
                                        <option value="Q1">1st Quarter (Q1)</option>
                                        <option value="Q2">2nd Quarter (Q2)</option>
                                        <option value="Q3">3rd Quarter (Q3)</option>
                                        <option value="Q4">4th Quarter (Q4)</option>
                                    </select>
                                </div>
                            </div>

                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-slate-600">Total Pregnant Tracked</label>
                                <input 
                                    type="number" 
                                    min={0}
                                    value={data.total_pregnant_tracked} 
                                    onChange={e => setData('total_pregnant_tracked', parseInt(e.target.value) || 0)}
                                    className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 transition" 
                                />
                                {errors.total_pregnant_tracked && <p className="text-rose-600 text-xs">{errors.total_pregnant_tracked}</p>}
                            </div>

                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-slate-600">Completed 4 Prenatal Checks (ANC)</label>
                                <input 
                                    type="number" 
                                    min={0}
                                    value={data.completed_4_anc_visits} 
                                    onChange={e => setData('completed_4_anc_visits', parseInt(e.target.value) || 0)}
                                    className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 transition" 
                                />
                                {errors.completed_4_anc_visits && <p className="text-rose-600 text-xs">{errors.completed_4_anc_visits}</p>}
                            </div>

                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-slate-600">Fully Immunized Children (FIC)</label>
                                <input 
                                    type="number" 
                                    min={0}
                                    value={data.fully_immunized_children} 
                                    onChange={e => setData('fully_immunized_children', parseInt(e.target.value) || 0)}
                                    className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 transition" 
 // Custom focus handling
                                />
                                {errors.fully_immunized_children && <p className="text-rose-600 text-xs">{errors.fully_immunized_children}</p>}
                            </div>

                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-slate-600">Exclusive Breastfed Infants</label>
                                <input 
                                    type="number" 
                                    min={0}
                                    value={data.infants_exclusive_breastfed} 
                                    onChange={e => setData('infants_exclusive_breastfed', parseInt(e.target.value) || 0)}
                                    className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-800 outline-none focus:border-blue-500 transition" 
                                />
                                {errors.infants_exclusive_breastfed && <p className="text-rose-600 text-xs">{errors.infants_exclusive_breastfed}</p>}
                            </div>

                            <button 
                                type="submit" 
                                disabled={processing}
                                className="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 disabled:bg-slate-300 transition shadow-sm mt-2"
                            >
                                {processing ? 'Processing Record...' : 'Submit Indicators'}
                            </button>
                        </form>
                    </div>

                    {/* Historical Logs Review Table Block */}
                    <div className="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden lg:col-span-2">
                        <div className="p-6 border-b border-slate-100 flex items-center gap-2 font-bold text-slate-900">
                            <Activity className="w-5 h-5 text-blue-600" />
                            <h2>Submitted Indicators History Log</h2>
                        </div>
                        
                        <div className="p-0">
                            {reports.length === 0 ? (
                                <div className="p-12 text-center text-slate-400 text-sm space-y-1">
                                    <FileText className="w-8 h-8 mx-auto text-slate-300 mb-2" />
                                    <p className="font-medium text-slate-600">No logs on record</p>
                                    <p className="text-xs">Submit your facility's quarterly matrix via the setup panel.</p>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left border-collapse text-xs text-slate-600">
                                        <thead>
                                            <tr className="bg-slate-50 text-slate-400 font-bold uppercase border-b border-slate-100">
                                                <th className="px-6 py-3">Reporting Period</th>
                                                <th className="px-6 py-3">Maternal Tracker</th>
                                                <th className="px-6 py-3">Immunized Base</th>
                                                <th className="px-6 py-3">Exclusive Breastfed</th>
                                                <th className="px-6 py-3">Log Status</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {reports.map((report) => (
                                                <tr key={report.id} className="hover:bg-slate-50/50 transition">
                                                    <td className="px-6 py-3.5 font-semibold text-slate-900">
                                                        FY {report.reporting_year} — {report.reporting_quarter}
                                                    </td>
                                                    <td className="px-6 py-3.5">
                                                        {report.total_pregnant_tracked} <span className="text-slate-400 text-[10px]">({report.completed_4_anc_visits} ANC)</span>
                                                    </td>
                                                    <td className="px-6 py-3.5 font-medium">{report.fully_immunized_children}</td>
                                                    <td className="px-6 py-3.5 font-medium">{report.infants_exclusive_breastfed}</td>
                                                    <td className="px-6 py-3.5">
                                                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-100/70 uppercase">
                                                            {report.status}
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </div>

                </div>
            </div>
        </AppLayout>
    );
}