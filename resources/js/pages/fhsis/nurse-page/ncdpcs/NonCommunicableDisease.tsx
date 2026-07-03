import React, { useState } from 'react';
import PhilPEN from './PhilPEN';
import EyesScreening from './EyesScreening';
import CervicalCancerScreening from './CervicalCancerScreening';
import MentalHealth from './MentalHealth';
import { TCLRow } from '../TargetClientListTable';

// Non-Communicable Disease Prevention and Control Services dashboard.
// Each sub-service has its own Target Client List data table (PhilPEN Risk
// Assessment, Eye Screening, Cervical Cancer, Mental Health).
//
// Rows are now seeded from the Inertia props coming out of
// PublicNurseController (backed by philpen_risk_assessments,
// eyes_screenings, cervical_cancer_screenings, mental_health_records)
// instead of always rendering empty tables.

type NcdTabKey = 'philpenRiskAssessment' | 'eyeScreening' | 'cervicalCancer' | 'mentalHealth';

interface NcdTabDefinition {
    key: NcdTabKey;
    label: string;
}

const ncdTabs: NcdTabDefinition[] = [
    { key: 'philpenRiskAssessment', label: 'PhilPen Risk Assessment' },
    { key: 'eyeScreening', label: 'Eye Screening' },
    { key: 'cervicalCancer', label: 'Cervical Cancer' },
    { key: 'mentalHealth', label: 'Mental Health' },
];

export interface NonCommunicableDiseaseClient {
    philpenRiskAssessment?: TCLRow[];
    eyeScreening?: TCLRow[];
    cervicalCancer?: TCLRow[];
    mentalHealth?: TCLRow[];
}

export default function NonCommunicableDisease({ clients }: { clients?: NonCommunicableDiseaseClient }) {
    const [activeSubTab, setActiveSubTab] = useState<NcdTabKey>('philpenRiskAssessment');

    const [philpenRows, setPhilpenRows] = useState<TCLRow[]>(clients?.philpenRiskAssessment ?? []);
    const [eyeScreeningRows, setEyeScreeningRows] = useState<TCLRow[]>(clients?.eyeScreening ?? []);
    const [cervicalCancerRows, setCervicalCancerRows] = useState<TCLRow[]>(clients?.cervicalCancer ?? []);
    const [mentalHealthRows, setMentalHealthRows] = useState<TCLRow[]>(clients?.mentalHealth ?? []);

    return (
        <div className="space-y-4">
            <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                <div className="border-b border-gray-200 px-4 pt-3">
                    <h2 className="text-base font-semibold text-gray-900">Non-Communicable Disease Prevention and Control Services</h2>
                    <p className="mb-3 text-sm text-gray-500">Select a service area to view or record client data</p>
                    <nav className="flex flex-wrap gap-4">
                        {ncdTabs.map((tab) => (
                            <button
                                key={tab.key}
                                onClick={() => setActiveSubTab(tab.key)}
                                className={`px-2 pb-3 text-sm font-medium transition ${
                                    activeSubTab === tab.key
                                        ? 'text-blue-600 border-b-2 border-blue-600 font-semibold'
                                        : 'text-gray-600 hover:text-blue-600'
                                }`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </nav>
                </div>

                <div className="p-6">
                    {activeSubTab === 'philpenRiskAssessment' && (
                        <PhilPEN rows={philpenRows} onChange={setPhilpenRows} />
                    )}
                    {activeSubTab === 'eyeScreening' && (
                        <EyesScreening rows={eyeScreeningRows} onChange={setEyeScreeningRows} />
                    )}
                    {activeSubTab === 'cervicalCancer' && (
                        <CervicalCancerScreening rows={cervicalCancerRows} onChange={setCervicalCancerRows} />
                    )}
                    {activeSubTab === 'mentalHealth' && (
                        <MentalHealth rows={mentalHealthRows} onChange={setMentalHealthRows} />
                    )}
                </div>
            </div>
        </div>
    );
}