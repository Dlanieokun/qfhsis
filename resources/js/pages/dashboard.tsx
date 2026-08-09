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

/**
 * Record counts keyed by the database table each module reads from.
 * Pass these from the controller (e.g. Model::count()) to populate the ledger —
 * any key left out simply renders as 0.
 */
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

const CATEGORIES: Category[] = [
    {
        id: 'household',
        label: 'Household Profile',
        description: 'The base family registry every other record links back to.',
        icon: Home,
        color: '#2F5233',
        modules: [{ key: 'household_profiles', label: 'Household Profiles' }],
    },
    {
        id: 'maternal_child',
        label: 'Maternal & Child',
        description: 'Pregnancy tracking through delivery, and child growth through school age.',
        icon: Baby,
        color: '#9C3B3B',
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
        color: '#4A5D8A',
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
        color: '#B5762B',
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
        color: '#3E6E5E',
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
        color: '#6B7A3D',
        modules: [{ key: 'environmental_health_records', label: 'Environmental Health' }],
    },
];

const TOTAL_MODULES = CATEGORIES.reduce((sum, c) => sum + c.modules.length, 0);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'FHSIS Dashboard',
        href: '/fhsis-system/public/fhsis/dashboard',
    },
];

/** Perforation strip — a row of punched holes, like the tear-off edge of a clinic form. */
function Perforation({ bg = '#EEF2E6' }: { bg?: string }) {
    return (
        <div
            className="h-2 w-full"
            style={{
                backgroundImage: `radial-gradient(circle, ${bg} 2.2px, transparent 2.2px)`,
                backgroundSize: '11px 100%',
                backgroundColor: '#C7D0BC',
                backgroundRepeat: 'repeat-x',
            }}
        />
    );
}

