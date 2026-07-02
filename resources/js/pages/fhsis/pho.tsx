import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

// 1. Import your newly created component here
import M1AllPrograms from './M1AllPrograms';
import M28PAA from './M28PAA';
import A1AllPrograms from './A1AllPrograms';

// Shape of the data PhoController@pho passes into Inertia::render('fhsis/pho', [...])
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

// Shape of the Section B (Maternal Care) data from PhoController@getMaternalCareData
interface AgeBrackets {
    '10-14': number;
    '15-19': number;
    '20-49': number;
    total: number;
}

interface SexBrackets {
    male: number;
    female: number;
    total: number;
}

interface MaternalCareData {
    prenatal: Record<string, AgeBrackets>;
    intrapartum: Record<string, SexBrackets>;
    postpartum: Record<string, AgeBrackets>;
}

// Shape of the Section C (Child Care) data from PhoController@getChildCareData
interface ChildCareData {
    imm0_11: Record<string, SexBrackets>;
    immPrev: Record<string, SexBrackets>;
    schoolImm: Record<string, SexBrackets>;
    nutrition: Record<string, SexBrackets>;
    nutrition2: Record<string, SexBrackets>;
    mgmtSick: Record<string, SexBrackets>;
}

interface PhoPageProps {
    familyPlanning?: FamilyPlanningData;
    maternalCare?: MaternalCareData;
    childCare?: ChildCareData;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'FHSIS', href: '/fhsis/dashboard' },
    { title: 'PHO Form M1', href: '/fhsis/pho' },
];

export default function PhoPage({ familyPlanning, maternalCare, childCare }: PhoPageProps) {
    const [activeTab, setActiveTab] = useState<'m1' | 'q1' | 'm2' | 'a1'>('m1');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="PHO Reports" />

            {/* Navigation Bar */}
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

            {/* Dynamic Content Switching */}
            <div className="mt-6 max-w-7xl mx-auto px-6 pb-12">
                
                {/* 2. Render the imported component when 'm1' is active, with live data */}
                {activeTab === 'm1' && (
                    <M1AllPrograms familyPlanning={familyPlanning} maternalCare={maternalCare} childCare={childCare} />
                )}

                {activeTab === 'q1' && (
                    <div className="p-6 bg-white rounded-lg shadow-sm border border-gray-200 text-gray-600">
                        Q1 All Programs Content Dashboard Component
                    </div>
                )}

                {activeTab === 'm2' && (
                    <M28PAA />
                )}

                {activeTab === 'a1' && (
                    <A1AllPrograms />
                )}
            </div>
        </AppLayout>
    );
}