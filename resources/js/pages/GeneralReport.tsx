import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import {
    Activity,
    Baby,
    Brain,
    Download,
    Droplets,
    Eye,
    FileText,
    Heart,
    HeartPulse,
    Leaf,
    Microscope,
    ShieldPlus,
    Smile,
    Stethoscope,
    Syringe,
    TreePine,
    Users,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';

// ── Types ─────────────────────────────────────────────────────────────────────

interface ReportFilters {
    year: string;
    barangay: string;
    barangays: string[];
}

interface ReportProps {
    filters: ReportFilters;
    demographics: { total_profiles: number; male_count: number; female_count: number };
    maternal_stats: { total_tracked: number; adolescent_pregnancies: number; normal_bmi: number };
    child_stats?: { total_infants: number; fully_immunized: number; exclusive_breastfeeding: number };
    fp_stats: { total_clients: number; new_acceptors: number };
    child_immunization: { total_records: number; fic_count: number; cic_count: number };
    child_immunization_school: { total_records: number; hpv_completed: number };
    child_nutrition: { total_records: number; mam_identified: number; sam_identified: number };
    child_sick: { total_records: number; diagnosed_measles: number; treated_pneumonia: number };
    oral_health: { total_records: number; complete_rpoc0: number };
    philpen: { total_records: number; hypertension_positive: number; current_smokers: number };
    eyes_screening: { total_screened: number; with_eye_disease: number };
    cervical_cancer: { total_records: number; cervical_done: number; breast_risk_assessed: number };
    geriatric: { total_records: number; ppv_received: number };
    filariasis: { total_records: number; with_lymphedema: number; with_elephantiasis: number };
    leprosy: { total_records: number; paucibacillary: number; multibacillary: number };
    rabies: { total_records: number; completed_pvrv: number };
    schistosomiasis: { total_records: number; confirmed_positive: number; mda_given: number };
    sth: { total_records: number; positive_result: number; mda_jan: number; mda_jul: number };
    mental_health: { total_records: number; screened_mhgap: number };
    environmental_health: { total_records: number; safely_managed_water: number; safely_managed_sanitation: number };
}

// ── Constants ─────────────────────────────────────────────────────────────────

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'FHSIS Dashboard', href: '/fhsis/dashboard' },
    { title: 'General Report', href: '/fhsis/reports' },
];

// ── Sub-components ────────────────────────────────────────────────────────────

function StatRow({ label, value, accent }: { label: string; value: number | string; accent?: string }) {
    return (
        <div className="flex items-center justify-between py-2.5 border-b border-slate-100 last:border-0">
            <span className="text-sm text-slate-500">{label}</span>
            <span className={`text-sm font-semibold tabular-nums ${accent ?? 'text-slate-800'}`}>
                {value}
            </span>
        </div>
    );
}

function SectionHeading({ label }: { label: string }) {
    return (
        <div className="col-span-full flex items-center gap-3 pt-2 pb-1">
            <span className="text-xs font-semibold uppercase tracking-widest text-slate-400">{label}</span>
            <div className="flex-1 border-t border-slate-200" />
        </div>
    );
}

function ReportCard({
    icon, iconBg, iconColor, title, subtitle, children, footerNote, onExport,
}: {
    icon: ReactNode;
    iconBg: string;
    iconColor: string;
    title: string;
    subtitle: string;
    children: ReactNode;
    footerNote: string;
    onExport: () => void;
}) {
    return (
        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col print:border print:shadow-none">
            <div className="flex items-start gap-3 p-5 pb-4">
                <div className={`mt-0.5 p-2 rounded-xl ${iconBg} ${iconColor} shrink-0`}>
                    {icon}
                </div>
                <div className="min-w-0">
                    <h3 className="text-sm font-semibold text-slate-900 leading-snug">{title}</h3>
                    <p className="text-xs text-slate-400 mt-0.5 leading-snug">{subtitle}</p>
                </div>
            </div>
            <div className="mx-5 border-t border-slate-100" />
            <div className="px-5 py-3 flex-1">{children}</div>
            <div className="mx-5 border-t border-slate-100" />
            <div className="flex items-center justify-between px-5 py-3">
                <span className="text-xs text-slate-400">{footerNote}</span>
                <button
                    onClick={onExport}
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors print:hidden"
                >
                    <Download className="w-3 h-3" />
                    Export CSV
                </button>
            </div>
        </div>
    );
}

// ── Main component ────────────────────────────────────────────────────────────

