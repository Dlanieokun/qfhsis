import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

// 1. Import your section components here as they're built
import FamilyPlanning, { type FamilyPlanningClient } from './nurse-page/FamilyPlanning';
import MaternalCare, { type MaternalCareClient } from './nurse-page/MaternalCare';
import ChildCare, { type ChildCareClient } from './nurse-page/child/ChildCare';
import OralHealthCare, { type OralHealthClient } from './nurse-page/OralHealthCare';
import NonCommunicableDisease, { type NonCommunicableDiseaseClient } from './nurse-page/ncdpcs/NonCommunicableDisease';
import GeriatricScreening, { type GeriatricClient } from './nurse-page/GeriatricScreening';
import InfectiousDisease, { type InfectiousDiseaseClient } from './nurse-page/idpcs/InfectiousDisease';
import EnvironmentalHealth, { type EnvironmentalHealthRecord } from './nurse-page/EnvironmentalHealth';

// Shape of the data PublicNurseController@publicNurse (or similar) passes
// into Inertia::render('fhsis/public-nurse', [...]).
//
// IMPORTANT: childCare, nonCommunicableDisease, and infectiousDisease are
// tabs-of-tabs — each one renders its own sub-navigation with its own table.
// They must receive an object keyed by sub-service, not a flat array,
// otherwise the sub-tables have nothing to render and silently fall back to
// empty/mock data even though the database has rows. This is the piece that
// was missing before: the controller can send real records all the way down
// to every leaf table.
interface PublicNursePageProps {
    familyPlanning?: FamilyPlanningClient[];
    maternalCare?: MaternalCareClient[];
    childCare?: ChildCareClient;
    oralHealth?: OralHealthClient[];
    nonCommunicableDisease?: NonCommunicableDiseaseClient;
    geriatricHealth?: GeriatricClient[];
    infectiousDisease?: InfectiousDiseaseClient;
    wash?: EnvironmentalHealthRecord[];
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
    { key: 'maternalCare', label: 'Maternal Care and Services' },
    { key: 'childCare', label: 'Child Care Services' },
    { key: 'oralHealth', label: 'Oral Health Care and Services' },
    { key: 'nonCommunicableDisease', label: 'Non-Communicable Disease Prevention and Control Services' },
    { key: 'geriatricHealth', label: 'Geriatric Health' },
    { key: 'infectiousDisease', label: 'Infectious Disease Prevention and Control Services' },
    { key: 'wash', label: 'Water Sanitation, and Hygiene (WASH)' },
];

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'FHSIS', href: '/fhsis/dashboard' },
    { title: 'Public Health Nurse', href: '/fhsis/public-nurse' },
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
}: PublicNursePageProps) {
    const [activeTab, setActiveTab] = useState<TabKey>('familyPlanning');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Public Health Nurse Reports" />

            {/* Navigation Bar */}
            <nav className="bg-white border-b border-gray-200 p-4 shadow-sm">
                <div className="max-w-7xl mx-auto flex flex-wrap justify-center gap-4">
                    {tabs.map((tab) => (
                        <button
                            key={tab.key}
                            onClick={() => setActiveTab(tab.key)}
                            className={`px-3 py-2 text-sm font-medium transition ${
                                activeTab === tab.key
                                    ? 'text-blue-600 border-b-2 border-blue-600 font-semibold'
                                    : 'text-gray-600 hover:text-blue-600'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>
            </nav>

            {/* Dynamic Content Switching */}
            <div className="mt-6 max-w-7xl mx-auto px-6 pb-12">
                {/* 2. Swap each placeholder below for its real component once built.
                     Every tab below now receives whatever the controller sent for
                     it — no more components silently rendering with no data. */}

                {activeTab === 'familyPlanning' && <FamilyPlanning clients={familyPlanning} />}

                {activeTab === 'maternalCare' && <MaternalCare clients={maternalCare} />}

                {activeTab === 'childCare' && <ChildCare clients={childCare} />}

                {activeTab === 'oralHealth' && <OralHealthCare clients={oralHealth} />}

                {activeTab === 'nonCommunicableDisease' && <NonCommunicableDisease clients={nonCommunicableDisease} />}

                {activeTab === 'geriatricHealth' && <GeriatricScreening clients={geriatricHealth} />}

                {activeTab === 'infectiousDisease' && <InfectiousDisease clients={infectiousDisease} />}

                {activeTab === 'wash' && <EnvironmentalHealth records={wash} />}
            </div>
        </AppLayout>
    );
}