import React, { useState, useEffect, useCallback, useRef } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

// Import section components
import FamilyPlanning, { type FamilyPlanningClient } from './nurse-page/FamilyPlanning';
import MaternalCare, { type MaternalCareClient } from './nurse-page/MaternalCare';
import ChildCare, { type ChildCareClient } from './nurse-page/child/ChildCare';
import OralHealthCare, { type OralHealthClient } from './nurse-page/OralHealthCare';
import NonCommunicableDisease, { type NonCommunicableDiseaseClient } from './nurse-page/ncdpcs/NonCommunicableDisease';
import GeriatricScreening, { type GeriatricClient } from './nurse-page/GeriatricScreening';
import InfectiousDisease, { type InfectiousDiseaseClient } from './nurse-page/idpcs/InfectiousDisease';
import EnvironmentalHealth, { type EnvironmentalHealthRecord } from './nurse-page/EnvironmentalHealth';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface LocationOption {
    regCode?: string;
    regDesc?: string;
    provCode?: string;
    provDesc?: string;
    citymunCode?: string;
    citymunDesc?: string;
    brgyCode?: string;
    brgyDesc?: string;
}

interface PublicNursePageProps {
    familyPlanning?: FamilyPlanningClient[];
    maternalCare?: MaternalCareClient[];
    childCare?: ChildCareClient;
    oralHealth?: OralHealthClient[];
    nonCommunicableDisease?: NonCommunicableDiseaseClient;
    geriatricHealth?: GeriatricClient[];
    infectiousDisease?: InfectiousDiseaseClient;
    wash?: EnvironmentalHealthRecord[];
    regions?: LocationOption[];
    provinces?: LocationOption[];
    municipalities?: LocationOption[];
    barangays?: LocationOption[];
    isValidated?: boolean;
    filters?: {
        month?: string;
        year?: string;
        region?: string;
        province?: string;
        municipality?: string;
        barangay?: string;
    };
}

type TabKey =
    | 'familyPlanning'
    | 'maternalCare'
    | 'childCare'
    | 'oralHealth'
    | 'nonCommunicableDisease'
    | 'geriatricHealth'
    | 'infectiousDisease'
    | 'wash';

interface TabDefinition {
    key: TabKey;
    label: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const tabs: TabDefinition[] = [
    { key: 'familyPlanning',        label: 'Family Planning' },
    { key: 'maternalCare',          label: 'Maternal Care' },
    { key: 'childCare',             label: 'Child Care' },
    { key: 'oralHealth',            label: 'Oral Health' },
    { key: 'nonCommunicableDisease',label: 'NCD Prevention' },
    { key: 'geriatricHealth',       label: 'Geriatric Health' },
    { key: 'infectiousDisease',     label: 'Infectious Disease' },
    { key: 'wash',                  label: 'WASH' },
];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'FHSIS', href: '/qfhsis/public/fhsis/dashboard' },
    { title: 'Public Health Nurse', href: '/qfhsis/public/fhsis/public-nurse' },
];

const months = [
    { value: '01', label: 'January' },
    { value: '02', label: 'February' },
    { value: '03', label: 'March' },
    { value: '04', label: 'April' },
    { value: '05', label: 'May' },
    { value: '06', label: 'June' },
    { value: '07', label: 'July' },
    { value: '08', label: 'August' },
    { value: '09', label: 'September' },
    { value: '10', label: 'October' },
    { value: '11', label: 'November' },
    { value: '12', label: 'December' },
];

const PAGE_SIZE = 15; // rows per page (adjust to taste)

// ─────────────────────────────────────────────────────────────────────────────
// Pagination hook
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Generic client-side pagination.
 * Returns the sliced page of items plus navigation helpers.
 */