/** Ledger card shell — cream stock, dashed rule margin, perforated top edge. */
function LedgerCard({ children, className = '' }: { children: React.ReactNode; className?: string }) {
    return (
        <div className={`bg-white border border-[#C7D0BC] rounded-b-md shadow-sm overflow-hidden ${className}`}>
            <Perforation bg="#FFFFFF" />
            <div className="relative pl-7 pr-5 py-5">
                <div className="absolute left-4 top-0 bottom-0 w-px bg-[#C9827E]" />
                <div className="absolute left-[18px] top-0 bottom-0 w-px border-l border-dotted border-[#C9827E]" />
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
        post('/fhsis-system/public/fhsis/reports', {
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

            <div className="min-h-screen bg-[#EEF2E6] font-['Public_Sans'] text-[#1F2A22] antialiased">
                <div className="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
                    {/* Ledger header band */}
                    <div className="relative bg-[#1F2A22] text-[#EEF2E6] rounded-t-md px-6 py-5 overflow-hidden">
                        <p className="text-[11px] font-semibold tracking-[0.25em] uppercase text-[#A9B6A0] font-['Courier_Prime']">
                            Barangay Health Information System
                        </p>
                        <h1 className="text-[28px] leading-tight font-bold tracking-tight font-['Zilla_Slab'] mt-1 pr-32">
                            {auth.user.assigned_facility ? `${auth.user.assigned_facility} — Records Ledger` : 'Rural Health Unit — Records Ledger'}
                        </h1>
                        <p className="text-[#C7D0BC] text-sm mt-1">
                            Kept by <span className="font-semibold text-[#EEF2E6]">{auth.user.name}</span> · {auth.user.role}
                        </p>

                        {/* Ink stamp */}
                        <div
                            className="hidden sm:flex absolute top-5 right-6 items-center justify-center w-24 h-24 rounded-full border-[3px] border-double border-[#9C3B3B] text-[#c96868] text-[10px] font-bold uppercase tracking-widest text-center leading-tight font-['Courier_Prime']"
                            style={{ transform: 'rotate(-9deg)' }}
                        >
                            System<br />Operational
                        </div>
                    </div>
                    <Perforation />

                    {/* Ledger summary row */}
                    <div className="bg-white border border-[#C7D0BC] border-t-0 rounded-b-md shadow-sm grid grid-cols-2 sm:grid-cols-4 divide-x divide-dashed divide-[#C7D0BC] mb-10">
                        <LedgerStat icon={ClipboardList} label="Submissions" value={totalSubmittedReports} unit="logs" color="#1F2A22" />
                        <LedgerStat icon={Baby} label="Pregnancies Tracked" value={totalMaternalCases} unit="cases" color="#9C3B3B" />
                        <LedgerStat icon={Activity} label="Fully Immunized" value={totalImmunized} unit="children" color="#3E6E5E" />
                        <LedgerStat icon={FileText} label="Program Modules" value={TOTAL_MODULES} unit={`across ${CATEGORIES.length}`} color="#4A5D8A" />
                    </div>

                    {/* Program directory + ledger */}
                    <div className="mb-10">
                        <h2 className="text-xl font-bold text-[#1F2A22] font-['Zilla_Slab'] mb-1">Program Records</h2>
                        <p className="text-sm text-[#5B6B5E] mb-4">Choose a program to see every register kept for it.</p>

                        <div className="flex flex-col lg:flex-row gap-5">
                            {/* Sidebar directory */}
                            <div className="flex lg:flex-col gap-2 overflow-x-auto lg:w-64 shrink-0 pb-1">
                                {CATEGORIES.map((cat) => {
                                    const isActive = cat.id === activeCategory;
                                    const Icon = cat.icon;
                                    return (
                                        <button
                                            key={cat.id}
                                            type="button"
                                            onClick={() => setActiveCategory(cat.id)}
                                            className={`flex items-center gap-3 shrink-0 text-left px-3 py-2.5 rounded-md border transition-all whitespace-nowrap lg:whitespace-normal ${
                                                isActive ? 'bg-white shadow-sm' : 'bg-white/40 border-transparent hover:bg-white/70'
                                            }`}
                                            style={{
                                                borderColor: isActive ? cat.color : 'transparent',
                                                borderLeftWidth: 4,
                                                borderLeftColor: cat.color,
                                            }}
                                        >
                                            <span
                                                className="flex items-center justify-center w-8 h-8 rounded-full shrink-0 transition-transform"
                                                style={{
                                                    backgroundColor: isActive ? cat.color : `${cat.color}1A`,
                                                    color: isActive ? '#EEF2E6' : cat.color,
                                                    transform: isActive ? 'rotate(-4deg)' : 'none',
                                                }}
                                            >
                                                <Icon className="w-4 h-4" />
                                            </span>
                                            <span className="min-w-0">
                                                <span className="block text-sm font-semibold text-[#1F2A22]">{cat.label}</span>
                                                <span className="block text-[10px] font-['Courier_Prime'] text-[#8A9583] uppercase tracking-wide">
                                                    {cat.modules.length} register{cat.modules.length > 1 ? 's' : ''}
                                                </span>
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>

                            {/* Ledger sheet */}
                            <LedgerCard className="flex-1">
                                <div className="flex items-center gap-2 mb-1">
                                    <span className="w-2 h-2 rounded-full" style={{ backgroundColor: current.color }} />
                                    <h3 className="font-bold text-[#1F2A22] font-['Zilla_Slab'] text-lg">{current.label}</h3>
                                </div>
                                <p className="text-sm text-[#5B6B5E] mb-4">{current.description}</p>

                                <div>
                                    {current.modules.map((mod) => {
                                        const count = moduleCounts[mod.key] ?? 0;
                                        return (
                                            <div key={mod.key} className="flex items-baseline gap-2 py-2">
                                                <span className="text-sm text-[#1F2A22]">{mod.label}</span>
                                                <span className="flex-1 border-b border-dotted border-[#AAB6A0] translate-y-[-3px]" />
                                                <span
                                                    className="font-['Courier_Prime'] font-bold text-lg tabular-nums"
                                                    style={{ color: current.color }}
                                                >
                                                    {String(count).padStart(2, '0')}
                                                </span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </LedgerCard>
                        </div>
                    </div>

                    {/* Quarterly indicator entry + history */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                        <LedgerCard className="lg:col-span-1">
                            <div className="flex items-center gap-2 font-bold text-[#1F2A22] font-['Zilla_Slab'] text-lg mb-4">
                                <PlusCircle className="w-5 h-5 text-[#4A5D8A]" />
                                <h2>New Quarterly Entry</h2>
                            </div>

                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-1">
                                        <label className="text-xs font-semibold text-[#5B6B5E]">Year</label>
                                        <input
                                            type="text"
                                            maxLength={4}
                                            value={data.reporting_year}
                                            onChange={(e) => setData('reporting_year', e.target.value)}
                                            className="w-full bg-[#F5F7EF] border border-[#C7D0BC] rounded-md px-3 py-2 text-sm text-[#1F2A22] outline-none focus:border-[#4A5D8A] transition font-['Courier_Prime']"
                                        />
                                        {errors.reporting_year && <p className="text-[#9C3B3B] text-xs">{errors.reporting_year}</p>}
                                    </div>
                                    <div className="space-y-1">
                                        <label className="text-xs font-semibold text-[#5B6B5E]">Quarter</label>
                                        <select
                                            value={data.reporting_quarter}
                                            onChange={(e) => setData('reporting_quarter', e.target.value)}
                                            className="w-full bg-[#F5F7EF] border border-[#C7D0BC] rounded-md px-3 py-2 text-sm text-[#1F2A22] outline-none focus:border-[#4A5D8A] transition"
                                        >
                                            <option value="Q1">1st Quarter (Q1)</option>
                                            <option value="Q2">2nd Quarter (Q2)</option>
                                            <option value="Q3">3rd Quarter (Q3)</option>
                                            <option value="Q4">4th Quarter (Q4)</option>
                                        </select>
                                    </div>
                                </div>

                                <div className="space-y-1">
                                    <label className="text-xs font-semibold text-[#5B6B5E]">Total Pregnant Tracked</label>
                                    <input
                                        type="number"
                                        min={0}
                                        value={data.total_pregnant_tracked}
                                        onChange={(e) => setData('total_pregnant_tracked', parseInt(e.target.value) || 0)}
                                        className="w-full bg-[#F5F7EF] border border-[#C7D0BC] rounded-md px-3 py-2 text-sm text-[#1F2A22] outline-none focus:border-[#4A5D8A] transition font-['Courier_Prime']"
                                    />
                                    {errors.total_pregnant_tracked && <p className="text-[#9C3B3B] text-xs">{errors.total_pregnant_tracked}</p>}
                                </div>

                                <div className="space-y-1">
                                    <label className="text-xs font-semibold text-[#5B6B5E]">Completed 4 Prenatal Checks (ANC)</label>
                                    <input
                                        type="number"
                                        min={0}
                                        value={data.completed_4_anc_visits}
                                        onChange={(e) => setData('completed_4_anc_visits', parseInt(e.target.value) || 0)}
                                        className="w-full bg-[#F5F7EF] border border-[#C7D0BC] rounded-md px-3 py-2 text-sm text-[#1F2A22] outline-none focus:border-[#4A5D8A] transition font-['Courier_Prime']"
                                    />
                                    {errors.completed_4_anc_visits && <p className="text-[#9C3B3B] text-xs">{errors.completed_4_anc_visits}</p>}
                                </div>

                                <div className="space-y-1">
                                    <label className="text-xs font-semibold text-[#5B6B5E]">Fully Immunized Children (FIC)</label>
                                    <input
                                        type="number"
                                        min={0}
                                        value={data.fully_immunized_children}
                                        onChange={(e) => setData('fully_immunized_children', parseInt(e.target.value) || 0)}
                                        className="w-full bg-[#F5F7EF] border border-[#C7D0BC] rounded-md px-3 py-2 text-sm text-[#1F2A22] outline-none focus:border-[#4A5D8A] transition font-['Courier_Prime']"
                                    />
                                    {errors.fully_immunized_children && <p className="text-[#9C3B3B] text-xs">{errors.fully_immunized_children}</p>}
                                </div>

                                <div className="space-y-1">
                                    <label className="text-xs font-semibold text-[#5B6B5E]">Exclusive Breastfed Infants</label>
                                    <input
                                        type="number"
                                        min={0}
                                        value={data.infants_exclusive_breastfed}
                                        onChange={(e) => setData('infants_exclusive_breastfed', parseInt(e.target.value) || 0)}
                                        className="w-full bg-[#F5F7EF] border border-[#C7D0BC] rounded-md px-3 py-2 text-sm text-[#1F2A22] outline-none focus:border-[#4A5D8A] transition font-['Courier_Prime']"
                                    />
                                    {errors.infants_exclusive_breastfed && (
                                        <p className="text-[#9C3B3B] text-xs">{errors.infants_exclusive_breastfed}</p>
                                    )}
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-[#EEF2E6] bg-[#1F2A22] rounded-md hover:bg-[#16201A] disabled:bg-[#AAB6A0] transition mt-2"
                                >
                                    {processing ? 'Filing Record…' : 'Submit Indicators'}
                                </button>
                            </form>
                        </LedgerCard>

                        <LedgerCard className="lg:col-span-2">
                            <div className="flex items-center gap-2 font-bold text-[#1F2A22] font-['Zilla_Slab'] text-lg mb-4">
                                <Activity className="w-5 h-5 text-[#4A5D8A]" />
                                <h2>Submitted Indicators History</h2>
                            </div>

                            {reports.length === 0 ? (
                                <div className="py-10 text-center text-[#8A9583] text-sm space-y-1">
                                    <FileText className="w-8 h-8 mx-auto text-[#C7D0BC] mb-2" />
                                    <p className="font-medium text-[#5B6B5E]">No logs on record</p>
                                    <p className="text-xs">Submit your facility's quarterly matrix via the panel on the left.</p>
                                </div>
                            ) : (
                                <div className="overflow-x-auto -mx-1">
                                    <table className="w-full text-left border-collapse text-xs text-[#5B6B5E]">
                                        <thead>
                                            <tr className="text-[#8A9583] font-bold uppercase border-b border-dashed border-[#C7D0BC] font-['Courier_Prime']">
                                                <th className="px-3 py-2">Period</th>
                                                <th className="px-3 py-2">Maternal Tracker</th>
                                                <th className="px-3 py-2">Immunized Base</th>
                                                <th className="px-3 py-2">Excl. Breastfed</th>
                                                <th className="px-3 py-2">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-dashed divide-[#E2E7D8]">
                                            {reports.map((report) => (
                                                <tr key={report.id} className="hover:bg-[#F5F7EF] transition">
                                                    <td className="px-3 py-3 font-semibold text-[#1F2A22] font-['Courier_Prime']">
                                                        FY {report.reporting_year} — {report.reporting_quarter}
                                                    </td>
                                                    <td className="px-3 py-3 font-['Courier_Prime']">
                                                        {report.total_pregnant_tracked}{' '}
                                                        <span className="text-[#8A9583] text-[10px]">({report.completed_4_anc_visits} ANC)</span>
                                                    </td>
                                                    <td className="px-3 py-3 font-medium font-['Courier_Prime']">{report.fully_immunized_children}</td>
                                                    <td className="px-3 py-3 font-medium font-['Courier_Prime']">{report.infants_exclusive_breastfed}</td>
                                                    <td className="px-3 py-3">
                                                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#4A5D8A]/10 text-[#4A5D8A] border border-[#4A5D8A]/20 uppercase">
                                                            {report.status}
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </LedgerCard>
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
        <div className="p-5 flex flex-col items-center text-center gap-1.5">
            <Icon className="w-4 h-4 mb-0.5" style={{ color }} />
            <p className="text-2xl font-bold font-['Courier_Prime'] tabular-nums" style={{ color }}>
                {value}
            </p>
            <p className="text-[10px] font-semibold text-[#8A9583] uppercase tracking-wide">
                {label} <span className="block sm:inline">({unit})</span>
            </p>
        </div>
    );
}