import React, { useState } from 'react';
import ChildImmunization from './ChildImmunization';
import ChildImmunizationSchool from './ChildImmunizationSchool';
import ChildManagementSick from './ChildManagementSick';
import ChildNutrition from './ChildNutrition';
import { TCLRow } from '../TargetClientListTable';

// Child Care Services dashboard. Each sub-service has its own Target Client List
// data table (Immunization, Immunization School, Management of Sick, Nutrition).
//
// Data now flows in from PublicNurseController via Inertia props instead of
// always starting blank, so records already in the database show up here.

type ChildCareTabKey = 'immunization' | 'immunizationSchool' | 'managementOfSick' | 'nutrition';

interface ChildCareTabDefinition {
    key: ChildCareTabKey;
    label: string;
}

const childCareTabs: ChildCareTabDefinition[] = [
    { key: 'immunization', label: 'Immunization' },
    { key: 'immunizationSchool', label: 'Immunization School' },
    { key: 'managementOfSick', label: 'Management of Sick' },
    { key: 'nutrition', label: 'Nutrition' },
];

// Shape passed down from PublicNursePage. Each key corresponds to one of the
// child_* tables created by the migrations.
export interface ChildCareClient {
    immunization?: TCLRow[];
    immunizationSchool?: TCLRow[];
    managementOfSick?: TCLRow[];
    nutrition?: TCLRow[];
}

export default function ChildCare({ clients }: { clients?: ChildCareClient }) {
    const [activeSubTab, setActiveSubTab] = useState<ChildCareTabKey>('immunization');

    // Seed local editable state FROM the database-backed props (falls back to
    // an empty array only when the backend hasn't sent anything for that
    // sub-table yet). Previously these always started as [] regardless of
    // what was passed in, which is why the tables looked disconnected from
    // the database.
    const [immunizationRows, setImmunizationRows] = useState<TCLRow[]>(clients?.immunization ?? []);
    const [immunizationSchoolRows, setImmunizationSchoolRows] = useState<TCLRow[]>(clients?.immunizationSchool ?? []);
    const [managementOfSickRows, setManagementOfSickRows] = useState<TCLRow[]>(clients?.managementOfSick ?? []);
    const [nutritionRows, setNutritionRows] = useState<TCLRow[]>(clients?.nutrition ?? []);

    return (
        <div className="space-y-4">
            <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                <div className="border-b border-gray-200 px-4 pt-3">
                    <h2 className="text-base font-semibold text-gray-900">Child Care Services</h2>
                    <p className="mb-3 text-sm text-gray-500">Select a service area to view or record client data</p>
                    <nav className="flex flex-wrap gap-4">
                        {childCareTabs.map((tab) => (
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
                    {activeSubTab === 'immunization' && (
                        <ChildImmunization rows={immunizationRows} onChange={setImmunizationRows} />
                    )}
                    {activeSubTab === 'immunizationSchool' && (
                        <ChildImmunizationSchool
                            rows={immunizationSchoolRows}
                            onChange={setImmunizationSchoolRows}
                        />
                    )}
                    {activeSubTab === 'managementOfSick' && (
                        <ChildManagementSick rows={managementOfSickRows} onChange={setManagementOfSickRows} />
                    )}
                    {activeSubTab === 'nutrition' && (
                        <ChildNutrition rows={nutritionRows} onChange={setNutritionRows} />
                    )}
                </div>
            </div>
        </div>
    );
}