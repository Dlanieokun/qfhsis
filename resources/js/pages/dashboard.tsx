import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    Activity,
    Baby,
    Bug,
    ClipboardList,
    FileText,
    Home,
    Leaf,
    PlusCircle,
    Stethoscope,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

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

interface ModuleCounts {
    household_profiles?: number;
    maternal_care_records?: number;
    prenatal_8anc_records?: number;
    prenatal_immunization_records?: number;
    prenatal_supplementation_records?: number;
    prenatal_lab_screening_records?: number;
    intrapartum_records?: number;
    postpartum_records?: number;
    child_immunization_records?: number;
    child_immunization_school_records?: number;
    child_nutrition_records?: number;
    child_sick_records?: number;
    family_planning_records?: number;
    family_planning_follow_ups?: number;
    family_planning_drop_outs?: number;
    filariasis_registry_table?: number;
    schistosomiasis_registry?: number;
    sth_registry_records?: number;
    leprosy_registry?: number;
    rabies_records?: number;
    philpen_risk_assessments?: number;
    cervical_cancer_screenings?: number;
    eyes_screenings?: number;
    oral_health_care?: number;
    geriatric_screening_records?: number;
    mental_health_records?: number;
    environmental_health_records?: number;
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
    moduleCounts?: ModuleCounts;
}

type ModuleKey = keyof ModuleCounts;

interface ModuleDef {
    key: ModuleKey;
    label: string;
}

interface Category {
    id: string;
    label: string;
    description: string;
    icon: LucideIcon;
    color: string;
    modules: ModuleDef[];
}

// Updated colors to be rich and legible against a white background
const CATEGORIES: Category[] = [
    {
        id: 'household',
        label: 'Household Profile',
        description: 'The base family registry every other record links back to.',
        icon: Home,
        color: '#0F766E', // Teal 700
        modules: [{ key: 'household_profiles', label: 'Household Profiles' }],
    },
    {
        id: 'maternal_child',
        label: 'Maternal & Child',
        description: 'Pregnancy tracking through delivery, and child growth through school age.',
        icon: Baby,
        color: '#BE185D', // Pink 700
        modules: [
            { key: 'maternal_care_records', label: 'Maternal Care Records' },
            { key: 'prenatal_8anc_records', label: 'Prenatal 8-ANC Visits' },
            { key: 'prenatal_immunization_records', label: 'Prenatal Td Immunization' },
            { key: 'prenatal_supplementation_records', label: 'Prenatal Supplementation' },
            { key: 'prenatal_lab_screening_records', label: 'Prenatal Lab Screening' },
            { key: 'intrapartum_records', label: 'Intrapartum Records' },
            { key: 'postpartum_records', label: 'Postpartum Records' },
            { key: 'child_immunization_records', label: 'Child Immunization' },
            { key: 'child_immunization_school_records', label: 'School-Based Immunization' },
            { key: 'child_nutrition_records', label: 'Child Nutrition' },
            { key: 'child_sick_records', label: 'Sick Child (IMCI)' },
        ],
    },
    {
        id: 'family_planning',
        label: 'Family Planning',
        description: 'Active clients, method changes, follow-up visits, and drop-outs.',
        icon: Users,
        color: '#4338CA', // Indigo 700
        modules: [
            { key: 'family_planning_records', label: 'Family Planning Records' },
            { key: 'family_planning_follow_ups', label: 'Follow-Up Visits' },
            { key: 'family_planning_drop_outs', label: 'Drop-Outs' },
        ],
    },
    {
        id: 'communicable',
        label: 'Communicable Disease',
        description: 'Neglected tropical disease registries and animal-bite exposure.',
        icon: Bug,
        color: '#B45309', // Amber 700
        modules: [
            { key: 'filariasis_registry_table', label: 'Filariasis Registry' },
            { key: 'schistosomiasis_registry', label: 'Schistosomiasis Registry' },
            { key: 'sth_registry_records', label: 'Soil-Transmitted Helminths' },
            { key: 'leprosy_registry', label: 'Leprosy Registry' },
            { key: 'rabies_records', label: 'Rabies Exposure' },
        ],
    },
    {
        id: 'ncd_screening',
        label: 'NCD & Screening',
        description: 'Lifestyle-disease risk, cancer screening, and specialty checkups.',
        icon: Stethoscope,
        color: '#047857', // Emerald 700
        modules: [
            { key: 'philpen_risk_assessments', label: 'PhilPEN Risk Assessment' },
            { key: 'cervical_cancer_screenings', label: 'Cervical & Breast Screening' },
            { key: 'eyes_screenings', label: 'Eye Screening' },
            { key: 'oral_health_care', label: 'Oral Health Care' },
            { key: 'geriatric_screening_records', label: 'Geriatric Screening' },
            { key: 'mental_health_records', label: 'Mental Health (mhGAP)' },
        ],
    },
    {
        id: 'environmental',
        label: 'Environmental',
        description: 'Water source, sanitation facilities, and safe-disposal status.',
        icon: Leaf,
        color: '#4D7C0F', // Lime 700
        modules: [{ key: 'environmental_health_records', label: 'Environmental Health' }],
    },
];

