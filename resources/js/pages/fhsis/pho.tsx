import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

// Import child components
import M1AllPrograms from './M1AllPrograms';
import M28PAA from './M28PAA';
import A1AllPrograms from './A1AllPrograms';

// ─── Location Data Shapes ────────────────────────────────────────────────────
interface Region { regCode: string; regDesc: string; }
interface Province { provCode: string; provDesc: string; regCode: string; }
interface Municipality { citymunCode: string; citymunDesc: string; provCode: string; }
interface Barangay { brgyCode: string; brgyDesc: string; citymunCode: string; }

// ─── Form Data Shapes ────────────────────────────────────────────────────────
interface FamilyPlanningBrackets {
    '10-14': number;
    '15-19': number;
    '20-49': number;
    total: number;
}
interface FamilyPlanningData {
    demandSatisfied: FamilyPlanningBrackets;
    currentUsersByMethod: Record<string, FamilyPlanningBrackets>;
}

interface AgeBrackets { '10-14': number; '15-19': number; '20-49': number; total: number; }
interface SexBrackets { male: number; female: number; total: number; }

interface MaternalCareData {
    prenatal: Record<string, AgeBrackets>;
    intrapartum: Record<string, SexBrackets>;
    postpartum: Record<string, AgeBrackets>;
}

interface ChildCareData {
    imm0_11: Record<string, SexBrackets>;
    immPrev: Record<string, SexBrackets>;
    schoolImm: Record<string, SexBrackets>;
    nutrition: Record<string, SexBrackets>;
    nutrition2: Record<string, SexBrackets>;
    mgmtSick: Record<string, SexBrackets>;
}

interface OralHealthData {
    infantFirstVisit: SexBrackets;
    firstVisit: Record<string, SexBrackets>;
    firstVisitFacility: Record<string, SexBrackets>;
    firstVisitNonFacility: Record<string, SexBrackets>;
    completed2Visits: Record<string, SexBrackets>;
    completed2VisitsFacility: Record<string, SexBrackets>;
    completed2VisitsNonFacility: Record<string, SexBrackets>;
}

interface CervicalCancerTotals {
    screened: number; via: number; papSmear: number; hpvDna: number; assessedOnly: number;
    suspicious: number; linkedToCare: number; linkedTreated: number; linkedReferred: number;
}
interface BreastCancerTotals {
    seen: number; highRiskOrSymptomatic: number; providedCbe: number; providedMammogram: number;
    remarkableCbe: number; remarkableMammogram: number; linkedToCare: number; asymptomaticScreened: number;
}
interface NonCommunicableDiseaseData {
    lifestyle2059: Record<string, SexBrackets>;
    lifestyle60plus: Record<string, SexBrackets>;
    cvd2059: SexBrackets;
    cvd60plus: SexBrackets;
    dm2059: SexBrackets;
    dm60plus: SexBrackets;
    blindness: Record<string, SexBrackets>;
    mentalHealth: Record<string, SexBrackets>;
    cervical: CervicalCancerTotals;
    breast: BreastCancerTotals;
}

interface EnvironmentalHealthData {
    water: { levelI: number; levelII: number; levelIII: number; safelyManaged: number; total: number };
    sanitation: {
        pourFlushSeptic: number; pourFlushSewer: number; vip: number;
        basicSanitationFacility: number; safelyManagedSanitation: number; total: number;
    };
}

interface InfectiousDiseaseData {
    filariasis: Record<string, SexBrackets>;
    rabies: Record<string, SexBrackets>;
    schistosomiasis: Record<string, SexBrackets>;
    sth: Record<string, SexBrackets>;
    leprosy: Record<string, SexBrackets>;
}

interface PhoPageProps {
    familyPlanning?: FamilyPlanningData;
    maternalCare?: MaternalCareData;
    childCare?: ChildCareData;
    oralHealth?: OralHealthData;
    nonCommunicableDisease?: NonCommunicableDiseaseData;
    environmentalHealth?: EnvironmentalHealthData;
    infectiousDisease?: InfectiousDiseaseData;
    // Location Data injected via controller
    regions?: Region[];
    provinces?: Province[];
    municipalities?: Municipality[];
    barangays?: Barangay[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'FHSIS', href: '/fhsis/dashboard' },
    { title: 'PHO Form M1', href: '/fhsis/pho' },
];

export default function PhoPage({
    familyPlanning, maternalCare, childCare,
    oralHealth, nonCommunicableDisease, environmentalHealth, infectiousDisease,
    regions = [], provinces = [], municipalities = [], barangays = []
}: PhoPageProps) {
    const [activeTab, setActiveTab] = useState<'m1' | 'q1' | 'm2' | 'a1'>('m1');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="PHO Reports" />

            <nav className="bg-white border-b border-gray-200 p-4 shadow-sm">
                <div className="max-w-7xl mx-auto flex justify-center gap-6">
                    <button
                        onClick={() => setActiveTab('m1')}
                        className={`px-3 py-2 font-medium transition ${
                            activeTab === 'm1'
                                ? 'text-blue-600 border-b-2 border-blue-600 font-semibold'
                                : 'text-gray-600 hover:text-blue-600'
                        }`}
                    >
                        M1_All Programs
                    </button>
                    <button
                        onClick={() => setActiveTab('q1')}
                        className={`px-3 py-2 font-medium transition ${
                            activeTab === 'q1'
                                ? 'text-blue-600 border-b-2 border-blue-600 font-semibold'
                                : 'text-gray-600 hover:text-blue-600'
                        }`}
                    >
                        Q1_All Programs
                    </button>
                    <button
                        onClick={() => setActiveTab('m2')}
                        className={`px-3 py-2 font-medium transition ${
                            activeTab === 'm2'
                                ? 'text-blue-600 border-b-2 border-blue-600 font-semibold'
                                : 'text-gray-600 hover:text-blue-600'
                        }`}
                    >
                        M2_8PAA
                    </button>
                    <button
                        onClick={() => setActiveTab('a1')}
                        className={`px-3 py-2 font-medium transition ${
                            activeTab === 'a1'
                                ? 'text-blue-600 border-b-2 border-blue-600 font-semibold'
                                : 'text-gray-600 hover:text-blue-600'
                        }`}
                    >
                        A1_All Program
                    </button>
                </div>
            </nav>

            <div className="mt-6 max-w-7xl mx-auto px-6 pb-12">
                {activeTab === 'm1' && (
                    <M1AllPrograms
                        familyPlanning={familyPlanning}
                        maternalCare={maternalCare}
                        childCare={childCare}
                        oralHealth={oralHealth}
                        nonCommunicableDisease={nonCommunicableDisease}
                        environmentalHealth={environmentalHealth}
                        infectiousDisease={infectiousDisease}
                        regions={regions}
                        provinces={provinces}
                        municipalities={municipalities}
                        barangays={barangays}
                    />
                )}
                {activeTab === 'q1' && (
                    <div className="p-6 bg-white rounded-lg shadow-sm border border-gray-200 text-gray-600">
                        Q1 All Programs Content Dashboard Component
                    </div>
                )}
                {activeTab === 'm2' && <M28PAA />}
                {activeTab === 'a1' && <A1AllPrograms />}
            </div>
        </AppLayout>
    );
}