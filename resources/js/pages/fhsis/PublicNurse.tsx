import React, { useState, useEffect } from 'react';
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

const tabs: TabDefinition[] = [
    { key: 'familyPlanning', label: 'Family Planning' },
    { key: 'maternalCare', label: 'Maternal Care' },
    { key: 'childCare', label: 'Child Care' },
    { key: 'oralHealth', label: 'Oral Health' },
    { key: 'nonCommunicableDisease', label: 'NCD Prevention' },
    { key: 'geriatricHealth', label: 'Geriatric Health' },
    { key: 'infectiousDisease', label: 'Infectious Disease' },
    { key: 'wash', label: 'WASH' },
];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'FHSIS', href: '/fhsis/dashboard' },
    { title: 'Public Health Nurse', href: '/fhsis/public-nurse' },
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

export default function PublicNursePage({
    familyPlanning,
    maternalCare,
    childCare,
    oralHealth,
    nonCommunicableDisease,
    geriatricHealth,
    infectiousDisease,
    wash,
    regions = [],
    provinces = [],
    municipalities = [],
    barangays = [],
    isValidated = false,
    filters,
}: PublicNursePageProps) {
    const [activeTab, setActiveTab] = useState<TabKey>('familyPlanning');

    const currentYear = new Date().getFullYear().toString();
    const currentMonth = String(new Date().getMonth() + 1).padStart(2, '0');

    const [selectedMonth, setSelectedMonth] = useState(filters?.month || currentMonth);
    const [selectedYear, setSelectedYear] = useState(filters?.year || currentYear);
    
    const [selectedRegion, setSelectedRegion] = useState(filters?.region || '');
    const [selectedProvince, setSelectedProvince] = useState(filters?.province || '');
    const [selectedMunicipality, setSelectedMunicipality] = useState(filters?.municipality || '');
    const [selectedBarangay, setSelectedBarangay] = useState(filters?.barangay || '');

    // Synchronize layout state when filters change externally
    useEffect(() => {
        setSelectedRegion(filters?.region || '');
        setSelectedProvince(filters?.province || '');
        setSelectedMunicipality(filters?.municipality || '');
        setSelectedBarangay(filters?.barangay || '');
    }, [filters]);

    const filterKey = `${filters?.month ?? selectedMonth}-${filters?.year ?? selectedYear}-${filters?.region ?? ''}-${filters?.province ?? ''}-${filters?.municipality ?? ''}-${filters?.barangay ?? ''}`;

    const triggerLocationQuery = (updatedFilters: Record<string, string>) => {
        router.get(
            '/fhsis/public-nurse',
            { 
                month: selectedMonth, 
                year: selectedYear,
                region: selectedRegion,
                province: selectedProvince,
                municipality: selectedMunicipality,
                barangay: selectedBarangay,
                ...updatedFilters
            },
            {
                preserveState: true,  
                preserveScroll: true, 
            }
        );
    };

    const handleFilterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        triggerLocationQuery({});
    };

    const handleValidate = () => {
        router.post('/fhsis/public-nurse/validate', {
            month: selectedMonth,
            year: selectedYear,
            region: selectedRegion,
            province: selectedProvince,
            municipality: selectedMunicipality,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                alert('Report configurations successfully validated and saved!');
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Public Health Nurse Reports" />

            {/* 1. Navigation Bar (Tabs Only) */}
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

            {/* 2. Filter Sub-Bar (Directly Below Navigation) */}
            <div className="bg-gray-50 border-b border-gray-200 py-3 px-4 sm:px-6 lg:px-8">
                <div className="max-w-7xl mx-auto flex flex-col gap-3">
                    <div>
                        <h2 className="text-sm font-semibold text-gray-700">
                            {tabs.find(t => t.key === activeTab)?.label} Reports
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
                                    <option key={m.value} value={m.value}>
                                        {m.label}
                                    </option>
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

                        {/* Region Dropdown */}
                        <div className="flex flex-col gap-1 min-w-[150px]">
                            <label htmlFor="region" className="text-[10px] font-semibold uppercase tracking-wider text-gray-500">
                                Region
                            </label>
                            <select
                                id="region"
                                value={selectedRegion}
                                onChange={(e) => {
                                    const val = e.target.value;
                                    setSelectedRegion(val);
                                    setSelectedProvince('');
                                    setSelectedMunicipality('');
                                    setSelectedBarangay('');
                                    triggerLocationQuery({ region: val, province: '', municipality: '', barangay: '' });
                                }}
                                className="block w-full rounded-md border-gray-300 py-1 pl-2 pr-8 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 shadow-sm bg-white"
                            >
                                <option value="">All Regions</option>
                                {regions.map((reg) => (
                                    <option key={reg.regCode} value={reg.regCode}>{reg.regDesc}</option>
                                ))}
                            </select>
                        </div>

                        {/* Province Dropdown */}
                        <div className="flex flex-col gap-1 min-w-[150px]">
                            <label htmlFor="province" className="text-[10px] font-semibold uppercase tracking-wider text-gray-500">
                                Province
                            </label>
                            <select
                                id="province"
                                value={selectedProvince}
                                disabled={!selectedRegion}
                                onChange={(e) => {
                                    const val = e.target.value;
                                    setSelectedProvince(val);
                                    setSelectedMunicipality('');
                                    setSelectedBarangay('');
                                    triggerLocationQuery({ province: val, municipality: '', barangay: '' });
                                }}
                                className="block w-full rounded-md border-gray-300 py-1 pl-2 pr-8 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 shadow-sm bg-white disabled:bg-gray-100 disabled:text-gray-400"
                            >
                                <option value="">All Provinces</option>
                                {provinces.map((prov) => (
                                    <option key={prov.provCode} value={prov.provCode}>{prov.provDesc}</option>
                                ))}
                            </select>
                        </div>

                        {/* Municipality Dropdown */}
                        <div className="flex flex-col gap-1 min-w-[150px]">
                            <label htmlFor="municipality" className="text-[10px] font-semibold uppercase tracking-wider text-gray-500">
                                Municipality
                            </label>
                            <select
                                id="municipality"
                                value={selectedMunicipality}
                                disabled={!selectedProvince}
                                onChange={(e) => {
                                    const val = e.target.value;
                                    setSelectedMunicipality(val);
                                    setSelectedBarangay('');
                                    triggerLocationQuery({ municipality: val, barangay: '' });
                                }}
                                className="block w-full rounded-md border-gray-300 py-1 pl-2 pr-8 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 shadow-sm bg-white disabled:bg-gray-100 disabled:text-gray-400"
                            >
                                <option value="">All Municipalities</option>
                                {municipalities.map((mun) => (
                                    <option key={mun.citymunCode} value={mun.citymunCode}>{mun.citymunDesc}</option>
                                ))}
                            </select>
                        </div>

                        {/* Barangay Dropdown */}
                        <div className="flex flex-col gap-1 min-w-[150px]">
                            <label htmlFor="barangay" className="text-[10px] font-semibold uppercase tracking-wider text-gray-500">
                                Barangay
                            </label>
                            <select
                                id="barangay"
                                value={selectedBarangay}
                                disabled={!selectedMunicipality}
                                onChange={(e) => setSelectedBarangay(e.target.value)}
                                className="block w-full rounded-md border-gray-300 py-1 pl-2 pr-8 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 shadow-sm bg-white disabled:bg-gray-100 disabled:text-gray-400"
                            >
                                <option value="">All Barangays</option>
                                {barangays.map((brgy) => (
                                    <option key={brgy.brgyCode} value={brgy.brgyCode}>{brgy.brgyDesc}</option>
                                ))}
                            </select>
                        </div>

                        {/* Actions Container */}
                        <div className="flex items-center gap-2 mt-auto pb-0.5 ml-auto sm:ml-0">
                            <button
                                type="submit"
                                className="inline-flex justify-center rounded-md bg-blue-600 py-1.5 px-4 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                            >
                                Apply
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

            {/* 3. Dynamic Content Display */}
            <div className="mt-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
                {activeTab === 'familyPlanning' && <FamilyPlanning key={filterKey} clients={familyPlanning} />}
                {activeTab === 'maternalCare' && <MaternalCare key={filterKey} clients={maternalCare} />}
                {activeTab === 'childCare' && <ChildCare key={filterKey} clients={childCare} />}
                {activeTab === 'oralHealth' && <OralHealthCare key={filterKey} clients={oralHealth} />}
                {activeTab === 'nonCommunicableDisease' && <NonCommunicableDisease key={filterKey} clients={nonCommunicableDisease} />}
                {activeTab === 'geriatricHealth' && <GeriatricScreening key={filterKey} clients={geriatricHealth} />}
                {activeTab === 'infectiousDisease' && <InfectiousDisease key={filterKey} clients={infectiousDisease} />}
                {activeTab === 'wash' && <EnvironmentalHealth key={filterKey} records={wash} />}
            </div>
        </AppLayout>
    );
}