const TOTAL_MODULES = CATEGORIES.reduce((sum, c) => sum + c.modules.length, 0);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'FHSIS Dashboard',
        href: '/qfhsis/public/fhsis/dashboard',
    },
];

/** Modern Light UI Card */
function DashboardCard({ children, className = '' }: { children: React.ReactNode; className?: string }) {
    return (
        <div className={`bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden ${className}`}>
            <div className="p-6">
                {children}
            </div>
        </div>
    );
}

export default function Dashboard({ auth, reports = [], moduleCounts = {} }: Props) {
    const [activeCategory, setActiveCategory] = useState('maternal_child');

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
        post('/qfhsis/public/fhsis/reports', {
            onSuccess: () => reset(),
        });
    };

    const current = CATEGORIES.find((c) => c.id === activeCategory) ?? CATEGORIES[0];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="FHSIS Core Health Indicators">
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
                <link
                    href="https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&family=Public+Sans:wght@400;500;600;700&family=Zilla+Slab:wght@600;700&display=swap"
                    rel="stylesheet"
                />
            </Head>

            {/* Light background replacing the dark gradient */}
            <div className="min-h-screen bg-slate-50 font-['Public_Sans'] text-slate-800 antialiased">
                <div className="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
                    
                    {/* Header Panel - Light Mode */}
                    <div className="relative bg-white border border-slate-200 rounded-xl px-6 py-8 overflow-hidden mb-8 shadow-sm">
                        <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-[#1B3B66] to-[#126A59]" />
                        <p className="text-[11px] font-semibold tracking-[0.25em] uppercase text-slate-500 font-['Courier_Prime']">
                            Barangay Health Information System
                        </p>
                        <h1 className="text-[28px] leading-tight font-bold tracking-tight font-['Zilla_Slab'] mt-2 pr-32 text-[#1B3B66]">
                            {auth.user.assigned_facility ? `${auth.user.assigned_facility} — System Overview` : 'Rural Health Unit — System Overview'}
                        </h1>
                        <p className="text-slate-600 text-sm mt-2">
                            Session active for <span className="font-semibold text-slate-800">{auth.user.name}</span> · {auth.user.role}
                        </p>

                        {/* System Status Badge */}
                        <div className="hidden sm:flex absolute top-6 right-6 items-center gap-2 px-4 py-2 rounded-full bg-teal-50 border border-teal-100">
                            <span className="w-2 h-2 rounded-full bg-[#126A59] animate-pulse" />
                            <span className="text-[#126A59] text-[10px] font-bold uppercase tracking-widest font-['Courier_Prime']">
                                Online
                            </span>
                        </div>
                    </div>

                    {/* Summary row */}
                    <div className="bg-white border border-slate-200 rounded-xl shadow-sm grid grid-cols-2 sm:grid-cols-4 divide-x divide-slate-100 mb-10 overflow-hidden">
                        <LedgerStat icon={ClipboardList} label="Submissions" value={totalSubmittedReports} unit="logs" color="#0F766E" />
                        <LedgerStat icon={Baby} label="Pregnancies Tracked" value={totalMaternalCases} unit="cases" color="#BE185D" />
                        <LedgerStat icon={Activity} label="Fully Immunized" value={totalImmunized} unit="children" color="#047857" />
                        <LedgerStat icon={FileText} label="Program Modules" value={TOTAL_MODULES} unit={`across ${CATEGORIES.length}`} color="#4338CA" />
                    </div>

                    {/* Program directory */}
                    <div className="mb-10">
                        <h2 className="text-xl font-bold text-[#1B3B66] font-['Zilla_Slab'] mb-1">Program Records</h2>
                        <p className="text-sm text-slate-500 mb-6">Choose a program to see every register kept for it.</p>

                        <div className="flex flex-col lg:flex-row gap-6">
                            {/* Sidebar directory */}
                            <div className="flex lg:flex-col gap-3 overflow-x-auto lg:w-72 shrink-0 pb-2">
                                {CATEGORIES.map((cat) => {
                                    const isActive = cat.id === activeCategory;
                                    const Icon = cat.icon;
                                    return (
                                        <button
                                            key={cat.id}
                                            type="button"
                                            onClick={() => setActiveCategory(cat.id)}
                                            className={`flex items-center gap-4 shrink-0 text-left px-4 py-3 rounded-lg border transition-all whitespace-nowrap lg:whitespace-normal ${
                                                isActive 
                                                    ? 'bg-white border-[#126A59] shadow-md ring-1 ring-[#126A59]/10' 
                                                    : 'bg-transparent border-transparent hover:bg-slate-100'
                                            }`}
                                        >
                                            <span
                                                className="flex items-center justify-center w-10 h-10 rounded-lg shrink-0 transition-all shadow-sm"
                                                style={{
                                                    backgroundColor: isActive ? cat.color : `${cat.color}15`,
                                                    color: isActive ? '#FFFFFF' : cat.color,
                                                }}
                                            >
                                                <Icon className="w-5 h-5" />
                                            </span>
                                            <span className="min-w-0">
                                                <span className={`block text-sm font-semibold transition-colors ${isActive ? 'text-slate-900' : 'text-slate-600'}`}>{cat.label}</span>
                                                <span className="block text-[11px] font-['Courier_Prime'] text-slate-500 uppercase tracking-wide mt-0.5">
                                                    {cat.modules.length} register{cat.modules.length > 1 ? 's' : ''}
                                                </span>
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>

                            {/* Details Card */}
                            <DashboardCard className="flex-1 border-t-4" style={{ borderTopColor: current.color }}>
                                <div className="flex items-center gap-3 mb-2">
                                    <div 
                                        className="w-10 h-10 rounded-lg flex items-center justify-center shadow-sm" 
                                        style={{ backgroundColor: `${current.color}15`, color: current.color }}
                                    >
                                        <current.icon className="w-5 h-5" />
                                    </div>
                                    <h3 className="font-bold text-slate-900 font-['Zilla_Slab'] text-2xl">{current.label}</h3>
                                </div>
                                <p className="text-sm text-slate-600 mb-6 pb-4 border-b border-slate-100">{current.description}</p>

                                <div className="space-y-1">
                                    {current.modules.map((mod) => {
                                        const count = moduleCounts[mod.key] ?? 0;
                                        return (
                                            <div key={mod.key} className="flex items-center justify-between p-3 rounded-lg hover:bg-slate-50 transition-colors group">
                                                <span className="text-sm font-medium text-slate-700 group-hover:text-slate-900 transition-colors">{mod.label}</span>
                                                <div className="flex items-center gap-3">
                                                    <span className="h-px w-12 bg-slate-200 hidden sm:block"></span>
                                                    <span
                                                        className="font-['Courier_Prime'] font-bold text-lg tabular-nums min-w-[2.5rem] text-right"
                                                        style={{ color: current.color }}
                                                    >
                                                        {String(count).padStart(2, '0')}
                                                    </span>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </DashboardCard>
                        </div>
                    </div>

                    {/* Forms and History Grid */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                        <DashboardCard className="lg:col-span-1">
                            <div className="flex items-center gap-2 font-bold text-[#1B3B66] font-['Zilla_Slab'] text-lg mb-6">
                                <PlusCircle className="w-5 h-5 text-[#126A59]" />
                                <h2>New Quarterly Entry</h2>
                            </div>

                            <form onSubmit={handleSubmit} className="space-y-5">
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-semibold text-slate-600 uppercase tracking-wider">Year</label>
                                        <input
                                            type="text"
                                            maxLength={4}
                                            value={data.reporting_year}
                                            onChange={(e) => setData('reporting_year', e.target.value)}
                                            className="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#126A59] focus:ring-1 focus:ring-[#126A59] transition font-['Courier_Prime']"
                                        />
                                        {errors.reporting_year && <p className="text-pink-600 text-xs">{errors.reporting_year}</p>}
                                    </div>
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-semibold text-slate-600 uppercase tracking-wider">Quarter</label>
                                        <select
                                            value={data.reporting_quarter}
                                            onChange={(e) => setData('reporting_quarter', e.target.value)}
                                            className="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#126A59] focus:ring-1 focus:ring-[#126A59] transition"
                                        >
                                            <option value="Q1">1st Quarter (Q1)</option>
                                            <option value="Q2">2nd Quarter (Q2)</option>
                                            <option value="Q3">3rd Quarter (Q3)</option>
                                            <option value="Q4">4th Quarter (Q4)</option>
                                        </select>
                                    </div>
                                </div>

                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold text-slate-600 uppercase tracking-wider">Total Pregnant Tracked</label>
                                    <input
                                        type="number"
                                        min={0}
                                        value={data.total_pregnant_tracked}
                                        onChange={(e) => setData('total_pregnant_tracked', parseInt(e.target.value) || 0)}
                                        className="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#126A59] focus:ring-1 focus:ring-[#126A59] transition font-['Courier_Prime']"
                                    />
                                    {errors.total_pregnant_tracked && <p className="text-pink-600 text-xs">{errors.total_pregnant_tracked}</p>}
                                </div>

                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold text-slate-600 uppercase tracking-wider">Completed 4 ANC Visits</label>
                                    <input
                                        type="number"
                                        min={0}
                                        value={data.completed_4_anc_visits}
                                        onChange={(e) => setData('completed_4_anc_visits', parseInt(e.target.value) || 0)}
                                        className="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#126A59] focus:ring-1 focus:ring-[#126A59] transition font-['Courier_Prime']"
                                    />
                                    {errors.completed_4_anc_visits && <p className="text-pink-600 text-xs">{errors.completed_4_anc_visits}</p>}
                                </div>

                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold text-slate-600 uppercase tracking-wider">Fully Immunized Children (FIC)</label>
                                    <input
                                        type="number"
                                        min={0}
                                        value={data.fully_immunized_children}
                                        onChange={(e) => setData('fully_immunized_children', parseInt(e.target.value) || 0)}
                                        className="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#126A59] focus:ring-1 focus:ring-[#126A59] transition font-['Courier_Prime']"
                                    />
                                    {errors.fully_immunized_children && <p className="text-pink-600 text-xs">{errors.fully_immunized_children}</p>}
                                </div>

                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold text-slate-600 uppercase tracking-wider">Exclusive Breastfed Infants</label>
                                    <input
                                        type="number"
                                        min={0}
                                        value={data.infants_exclusive_breastfed}
                                        onChange={(e) => setData('infants_exclusive_breastfed', parseInt(e.target.value) || 0)}
                                        className="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#126A59] focus:ring-1 focus:ring-[#126A59] transition font-['Courier_Prime']"
                                    />
                                    {errors.infants_exclusive_breastfed && (
                                        <p className="text-pink-600 text-xs">{errors.infants_exclusive_breastfed}</p>
                                    )}
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full inline-flex items-center justify-center px-4 py-3 text-sm font-semibold text-white bg-[#126A59] rounded-lg hover:bg-[#0E5446] disabled:bg-slate-300 disabled:text-slate-500 transition-colors mt-4 shadow-sm"
                                >
                                    {processing ? 'Filing Record…' : 'Submit Indicators'}
                                </button>
                            </form>
                        </DashboardCard>

                        <DashboardCard className="lg:col-span-2">
                            <div className="flex items-center gap-2 font-bold text-[#1B3B66] font-['Zilla_Slab'] text-lg mb-6">
                                <Activity className="w-5 h-5 text-[#126A59]" />
                                <h2>Submitted Indicators History</h2>
                            </div>

                            {reports.length === 0 ? (
                                <div className="py-12 text-center text-slate-500 text-sm space-y-2 bg-slate-50 rounded-lg border border-slate-200 border-dashed">
                                    <FileText className="w-10 h-10 mx-auto text-slate-400 mb-3" />
                                    <p className="font-medium text-slate-700 text-base">No logs on record</p>
                                    <p className="text-xs">Submit your facility's quarterly matrix via the panel on the left.</p>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left border-collapse text-sm text-slate-700">
                                        <thead>
                                            <tr className="text-slate-500 font-bold uppercase border-b border-slate-200 font-['Courier_Prime'] text-xs">
                                                <th className="px-4 py-3 bg-slate-50 rounded-tl-lg">Period</th>
                                                <th className="px-4 py-3 bg-slate-50">Maternal Tracker</th>
                                                <th className="px-4 py-3 bg-slate-50">Immunized Base</th>
                                                <th className="px-4 py-3 bg-slate-50">Excl. Breastfed</th>
                                                <th className="px-4 py-3 bg-slate-50 rounded-tr-lg">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {reports.map((report) => (
                                                <tr key={report.id} className="hover:bg-slate-50 transition-colors">
                                                    <td className="px-4 py-4 font-semibold text-slate-900 font-['Courier_Prime']">
                                                        FY {report.reporting_year} — {report.reporting_quarter}
                                                    </td>
                                                    <td className="px-4 py-4 font-['Courier_Prime']">
                                                        {report.total_pregnant_tracked}{' '}
                                                        <span className="text-slate-500 text-[11px] ml-1">({report.completed_4_anc_visits} ANC)</span>
                                                    </td>
                                                    <td className="px-4 py-4 font-medium font-['Courier_Prime']">{report.fully_immunized_children}</td>
                                                    <td className="px-4 py-4 font-medium font-['Courier_Prime']">{report.infants_exclusive_breastfed}</td>
                                                    <td className="px-4 py-4">
                                                        <span className="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-teal-50 text-[#0F766E] border border-teal-200 uppercase tracking-wide">
                                                            {report.status}
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </DashboardCard>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function LedgerStat({
    icon: Icon,
    label,
    value,
    unit,
    color,
}: {
    icon: LucideIcon;
    label: string;
    value: number;
    unit: string;
    color: string;
}) {
    return (
        <div className="p-6 flex flex-col items-center text-center gap-2 hover:bg-slate-50 transition-colors">
            <div className="p-2 rounded-lg bg-slate-100 mb-1">
                <Icon className="w-5 h-5" style={{ color }} />
            </div>
            <p className="text-3xl font-bold font-['Courier_Prime'] tabular-nums" style={{ color }}>
                {value}
            </p>
            <p className="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                {label} <span className="block sm:inline text-slate-400">({unit})</span>
            </p>
        </div>
    );
}