export default function GeneralReport({
    filters,
    demographics,
    maternal_stats,
    child_stats,
    fp_stats,
    child_immunization,
    child_immunization_school,
    child_nutrition,
    child_sick,
    oral_health,
    philpen,
    eyes_screening,
    cervical_cancer,
    geriatric,
    filariasis,
    leprosy,
    rabies,
    schistosomiasis,
    sth,
    mental_health,
    environmental_health,
}: ReportProps) {
    const [selectedYear, setSelectedYear] = useState(filters.year || new Date().getFullYear().toString());
    const [selectedBarangay, setSelectedBarangay] = useState(filters.barangay || 'All');
    const barangays = filters.barangays ?? [];

    const applyFilters = (year: string, barangay: string) =>
        router.get('/fhsis/reports', { year, barangay }, { preserveState: true });

    const exportURL = (path: string, section: string) =>
        `${path}?year=${selectedYear}&barangay=${selectedBarangay}&section=${section}`;

    const go = (section: string, path = '/fhsis/reports/export') =>
        () => { window.location.href = exportURL(path, section); };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="General Public Health Report" />

            <div className="min-h-screen bg-slate-50 text-slate-800 antialiased print:bg-white">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 print:px-0 print:py-0">

                    {/* ── Page header ──────────────────────────────────────── */}
                    <div className="mb-7 print:mb-6">
                        <div className="flex items-center gap-1.5 text-blue-600 text-xs font-semibold uppercase tracking-widest mb-1.5 print:hidden">
                            <FileText className="w-3.5 h-3.5" />
                            FHSIS Registry Summary
                        </div>
                        <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                            <div>
                                <h1 className="text-2xl font-bold text-slate-900 tracking-tight">
                                    General Public Health Report
                                </h1>
                                <p className="text-sm text-slate-500 mt-1">
                                    Consolidated Field Health Services Information System (FHSIS) indicators
                                </p>
                            </div>

                            {/* ── Filters ─────────────────────────────────── */}
                            <div className="flex items-center gap-2 print:hidden shrink-0">
                                <select
                                    value={selectedYear}
                                    onChange={(e) => { setSelectedYear(e.target.value); applyFilters(e.target.value, selectedBarangay); }}
                                    className="text-sm bg-white border border-slate-200 text-slate-700 rounded-lg px-3 py-2 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition w-24"
                                >
                                    <option value="2026">2026</option>
                                    <option value="2025">2025</option>
                                    <option value="2024">2024</option>
                                </select>
                                <select
                                    value={selectedBarangay}
                                    onChange={(e) => { setSelectedBarangay(e.target.value); applyFilters(selectedYear, e.target.value); }}
                                    className="text-sm bg-white border border-slate-200 text-slate-700 rounded-lg px-3 py-2 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition w-44"
                                >
                                    <option value="All">All Barangays</option>
                                    {barangays.map((b) => (
                                        <option key={b} value={b}>{b}</option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* ── Active filter badge (print-only) ─────────────────── */}
                    <div className="hidden print:flex items-center gap-4 mb-4 text-xs text-slate-500">
                        <span>Year: <strong className="text-slate-800">{selectedYear}</strong></span>
                        <span>Barangay: <strong className="text-slate-800">{selectedBarangay}</strong></span>
                    </div>

                    {/* ── Cards grid ────────────────────────────────────────── */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

                        {/* ─── POPULATION & FAMILY ──────────────────────────── */}
                        <SectionHeading label="Population & Family" />

                        <ReportCard
                            icon={<Users className="w-4 h-4" />}
                            iconBg="bg-blue-50" iconColor="text-blue-600"
                            title="Demographics"
                            subtitle="Population profiles registered in the system"
                            footerNote="Overall geographic coverage"
                            onExport={go('demographics')}
                        >
                            <StatRow label="Total profiles" value={demographics.total_profiles} />
                            <StatRow label="Male" value={demographics.male_count} />
                            <StatRow label="Female" value={demographics.female_count} />
                        </ReportCard>

                        <ReportCard
                            icon={<UsersRound className="w-4 h-4" />}
                            iconBg="bg-amber-50" iconColor="text-amber-600"
                            title="Family Planning"
                            subtitle="Responsible parenthood methods and program reach"
                            footerNote="Community resource utilization"
                            onExport={go('fp', '/fhsis/reports/export-fp')}
                        >
                            <StatRow label="Active clients" value={fp_stats.total_clients} />
                            <StatRow label="New acceptors this period" value={fp_stats.new_acceptors} accent="text-amber-600" />
                        </ReportCard>

                        <ReportCard
                            icon={<HeartPulse className="w-4 h-4" />}
                            iconBg="bg-rose-50" iconColor="text-rose-500"
                            title="Maternal Health"
                            subtitle="Pregnancy tracking and prenatal monitoring"
                            footerNote="Antenatal clinical data"
                            onExport={go('maternal', '/fhsis/reports/export-mc')}
                        >
                            <StatRow label="Tracked pregnancies" value={maternal_stats.total_tracked} />
                            <StatRow label="Adolescent pregnancies (≤19 yrs)" value={maternal_stats.adolescent_pregnancies} accent="text-rose-500" />
                            <StatRow label="Normal pre-pregnancy BMI" value={maternal_stats.normal_bmi} />
                        </ReportCard>

                        {/* ─── CHILD HEALTH ─────────────────────────────────── */}
                        <SectionHeading label="Child Health" />

                        <ReportCard
                            icon={<Syringe className="w-4 h-4" />}
                            iconBg="bg-emerald-50" iconColor="text-emerald-600"
                            title="Child Immunization"
                            subtitle="Infant vaccination records and FIC/CIC coverage"
                            footerNote="0–11 month immunization tracking"
                            onExport={go('child', '/fhsis/reports/export-ci')}
                        >
                            <StatRow label="Total records" value={child_immunization.total_records} />
                            <StatRow label="Fully immunized children (FIC)" value={child_immunization.fic_count} accent="text-emerald-600" />
                            <StatRow label="Completely immunized (CIC)" value={child_immunization.cic_count} accent="text-emerald-600" />
                        </ReportCard>

                        <ReportCard
                            icon={<ShieldPlus className="w-4 h-4" />}
                            iconBg="bg-teal-50" iconColor="text-teal-600"
                            title="Child Immunization — School"
                            subtitle="School-based and community HPV vaccination records"
                            footerNote="SBI/CBI school-age coverage"
                            onExport={go('child_immunization_school', '/fhsis/reports/export-cis')}
                        >
                            <StatRow label="Total records" value={child_immunization_school.total_records} />
                            <StatRow label="HPV fully immunized females" value={child_immunization_school.hpv_completed} accent="text-teal-600" />
                        </ReportCard>

                        <ReportCard
                            icon={<Baby className="w-4 h-4" />}
                            iconBg="bg-sky-50" iconColor="text-sky-600"
                            title="Child Nutrition"
                            subtitle="Micronutrient supplementation and malnutrition management"
                            footerNote="MAM / SAM therapeutic outcomes"
                            onExport={go('child_nutrition', '/fhsis/reports/export-cn')}
                        >
                            <StatRow label="Total records" value={child_nutrition.total_records} />
                            <StatRow label="MAM identified" value={child_nutrition.mam_identified} accent="text-amber-500" />
                            <StatRow label="SAM identified" value={child_nutrition.sam_identified} accent="text-rose-500" />
                        </ReportCard>

                        <ReportCard
                            icon={<Stethoscope className="w-4 h-4" />}
                            iconBg="bg-orange-50" iconColor="text-orange-500"
                            title="Child Sick"
                            subtitle="IMCI illness diagnosis and case management records"
                            footerNote="Diarrhea, pneumonia & measles tracking"
                            onExport={go('child_sick', '/fhsis/reports/export-cms')}
                        >
                            <StatRow label="Total records" value={child_sick.total_records} />
                            <StatRow label="Diagnosed measles" value={child_sick.diagnosed_measles} accent="text-rose-500" />
                            <StatRow label="Treated for pneumonia" value={child_sick.treated_pneumonia} accent="text-orange-500" />
                        </ReportCard>

                        {/* ─── ADULT & PREVENTIVE HEALTH ────────────────────── */}
                        <SectionHeading label="Adult & Preventive Health" />

                        <ReportCard
                            icon={<Smile className="w-4 h-4" />}
                            iconBg="bg-cyan-50" iconColor="text-cyan-600"
                            title="Oral Health Care"
                            subtitle="Dental screening, prophylaxis, and fluoride varnish records"
                            footerNote="RPOC completeness tracking"
                            onExport={go('oral_health', '/fhsis/reports/export-oral')}
                        >
                            <StatRow label="Total records" value={oral_health.total_records} />
                            <StatRow label="Complete RPOC0 (0–71 mos)" value={oral_health.complete_rpoc0} accent="text-cyan-600" />
                        </ReportCard>

                        <ReportCard
                            icon={<Activity className="w-4 h-4" />}
                            iconBg="bg-violet-50" iconColor="text-violet-600"
                            title="PhilPEN Risk Assessment"
                            subtitle="NCD risk factor screening and hypertension monitoring"
                            footerNote="BTI and lifestyle risk indicators"
                            onExport={go('philpen', '/fhsis/reports/export-philpen')}
                        >
                            <StatRow label="Total assessed" value={philpen.total_records} />
                            <StatRow label="Hypertension detected" value={philpen.hypertension_positive} accent="text-rose-500" />
                            <StatRow label="Current smokers" value={philpen.current_smokers} accent="text-amber-500" />
                        </ReportCard>

                        <ReportCard
                            icon={<Eye className="w-4 h-4" />}
                            iconBg="bg-indigo-50" iconColor="text-indigo-600"
                            title="Eyes Screening"
                            subtitle="Vision and eye disease detection records"
                            footerNote="Referral and disease code tracking"
                            onExport={go('eyes_screening', '/fhsis/reports/export-eyes')}
                        >
                            <StatRow label="Total screened" value={eyes_screening.total_screened} />
                            <StatRow label="With eye disease detected" value={eyes_screening.with_eye_disease} accent="text-indigo-600" />
                        </ReportCard>

                        <ReportCard
                            icon={<Heart className="w-4 h-4" />}
                            iconBg="bg-pink-50" iconColor="text-pink-600"
                            title="Cervical Cancer Screening"
                            subtitle="Cervical and breast cancer risk assessment records"
                            footerNote="Linked-to-care outcomes"
                            onExport={go('cervical_cancer', '/fhsis/reports/export-cervical')}
                        >
                            <StatRow label="Total records" value={cervical_cancer.total_records} />
                            <StatRow label="Cervical screening done" value={cervical_cancer.cervical_done} accent="text-pink-600" />
                            <StatRow label="Breast risk assessed" value={cervical_cancer.breast_risk_assessed} accent="text-pink-500" />
                        </ReportCard>

                        <ReportCard
                            icon={<Users className="w-4 h-4" />}
                            iconBg="bg-slate-100" iconColor="text-slate-600"
                            title="Geriatric Screening"
                            subtitle="Senior citizen health screening and immunization records"
                            footerNote="60+ PPV and influenza coverage"
                            onExport={go('geriatric', '/fhsis/reports/export-geriatric')}
                        >
                            <StatRow label="Total records" value={geriatric.total_records} />
                            <StatRow label="PPV received (≥60 yrs)" value={geriatric.ppv_received} accent="text-slate-700" />
                        </ReportCard>

                        {/* ─── COMMUNICABLE DISEASE ─────────────────────────── */}
                        <SectionHeading label="Communicable Disease" />

                        <ReportCard
                            icon={<Microscope className="w-4 h-4" />}
                            iconBg="bg-lime-50" iconColor="text-lime-700"
                            title="Filariasis Registry"
                            subtitle="Lymphatic filariasis NBE/RDT screening and MDA records"
                            footerNote="Lymphedema and elephantiasis morbidity"
                            onExport={go('filariasis', '/fhsis/reports/export-filariasis')}
                        >
                            <StatRow label="Total registered" value={filariasis.total_records} />
                            <StatRow label="With lymphedema" value={filariasis.with_lymphedema} accent="text-lime-700" />
                            <StatRow label="With elephantiasis" value={filariasis.with_elephantiasis} accent="text-amber-600" />
                        </ReportCard>

                        <ReportCard
                            icon={<Leaf className="w-4 h-4" />}
                            iconBg="bg-green-50" iconColor="text-green-700"
                            title="Leprosy Registry"
                            subtitle="Leprosy case confirmation, MDT, and treatment outcomes"
                            footerNote="Fixed MDT completion tracking"
                            onExport={go('leprosy', '/fhsis/reports/export-leprosy')}
                        >
                            <StatRow label="Total registered" value={leprosy.total_records} />
                            <StatRow label="Paucibacillary cases" value={leprosy.paucibacillary} />
                            <StatRow label="Multibacillary cases" value={leprosy.multibacillary} accent="text-amber-600" />
                        </ReportCard>

                        <ReportCard
                            icon={<ShieldPlus className="w-4 h-4" />}
                            iconBg="bg-red-50" iconColor="text-red-600"
                            title="Rabies Records"
                            subtitle="Animal bite exposures and post-exposure prophylaxis"
                            footerNote="PVRV / PCEV outcome monitoring"
                            onExport={go('rabies')}
                        >
                            <StatRow label="Total records" value={rabies.total_records} />
                            <StatRow label="Completed PVRV series" value={rabies.completed_pvrv} accent="text-red-600" />
                        </ReportCard>

                        <ReportCard
                            icon={<Droplets className="w-4 h-4" />}
                            iconBg="bg-blue-50" iconColor="text-blue-700"
                            title="Schistosomiasis Registry"
                            subtitle="Screening, diagnosis, treatment, and MDA records"
                            footerNote="Confirmed cases and cure rates"
                            onExport={go('schistosomiasis', '/fhsis/reports/export-schisto')}
                        >
                            <StatRow label="Total registered" value={schistosomiasis.total_records} />
                            <StatRow label="Confirmed positive" value={schistosomiasis.confirmed_positive} accent="text-blue-700" />
                            <StatRow label="MDA administered" value={schistosomiasis.mda_given} accent="text-emerald-600" />
                        </ReportCard>

                        <ReportCard
                            icon={<TreePine className="w-4 h-4" />}
                            iconBg="bg-emerald-50" iconColor="text-emerald-700"
                            title="Soil-Transmitted Helminthiasis"
                            subtitle="STH screening, treatment, and MDA round coverage"
                            footerNote="January & July MDA rounds"
                            onExport={go('sth', '/fhsis/reports/export-sth')}
                        >
                            <StatRow label="Total registered" value={sth.total_records} />
                            <StatRow label="Positive on screening" value={sth.positive_result} accent="text-amber-600" />
                            <StatRow label="January MDA" value={sth.mda_jan} accent="text-emerald-600" />
                            <StatRow label="July MDA" value={sth.mda_jul} accent="text-emerald-600" />
                        </ReportCard>

                        {/* ─── MENTAL & ENVIRONMENTAL HEALTH ────────────────── */}
                        <SectionHeading label="Mental & Environmental Health" />

                        <ReportCard
                            icon={<Brain className="w-4 h-4" />}
                            iconBg="bg-purple-50" iconColor="text-purple-600"
                            title="Mental Health"
                            subtitle="mhGAP screening and mental disorder assessment records"
                            footerNote="Community mental health coverage"
                            onExport={go('mental_health', '/fhsis/reports/export-mh')}
                        >
                            <StatRow label="Total assessed" value={mental_health.total_records} />
                            <StatRow label="Screened via mhGAP" value={mental_health.screened_mhgap} accent="text-purple-600" />
                        </ReportCard>

                        <ReportCard
                            icon={<Microscope className="w-4 h-4" />}
                            iconBg="bg-teal-50" iconColor="text-teal-700"
                            title="Environmental Health"
                            subtitle="Water source quality and sanitation facility records"
                            footerNote="WASH indicators and safety plans"
                            onExport={go('environmental_health', '/fhsis/reports/export-envi')}
                        >
                            <StatRow label="Total households" value={environmental_health.total_records} />
                            <StatRow label="Safely managed drinking water" value={environmental_health.safely_managed_water} accent="text-teal-600" />
                            <StatRow label="Safely managed sanitation" value={environmental_health.safely_managed_sanitation} accent="text-teal-600" />
                        </ReportCard>

                    </div>

                    {/* ── Print signature block ─────────────────────────────── */}
                    <div className="hidden print:flex justify-between items-end mt-16 pt-6 border-t border-slate-300 text-xs text-slate-600">
                        <div>
                            <p className="font-semibold text-slate-900 mb-6">Prepared by</p>
                            <div className="border-b border-slate-400 w-44 mb-1" />
                            <p>Public Health Nurse / Officer</p>
                        </div>
                        <div>
                            <p className="font-semibold text-slate-900 mb-6">Verified by</p>
                            <div className="border-b border-slate-400 w-44 mb-1" />
                            <p>MHO / Facility Head</p>
                        </div>
                        <div className="text-right">
                            <p>Generated: {new Date().toLocaleDateString('en-PH', { dateStyle: 'long' })}</p>
                            <p className="text-slate-400 mt-0.5">FHSIS Electronic System</p>
                        </div>
                    </div>

                </div>
            </div>
        </AppLayout>
    );
}