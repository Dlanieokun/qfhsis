import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
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
    RotateCcw,
    ShieldPlus,
    SlidersHorizontal,
    Smile,
    Stethoscope,
    Syringe,
    TreePine,
    Users,
    UsersRound,
} from 'lucide-react';
import { useState, useEffect } from 'react';

// ── Types ─────────────────────────────────────────────────────────────────────

interface LocationOption {
    code: string;   // used only to fetch the next level (e.g. provCode → municipalities)
    name: string;   // displayed in the dropdown AND sent to the backend (matches stored text)
}

interface ReportFilters {
    year: string;
    region: string;
    province: string;
    municipality: string;
    barangay: string;
    // barangays is no longer needed from the server — we load them from the API
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

// BASE_PATH is not available at module scope (window may not exist during SSR),
// so breadcrumb hrefs are built inside the component. We keep the constant for
// the static array shape and override at render time below.
const BREADCRUMB_PATHS = {
    dashboard: '/fhsis/dashboard',
    reports:   '/fhsis/reports',
};

const SELECT_CLS =
    'w-full text-sm bg-white border border-slate-200 text-slate-700 rounded-lg px-3 py-2 ' +
    'shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 transition ' +
    'disabled:opacity-40 disabled:cursor-not-allowed';

const CURRENT_YEAR = new Date().getFullYear();
const YEARS = Array.from({ length: 5 }, (_, i) => String(CURRENT_YEAR - i));

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

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Derive the app's sub-path prefix from the current URL so that API calls
 * work regardless of where the app is deployed.
 *
 * e.g. http://localhost:9090/qfhsis/public/fhsis/reports
 *   → basePath = "/qfhsis/public"
 *
 * The heuristic: walk back from the end of the pathname until we find the
 * segment that precedes the first known app route segment (/fhsis, /login,
 * /register, /settings, /api).  Anything before that is the deploy prefix.
 */
function getBasePath(): string {
    if (typeof window === 'undefined') return '';
    const parts = window.location.pathname.split('/').filter(Boolean);
    const appSegments = new Set(['fhsis', 'login', 'register', 'settings', 'api', 'logout']);
    const idx = parts.findIndex((p) => appSegments.has(p));
    const prefixParts = idx > 0 ? parts.slice(0, idx) : [];
    return prefixParts.length ? '/' + prefixParts.join('/') : '';
}

// Resolved once per page load.
const BASE_PATH = typeof window !== 'undefined' ? getBasePath() : '';

async function fetchJSON<T>(path: string): Promise<T> {
    // path should start with /api/...
    const url = BASE_PATH + path;
    const res = await fetch(url);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json() as Promise<T>;
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
    // ── Selected values (names sent to/from backend) ───────────────────────
    const [selectedYear,         setSelectedYear]         = useState(filters.year         || String(CURRENT_YEAR));
    const [selectedRegion,       setSelectedRegion]       = useState(filters.region       || 'All');
    const [selectedProvince,     setSelectedProvince]     = useState(filters.province     || 'All');
    const [selectedMunicipality, setSelectedMunicipality] = useState(filters.municipality || 'All');
    const [selectedBarangay,     setSelectedBarangay]     = useState(filters.barangay     || 'All');

    // ── Option lists ───────────────────────────────────────────────────────
    // Each list holds { code, name }.  code is only used to fetch the next level.
    // The <select> value is always the *name* so it matches the stored text in household_profiles.
    const [regions,       setRegions]       = useState<LocationOption[]>([]);
    const [provinces,     setProvinces]     = useState<LocationOption[]>([]);
    const [municipalities,setMunicipalities]= useState<LocationOption[]>([]);
    const [barangays,     setBarangays]     = useState<string[]>([]);

    // Loading flags to show a subtle "…" placeholder while fetching
    const [loadingProvinces,     setLoadingProvinces]     = useState(false);
    const [loadingMunicipalities,setLoadingMunicipalities]= useState(false);
    const [loadingBarangays,     setLoadingBarangays]     = useState(false);

    const { auth } = usePage<SharedData>().props;
    const isBHW = String(auth?.user?.role ?? '').toUpperCase() === 'BHW';

    // ── 1. Load regions on mount ───────────────────────────────────────────
    useEffect(() => {
        fetchJSON<Array<{ regCode: string; regDesc: string }>>('/api/locations/regions')
            .then((data) => setRegions(data.map((d) => ({ code: d.regCode, name: d.regDesc }))))
            .catch(() => {});
    }, []);

    // ── 2. When regions load AND a region was pre-selected (page reload),
    //       find its code and load provinces automatically ─────────────────
    useEffect(() => {
        if (regions.length === 0 || selectedRegion === 'All') return;
        const match = regions.find((r) => r.name === selectedRegion);
        if (!match) return;
        setLoadingProvinces(true);
        fetchJSON<Array<{ provCode: string; provDesc: string }>>(
            `/api/locations/provinces/${match.code}`
        )
            .then((data) => setProvinces(data.map((d) => ({ code: d.provCode, name: d.provDesc }))))
            .catch(() => {})
            .finally(() => setLoadingProvinces(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [regions]);

    // ── 3. When provinces load AND a province was pre-selected, load municipalities
    useEffect(() => {
        if (provinces.length === 0 || selectedProvince === 'All') return;
        const match = provinces.find((p) => p.name === selectedProvince);
        if (!match) return;
        setLoadingMunicipalities(true);
        fetchJSON<Array<{ citymunCode: string; citymunDesc: string }>>(
            `/api/locations/municipalities/${match.code}`
        )
            .then((data) => setMunicipalities(data.map((d) => ({ code: d.citymunCode, name: d.citymunDesc }))))
            .catch(() => {})
            .finally(() => setLoadingMunicipalities(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [provinces]);

    // ── 4. When municipalities load AND a municipality was pre-selected, load barangays
    useEffect(() => {
        if (municipalities.length === 0 || selectedMunicipality === 'All') return;
        const match = municipalities.find((m) => m.name === selectedMunicipality);
        if (!match) return;
        setLoadingBarangays(true);
        fetchJSON<Array<{ brgyDesc: string }>>(
            `/api/locations/barangays/${match.code}`
        )
            .then((data) => setBarangays(data.map((d) => d.brgyDesc)))
            .catch(() => {})
            .finally(() => setLoadingBarangays(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [municipalities]);

    // ── Filter application ─────────────────────────────────────────────────
    // All values are *names* (matching household_profiles text columns).
    const applyFilters = (
        year: string,
        region: string,
        province: string,
        municipality: string,
        barangay: string,
    ) =>
        router.get(
            `${BASE_PATH}/fhsis/reports`,
            { year, region, province, municipality, barangay },
            { preserveState: false },
        );

    const exportURL = (path: string, section: string) => {
        const p = new URLSearchParams({
            year:         selectedYear,
            region:       selectedRegion,
            province:     selectedProvince,
            municipality: selectedMunicipality,
            barangay:     selectedBarangay,
            section,
        });
        return `${BASE_PATH}${path}?${p.toString()}`;
    };

    const go = (section: string, path = '/fhsis/reports/export') =>
        () => { window.location.href = exportURL(path, section); };

    // ── Region change — cascade fetch only, no navigation ─────────────────
    const handleRegionChange = (name: string) => {
        setSelectedRegion(name);
        setSelectedProvince('All');
        setSelectedMunicipality('All');
        setSelectedBarangay('All');
        setProvinces([]);
        setMunicipalities([]);
        setBarangays([]);

        if (name !== 'All') {
            const match = regions.find((r) => r.name === name);
            if (match) {
                setLoadingProvinces(true);
                fetchJSON<Array<{ provCode: string; provDesc: string }>>(
                    `/api/locations/provinces/${match.code}`
                )
                    .then((data) => setProvinces(data.map((d) => ({ code: d.provCode, name: d.provDesc }))))
                    .catch(() => {})
                    .finally(() => setLoadingProvinces(false));
            }
        }
    };

    // ── Province change — cascade fetch only, no navigation ───────────────
    const handleProvinceChange = (name: string) => {
        setSelectedProvince(name);
        setSelectedMunicipality('All');
        setSelectedBarangay('All');
        setMunicipalities([]);
        setBarangays([]);

        if (name !== 'All') {
            const match = provinces.find((p) => p.name === name);
            if (match) {
                setLoadingMunicipalities(true);
                fetchJSON<Array<{ citymunCode: string; citymunDesc: string }>>(
                    `/api/locations/municipalities/${match.code}`
                )
                    .then((data) => setMunicipalities(data.map((d) => ({ code: d.citymunCode, name: d.citymunDesc }))))
                    .catch(() => {})
                    .finally(() => setLoadingMunicipalities(false));
            }
        }
    };

    // ── Municipality change — cascade fetch only, no navigation ───────────
    const handleMunicipalityChange = (name: string) => {
        setSelectedMunicipality(name);
        setSelectedBarangay('All');
        setBarangays([]);

        if (name !== 'All') {
            const match = municipalities.find((m) => m.name === name);
            if (match) {
                setLoadingBarangays(true);
                fetchJSON<Array<{ brgyDesc: string }>>(
                    `/api/locations/barangays/${match.code}`
                )
                    .then((data) => setBarangays(data.map((d) => d.brgyDesc)))
                    .catch(() => {})
                    .finally(() => setLoadingBarangays(false));
            }
        }
    };

    // ── Barangay / Year change — update local state only ──────────────────
    const handleBarangayChange = (name: string) => setSelectedBarangay(name);
    const handleYearChange     = (year: string) => setSelectedYear(year);

    // ── Apply button — navigate with current selections ────────────────────
    const handleApply = () =>
        applyFilters(selectedYear, selectedRegion, selectedProvince, selectedMunicipality, selectedBarangay);

    // ── Reset button — clear all filters and navigate ─────────────────────
    const handleReset = () => {
        const y = String(CURRENT_YEAR);
        setSelectedYear(y);
        setSelectedRegion('All');
        setSelectedProvince('All');
        setSelectedMunicipality('All');
        setSelectedBarangay('All');
        setProvinces([]);
        setMunicipalities([]);
        setBarangays([]);
        applyFilters(y, 'All', 'All', 'All', 'All');
    };

    // ── Dirty check — true when pending state differs from applied filters ─
    const isDirty =
        selectedYear         !== (filters.year         || String(CURRENT_YEAR)) ||
        selectedRegion       !== (filters.region       || 'All') ||
        selectedProvince     !== (filters.province     || 'All') ||
        selectedMunicipality !== (filters.municipality || 'All') ||
        selectedBarangay     !== (filters.barangay     || 'All');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'FHSIS Dashboard', href: BASE_PATH + BREADCRUMB_PATHS.dashboard },
        { title: 'General Report',  href: BASE_PATH + BREADCRUMB_PATHS.reports },
    ];

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

                        <h1 className="text-2xl font-bold text-slate-900 tracking-tight">
                            General Public Health Report
                        </h1>
                        <p className="text-sm text-slate-500 mt-1 mb-5">
                            Consolidated Field Health Services Information System (FHSIS) indicators
                        </p>

                        {/* ── Filter grid ──────────────────────────────────── */}
                        <div className="print:hidden bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
                            <p className="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-3">
                                Filter by Location &amp; Period
                            </p>
                            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">

                                {/* Year */}
                                <div className="flex flex-col gap-1">
                                    <label className="text-xs text-slate-500 font-medium">Year</label>
                                    <select
                                        value={selectedYear}
                                        onChange={(e) => handleYearChange(e.target.value)}
                                        className={SELECT_CLS}
                                    >
                                        {YEARS.map((y) => (
                                            <option key={y} value={y}>{y}</option>
                                        ))}
                                    </select>
                                </div>

                                {/* Region */}
                                <div className="flex flex-col gap-1">
                                    <label className="text-xs text-slate-500 font-medium">Region</label>
                                    <select
                                        value={selectedRegion}
                                        onChange={(e) => handleRegionChange(e.target.value)}
                                        className={SELECT_CLS}
                                    >
                                        <option value="All">All Regions</option>
                                        {regions.map((r) => (
                                            <option key={r.code} value={r.name}>{r.name}</option>
                                        ))}
                                    </select>
                                </div>

                                {/* Province */}
                                <div className="flex flex-col gap-1">
                                    <label className="text-xs text-slate-500 font-medium">Province</label>
                                    <select
                                        value={selectedProvince}
                                        disabled={selectedRegion === 'All'}
                                        onChange={(e) => handleProvinceChange(e.target.value)}
                                        className={SELECT_CLS}
                                    >
                                        <option value="All">
                                            {loadingProvinces ? 'Loading…' : 'All Provinces'}
                                        </option>
                                        {provinces.map((p) => (
                                            <option key={p.code} value={p.name}>{p.name}</option>
                                        ))}
                                    </select>
                                </div>

                                {/* Municipality */}
                                <div className="flex flex-col gap-1">
                                    <label className="text-xs text-slate-500 font-medium">Municipality / City</label>
                                    <select
                                        value={selectedMunicipality}
                                        disabled={selectedProvince === 'All'}
                                        onChange={(e) => handleMunicipalityChange(e.target.value)}
                                        className={SELECT_CLS}
                                    >
                                        <option value="All">
                                            {loadingMunicipalities ? 'Loading…' : 'All Municipalities'}
                                        </option>
                                        {municipalities.map((m) => (
                                            <option key={m.code} value={m.name}>{m.name}</option>
                                        ))}
                                    </select>
                                </div>

                                {/* Barangay */}
                                <div className="flex flex-col gap-1">
                                    <label className="text-xs text-slate-500 font-medium">Barangay</label>
                                    <select
                                        value={selectedBarangay}
                                        disabled={selectedMunicipality === 'All'}
                                        onChange={(e) => handleBarangayChange(e.target.value)}
                                        className={SELECT_CLS}
                                    >
                                        <option value="All">
                                            {loadingBarangays ? 'Loading…' : 'All Barangays'}
                                        </option>
                                        {barangays.map((b) => (
                                            <option key={b} value={b}>{b}</option>
                                        ))}
                                    </select>
                                </div>

                            </div>

                            {/* ── Action row ───────────────────────────────── */}
                            <div className="flex items-center justify-between mt-4 pt-3 border-t border-slate-100">
                                {/* Active filter chips */}
                                <div className="flex flex-wrap gap-1.5">
                                    {selectedYear !== String(CURRENT_YEAR) && (
                                        <span className="inline-flex items-center gap-1 text-xs bg-blue-50 text-blue-700 border border-blue-100 rounded-full px-2.5 py-0.5 font-medium">
                                            {selectedYear}
                                        </span>
                                    )}
                                    {selectedRegion !== 'All' && (
                                        <span className="inline-flex items-center gap-1 text-xs bg-blue-50 text-blue-700 border border-blue-100 rounded-full px-2.5 py-0.5 font-medium">
                                            {selectedRegion}
                                        </span>
                                    )}
                                    {selectedProvince !== 'All' && (
                                        <span className="inline-flex items-center gap-1 text-xs bg-blue-50 text-blue-700 border border-blue-100 rounded-full px-2.5 py-0.5 font-medium">
                                            {selectedProvince}
                                        </span>
                                    )}
                                    {selectedMunicipality !== 'All' && (
                                        <span className="inline-flex items-center gap-1 text-xs bg-blue-50 text-blue-700 border border-blue-100 rounded-full px-2.5 py-0.5 font-medium">
                                            {selectedMunicipality}
                                        </span>
                                    )}
                                    {selectedBarangay !== 'All' && (
                                        <span className="inline-flex items-center gap-1 text-xs bg-blue-50 text-blue-700 border border-blue-100 rounded-full px-2.5 py-0.5 font-medium">
                                            {selectedBarangay}
                                        </span>
                                    )}
                                </div>

                                {/* Buttons */}
                                <div className="flex items-center gap-2 shrink-0">
                                    <button
                                        type="button"
                                        onClick={handleReset}
                                        className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-500 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors"
                                    >
                                        <RotateCcw className="w-3.5 h-3.5" />
                                        Reset
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleApply}
                                        disabled={!isDirty}
                                        className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed rounded-lg transition-colors shadow-sm"
                                    >
                                        <SlidersHorizontal className="w-3.5 h-3.5" />
                                        Apply Filter
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* ── Active filter badge (print-only) ─────────────────── */}
                    <div className="hidden print:flex flex-wrap items-center gap-4 mb-4 text-xs text-slate-500">
                        <span>Year: <strong className="text-slate-800">{selectedYear}</strong></span>
                        <span>Region: <strong className="text-slate-800">{selectedRegion}</strong></span>
                        <span>Province: <strong className="text-slate-800">{selectedProvince}</strong></span>
                        <span>Municipality: <strong className="text-slate-800">{selectedMunicipality}</strong></span>
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

                        {!isBHW && (
                            <>
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
                            </>
                        )}

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