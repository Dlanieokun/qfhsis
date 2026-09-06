import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

// Import child components
import M1AllPrograms from './M1AllPrograms';
import Q1AllPrograms from './Q1AllPrograms';
import M28PAA from './M28PAA';
import A1AllPrograms from './A1AllPrograms';
import MorbidityPage from './MorbidityPage';

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
    { title: 'FHSIS', href: '/qfhsis/public/fhsis/dashboard' },
    { title: 'PHO Form M1', href: '/qfhsis/public/fhsis/pho' },
];

export default function PhoPage({
    regions = [], provinces = [], municipalities = [], barangays = []
}: PhoPageProps) {
    const [activeTab, setActiveTab] = useState<'m1' | 'q1' | 'm2' | 'a1' | 'mo'>('m1');

    const tabs = [
        { id: 'm1', label: 'M1_All Programs' },
        { id: 'q1', label: 'Q1_All Programs' },
        { id: 'm2', label: 'M2_8PAA' },
        { id: 'a1', label: 'A1_All Program' },
        { id: 'mo', label: 'M2_Morbidity' },
    ] as const;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="PHO Reports" />

            <div className="max-w-7xl mx-auto px-6 pt-6">
                <motion.nav 
                    initial={{ opacity: 0, y: -20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ type: 'spring', stiffness: 200, damping: 25 }}
                    className="flex justify-center p-2 rounded-2xl shadow-lg overflow-hidden"
                    style={{
                        background: 'linear-gradient(160deg, #0f2d6b 0%, #1a5276 25%, #117a65 60%, #0e6655 100%)',
                    }}
                >
                    <div className="flex gap-2 w-full justify-center">
                        {tabs.map((tab) => {
                            const isActive = activeTab === tab.id;
                            return (
                                <button
                                    key={tab.id}
                                    onClick={() => setActiveTab(tab.id)}
                                    className={[
                                        'px-6 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 hover:scale-[1.02]',
                                        isActive
                                            ? 'text-white'
                                            : 'text-white/70 hover:bg-white/10 hover:text-white',
                                    ].join(' ')}
                                    style={isActive ? {
                                        background: 'linear-gradient(90deg, rgba(255,255,255,0.22) 0%, rgba(255,255,255,0.08) 100%)',
                                        boxShadow: 'inset 0 0 0 1px rgba(255,255,255,0.15)',
                                    } : {}}
                                >
                                    {tab.label}
                                </button>
                            );
                        })}
                    </div>
                </motion.nav>
            </div>

            <div className="mt-6 max-w-7xl mx-auto px-6 pb-12">
                <AnimatePresence mode="wait">
                    <motion.div
                        key={activeTab}
                        initial={{ opacity: 0, y: 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -10 }}
                        transition={{ duration: 0.2 }}
                    >
                        {activeTab === 'm1' && (
                            <M1AllPrograms
                                regions={regions}
                                provinces={provinces}
                                municipalities={municipalities}
                                barangays={barangays}
                            />
                        )}
                        {activeTab === 'q1' && (
                            <Q1AllPrograms
                                regions={regions}
                                provinces={provinces}
                                municipalities={municipalities}
                                barangays={barangays}
                            />
                        )}
                        {activeTab === 'm2' && 
                            <M28PAA 
                                regions={regions}
                                provinces={provinces}
                                municipalities={municipalities}
                                barangays={barangays}
                            />
                        }
                        {activeTab === 'a1' && 
                            <A1AllPrograms 
                                regions={regions}
                                provinces={provinces}
                                municipalities={municipalities}
                            />
                        }
                        {activeTab === 'mo' && 
                            <MorbidityPage
                                // regions={regions}
                                // provinces={provinces}
                                // municipalities={municipalities}
                            />
                        }
                    </motion.div>
                </AnimatePresence>
            </div>
        </AppLayout>
    );
}