export function usePagination<T>(items: T[] | undefined, pageSize = PAGE_SIZE) {
    const [page, setPage] = useState(1);

    // Reset to page 1 whenever the source array changes (e.g. after a filter)
    useEffect(() => {
        setPage(1);
    }, [items]);

    const data = items ?? [];
    const totalPages = Math.max(1, Math.ceil(data.length / pageSize));
    const safePage = Math.min(page, totalPages);

    const pageItems = data.slice((safePage - 1) * pageSize, safePage * pageSize);

    return {
        pageItems,
        page: safePage,
        totalPages,
        totalItems: data.length,
        setPage,
        hasPrev: safePage > 1,
        hasNext: safePage < totalPages,
        prev: () => setPage((p) => Math.max(1, p - 1)),
        next: () => setPage((p) => Math.min(totalPages, p + 1)),
    };
}

// ─────────────────────────────────────────────────────────────────────────────
// PaginationBar component
// ─────────────────────────────────────────────────────────────────────────────

interface PaginationBarProps {
    page: number;
    totalPages: number;
    totalItems: number;
    pageSize: number;
    hasPrev: boolean;
    hasNext: boolean;
    onPrev: () => void;
    onNext: () => void;
    onPageSelect: (p: number) => void;
}

export function PaginationBar({
    page,
    totalPages,
    totalItems,
    pageSize,
    hasPrev,
    hasNext,
    onPrev,
    onNext,
    onPageSelect,
}: PaginationBarProps) {
    if (totalItems === 0) return null;

    const from = (page - 1) * pageSize + 1;
    const to   = Math.min(page * pageSize, totalItems);

    // Build page window: always show first, last, current ±2, with ellipsis
    const buildPages = (): (number | '...')[] => {
        if (totalPages <= 7) return Array.from({ length: totalPages }, (_, i) => i + 1);
        const pages: (number | '...')[] = [];
        const addPage = (n: number) => { if (!pages.includes(n)) pages.push(n); };
        addPage(1);
        if (page > 3) pages.push('...');
        for (let i = Math.max(2, page - 2); i <= Math.min(totalPages - 1, page + 2); i++) addPage(i);
        if (page < totalPages - 2) pages.push('...');
        addPage(totalPages);
        return pages;
    };

    return (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-3 mt-4 select-none">
            <span className="text-xs text-gray-500">
                Showing <span className="font-medium text-gray-700">{from}–{to}</span> of{' '}
                <span className="font-medium text-gray-700">{totalItems}</span> records
            </span>

            <div className="flex items-center gap-1">
                {/* Prev */}
                <button
                    onClick={onPrev}
                    disabled={!hasPrev}
                    className="inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-300 text-sm text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                    aria-label="Previous page"
                >
                    ‹
                </button>

                {/* Page numbers */}
                {buildPages().map((p, idx) =>
                    p === '...' ? (
                        <span key={`ellipsis-${idx}`} className="w-8 text-center text-gray-400 text-sm">…</span>
                    ) : (
                        <button
                            key={p}
                            onClick={() => onPageSelect(p as number)}
                            className={`inline-flex items-center justify-center w-8 h-8 rounded-md border text-sm font-medium transition-colors ${
                                p === page
                                    ? 'bg-blue-600 border-blue-600 text-white'
                                    : 'border-gray-300 text-gray-600 hover:bg-gray-100'
                            }`}
                        >
                            {p}
                        </button>
                    )
                )}

                {/* Next */}
                <button
                    onClick={onNext}
                    disabled={!hasNext}
                    className="inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-300 text-sm text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                    aria-label="Next page"
                >
                    ›
                </button>
            </div>
        </div>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// Loading overlay
// ─────────────────────────────────────────────────────────────────────────────

function LoadingOverlay() {
    return (
        <div className="absolute inset-0 bg-white/60 backdrop-blur-[1px] flex items-center justify-center z-20 rounded-md">
            <div className="flex items-center gap-2 text-sm text-blue-600 font-medium">
                <svg className="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                </svg>
                Loading…
            </div>
        </div>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main page component
// ─────────────────────────────────────────────────────────────────────────────

export default function PublicNursePage({
    familyPlanning:       initFamilyPlanning,
    maternalCare:         initMaternalCare,
    childCare:            initChildCare,
    oralHealth:           initOralHealth,
    nonCommunicableDisease: initNCD,
    geriatricHealth:      initGeriatric,
    infectiousDisease:    initInfectious,
    wash:                 initWash,
    regions = [],
    provinces:            initProvinces = [],
    municipalities:       initMunicipalities = [],
    barangays:            initBarangays = [],
    isValidated:          initIsValidated = false,
    filters,
}: PublicNursePageProps) {

    // ── Active tab ────────────────────────────────────────────────────────────
    const [activeTab, setActiveTab] = useState<TabKey>('familyPlanning');

    // ── Filter state ──────────────────────────────────────────────────────────
    const currentYear  = new Date().getFullYear().toString();
    const currentMonth = String(new Date().getMonth() + 1).padStart(2, '0');

    const [selectedMonth,    setSelectedMonth]    = useState(filters?.month        || currentMonth);
    const [selectedYear,     setSelectedYear]     = useState(filters?.year         || currentYear);
    const [selectedRegion,   setSelectedRegion]   = useState(filters?.region       || '');
    const [selectedProvince, setSelectedProvince] = useState(filters?.province     || '');
    const [selectedMuni,     setSelectedMuni]     = useState(filters?.municipality || '');
    const [selectedBarangay, setSelectedBarangay] = useState(filters?.barangay     || '');

    // ── Location options (AJAX-managed) ───────────────────────────────────────
    const [provinces,     setProvinces]     = useState<LocationOption[]>(initProvinces);
    const [municipalities,setMunicipalities]= useState<LocationOption[]>(initMunicipalities);
    const [barangays,     setBarangays]     = useState<LocationOption[]>(initBarangays);

    // ── Report data (AJAX-managed) ────────────────────────────────────────────
    const [familyPlanning,       setFamilyPlanning]       = useState(initFamilyPlanning);
    const [maternalCare,         setMaternalCare]         = useState(initMaternalCare);
    const [childCare,            setChildCare]            = useState(initChildCare);
    const [oralHealth,           setOralHealth]           = useState(initOralHealth);
    const [nonCommunicableDisease,setNCD]                 = useState(initNCD);
    const [geriatricHealth,      setGeriatric]            = useState(initGeriatric);
    const [infectiousDisease,    setInfectious]           = useState(initInfectious);
    const [wash,                 setWash]                 = useState(initWash);
    const [isValidated,          setIsValidated]          = useState(initIsValidated);

    // ── Loading flags ─────────────────────────────────────────────────────────
    const [loadingData,      setLoadingData]      = useState(false);
    const [loadingLocations, setLoadingLocations] = useState(false);

    // Abort controller so rapid filter changes cancel the previous in-flight request
    const abortRef = useRef<AbortController | null>(null);

    // ── Sync filter state when Inertia props change externally ────────────────
    useEffect(() => {
        setSelectedRegion(filters?.region       || '');
        setSelectedProvince(filters?.province   || '');
        setSelectedMuni(filters?.municipality   || '');
        setSelectedBarangay(filters?.barangay   || '');
    }, [filters]);

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX: fetch report data
    // ─────────────────────────────────────────────────────────────────────────
    const fetchReportData = useCallback(async (params: Record<string, string>) => {
        // Cancel any in-flight request
        if (abortRef.current) abortRef.current.abort();
        abortRef.current = new AbortController();

        setLoadingData(true);
        try {
            const qs = new URLSearchParams(params).toString();
            const res = await fetch(`/qfhsis/public/fhsis/public-nurse/data?${qs}`, {
                headers: {
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: abortRef.current.signal,
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();

            if (json.familyPlanning       !== undefined) setFamilyPlanning(json.familyPlanning);
            if (json.maternalCare         !== undefined) setMaternalCare(json.maternalCare);
            if (json.childCare            !== undefined) setChildCare(json.childCare);
            if (json.oralHealth           !== undefined) setOralHealth(json.oralHealth);
            if (json.nonCommunicableDisease !== undefined) setNCD(json.nonCommunicableDisease);
            if (json.geriatricHealth      !== undefined) setGeriatric(json.geriatricHealth);
            if (json.infectiousDisease    !== undefined) setInfectious(json.infectiousDisease);
            if (json.wash                 !== undefined) setWash(json.wash);
            if (json.isValidated          !== undefined) setIsValidated(json.isValidated);
        } catch (err: unknown) {
            if ((err as { name?: string }).name !== 'AbortError') {
                console.error('Failed to fetch report data:', err);
            }
        } finally {
            setLoadingData(false);
        }
    }, []);

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX: fetch location options (cascading dropdowns)
    // ─────────────────────────────────────────────────────────────────────────
    const fetchLocations = useCallback(async (level: 'provinces' | 'municipalities' | 'barangays', code: string) => {
        setLoadingLocations(true);
        try {
            const res = await fetch(`/api/locations/${level}?code=${encodeURIComponent(code)}`, {
                headers: {
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();
            if (level === 'provinces')     setProvinces(json);
            if (level === 'municipalities') setMunicipalities(json);
            if (level === 'barangays')     setBarangays(json);
        } catch (err) {
            console.error(`Failed to fetch ${level}:`, err);
        } finally {
            setLoadingLocations(false);
        }
    }, []);

    // ─────────────────────────────────────────────────────────────────────────
    // Filter helpers
    // ─────────────────────────────────────────────────────────────────────────
    const buildParams = (overrides: Record<string, string> = {}): Record<string, string> => ({
        month:        selectedMonth,
        year:         selectedYear,
        region:       selectedRegion,
        province:     selectedProvince,
        municipality: selectedMuni,
        barangay:     selectedBarangay,
        ...overrides,
    });

    const handleFilterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        fetchReportData(buildParams());
    };

    const handleRegionChange = (val: string) => {
        setSelectedRegion(val);
        setSelectedProvince('');
        setSelectedMuni('');
        setSelectedBarangay('');
        setProvinces([]);
        setMunicipalities([]);
        setBarangays([]);
        if (val) fetchLocations('provinces', val);
    };

    const handleProvinceChange = (val: string) => {
        setSelectedProvince(val);
        setSelectedMuni('');
        setSelectedBarangay('');
        setMunicipalities([]);
        setBarangays([]);
        if (val) fetchLocations('municipalities', val);
    };

    const handleMuniChange = (val: string) => {
        setSelectedMuni(val);
        setSelectedBarangay('');
        setBarangays([]);
        if (val) fetchLocations('barangays', val);
    };

    // ─────────────────────────────────────────────────────────────────────────
    // Validate (still uses Inertia POST — keeps CSRF handling simple)
    // ─────────────────────────────────────────────────────────────────────────
    const handleValidate = () => {
        router.post('/qfhsis/public/fhsis/public-nurse/validate', {
            month:        selectedMonth,
            year:         selectedYear,
            region:       selectedRegion,
            province:     selectedProvince,
            municipality: selectedMuni,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsValidated(true);
                alert('Report configurations successfully validated and saved!');
            },
        });
    };

    // ─────────────────────────────────────────────────────────────────────────
    // filterKey — drives key prop resets on child components
    // ─────────────────────────────────────────────────────────────────────────
    const filterKey = `${selectedMonth}-${selectedYear}-${selectedRegion}-${selectedProvince}-${selectedMuni}-${selectedBarangay}`;

    // ─────────────────────────────────────────────────────────────────────────
    // Render
    // ─────────────────────────────────────────────────────────────────────────
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Public Health Nurse Reports" />

            {/* 1. Navigation Tabs */}
            <nav className="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-10">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center overflow-x-auto gap-1 scrollbar-none">
                    {tabs.map((tab) => (
                        <button
                            key={tab.key}
                            onClick={() => setActiveTab(tab.key)}
                            className={`px-3 py-2 text-xs md:text-sm font-medium rounded-md transition-all whitespace-nowrap ${
                                activeTab === tab.key
                                    ? 'bg-blue-50 text-blue-700 font-semibold'
                                    : 'text-gray-600 hover:text-blue-600 hover:bg-gray-50'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>
            </nav>

            {/* 2. Filter Sub-Bar */}
            <div className="bg-gray-50 border-b border-gray-200 py-3 px-4 sm:px-6 lg:px-8">
                <div className="max-w-7xl mx-auto flex flex-col gap-3">
                    <div>
                        <h2 className="text-sm font-semibold text-gray-700">
                            {tabs.find((t) => t.key === activeTab)?.label} Reports
                        </h2>
                    </div>

                    <form
                        onSubmit={handleFilterSubmit}
                        className="flex flex-wrap items-center gap-4 bg-white p-3 rounded-md border border-gray-200 shadow-sm"
                    >
                        {/* Month */}
                        <div className="flex flex-col gap-1 min-w-[120px]">
                            <label htmlFor="month" className="text-[10px] font-semibold uppercase tracking-wider text-gray-500">
                                Month
                            </label>
                            <select
                                id="month"
                                value={selectedMonth}
                                onChange={(e) => setSelectedMonth(e.target.value)}
                                className="block w-full rounded-md border-gray-300 py-1 pl-2 pr-8 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 shadow-sm bg-white"
                            >
                                {months.map((m) => (
                                    <option key={m.value} value={m.value}>{m.label}</option>
                                ))}
                            </select>
                        </div>

                        {/* Year */}
                        <div className="flex flex-col gap-1">
                            <label htmlFor="year" className="text-[10px] font-semibold uppercase tracking-wider text-gray-500">
                                Year
                            </label>
                            <input
                                id="year"
                                type="number"
                                min="2000"
                                max="2099"
                                value={selectedYear}
                                onChange={(e) => setSelectedYear(e.target.value)}
                                className="block w-20 rounded-md border-gray-300 py-1 px-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 shadow-sm bg-white"
                            />
                        </div>

                        {/* Region */}
                        <div className="flex flex-col gap-1 min-w-[150px]">
                            <label htmlFor="region" className="text-[10px] font-semibold uppercase tracking-wider text-gray-500">
                                Region
                            </label>
                            <select
                                id="region"
                                value={selectedRegion}
                                onChange={(e) => handleRegionChange(e.target.value)}
                                className="block w-full rounded-md border-gray-300 py-1 pl-2 pr-8 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 shadow-sm bg-white"
                            >
                                <option value="">All Regions</option>
                                {regions.map((reg) => (
                                    <option key={reg.regCode} value={reg.regCode}>{reg.regDesc}</option>
                                ))}
                            </select>
                        </div>

                        {/* Province */}
                        <div className="flex flex-col gap-1 min-w-[150px]">
                            <label htmlFor="province" className="text-[10px] font-semibold uppercase tracking-wider text-gray-500">
                                Province
                            </label>
                            <select
                                id="province"
                                value={selectedProvince}
                                disabled={!selectedRegion || loadingLocations}
                                onChange={(e) => handleProvinceChange(e.target.value)}
                                className="block w-full rounded-md border-gray-300 py-1 pl-2 pr-8 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 shadow-sm bg-white disabled:bg-gray-100 disabled:text-gray-400"
                            >
                                <option value="">All Provinces</option>
                                {provinces.map((prov) => (
                                    <option key={prov.provCode} value={prov.provCode}>{prov.provDesc}</option>
                                ))}
                            </select>
                        </div>

                        {/* Municipality */}
                        <div className="flex flex-col gap-1 min-w-[150px]">
                            <label htmlFor="municipality" className="text-[10px] font-semibold uppercase tracking-wider text-gray-500">
                                Municipality
                            </label>
                            <select
                                id="municipality"
                                value={selectedMuni}
                                disabled={!selectedProvince || loadingLocations}
                                onChange={(e) => handleMuniChange(e.target.value)}
                                className="block w-full rounded-md border-gray-300 py-1 pl-2 pr-8 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 shadow-sm bg-white disabled:bg-gray-100 disabled:text-gray-400"
                            >
                                <option value="">All Municipalities</option>
                                {municipalities.map((mun) => (
                                    <option key={mun.citymunCode} value={mun.citymunCode}>{mun.citymunDesc}</option>
                                ))}
                            </select>
                        </div>

                        {/* Barangay */}
                        <div className="flex flex-col gap-1 min-w-[150px]">
                            <label htmlFor="barangay" className="text-[10px] font-semibold uppercase tracking-wider text-gray-500">
                                Barangay
                            </label>
                            <select
                                id="barangay"
                                value={selectedBarangay}
                                disabled={!selectedMuni || loadingLocations}
                                onChange={(e) => setSelectedBarangay(e.target.value)}
                                className="block w-full rounded-md border-gray-300 py-1 pl-2 pr-8 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 shadow-sm bg-white disabled:bg-gray-100 disabled:text-gray-400"
                            >
                                <option value="">All Barangays</option>
                                {barangays.map((brgy) => (
                                    <option key={brgy.brgyCode} value={brgy.brgyCode}>{brgy.brgyDesc}</option>
                                ))}
                            </select>
                        </div>

                        {/* Action buttons */}
                        <div className="flex items-center gap-2 mt-auto pb-0.5 ml-auto sm:ml-0">
                            <button
                                type="submit"
                                disabled={loadingData}
                                className="inline-flex justify-center rounded-md bg-blue-600 py-1.5 px-4 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                            >
                                {loadingData ? 'Loading…' : 'Apply'}
                            </button>

                            {isValidated ? (
                                <span className="inline-flex justify-center rounded-md bg-gray-100 border border-gray-300 py-1.5 px-4 text-sm font-medium text-gray-400 select-none shadow-sm cursor-not-allowed">
                                    ✓ Validated
                                </span>
                            ) : (
                                <button
                                    type="button"
                                    onClick={handleValidate}
                                    className="inline-flex justify-center rounded-md bg-green-600 py-1.5 px-4 text-sm font-medium text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors"
                                >
                                    Validate
                                </button>
                            )}
                        </div>
                    </form>
                </div>
            </div>

            {/* 3. Dynamic Content */}
            <div className="mt-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12 relative">
                {loadingData && <LoadingOverlay />}

                {activeTab === 'familyPlanning' && (
                    <FamilyPlanning key={filterKey} clients={familyPlanning} pageSize={PAGE_SIZE} />
                )}
                {activeTab === 'maternalCare' && (
                    <MaternalCare key={filterKey} clients={maternalCare} pageSize={PAGE_SIZE} />
                )}
                {activeTab === 'childCare' && (
                    <ChildCare key={filterKey} clients={childCare} pageSize={PAGE_SIZE} />
                )}
                {activeTab === 'oralHealth' && (
                    <OralHealthCare key={filterKey} clients={oralHealth} pageSize={PAGE_SIZE} />
                )}
                {activeTab === 'nonCommunicableDisease' && (
                    <NonCommunicableDisease key={filterKey} clients={nonCommunicableDisease} pageSize={PAGE_SIZE} />
                )}
                {activeTab === 'geriatricHealth' && (
                    <GeriatricScreening key={filterKey} clients={geriatricHealth} pageSize={PAGE_SIZE} />
                )}
                {activeTab === 'infectiousDisease' && (
                    <InfectiousDisease key={filterKey} clients={infectiousDisease} pageSize={PAGE_SIZE} />
                )}
                {activeTab === 'wash' && (
                    <EnvironmentalHealth key={filterKey} records={wash} pageSize={PAGE_SIZE} />
                )}
            </div>
        </AppLayout>
    );
}