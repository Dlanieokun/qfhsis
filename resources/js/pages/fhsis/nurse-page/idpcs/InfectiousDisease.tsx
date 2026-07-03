import React, { useState } from 'react';
import FilariasisRegistry, { FilariasisRecord } from './FilariasisRegistry';
import SchistosomiasisRegistry, { SchistosomiasisRecord } from './SchistosomiasisRegistry';
import SoilTransmittedHelminthiasisRegistry, { SthRecord } from './SoilTransmittedHelminthiasisRegistry';
import LeprosyRegistry, { LeprosyRecord } from './LeprosyRegistry';

// Infectious Disease Prevention and Control Services dashboard.
// Each panel is wired to its own registry Target Client List (Filariasis,
// Schistosomiasis, STH, Leprosy). Previously these were rendered with no
// props at all, so every registry always fell back to its built-in mock
// data even when real rows existed in filariasis_registry_table,
// schistosomiasis_registry, sth_registry_records, and leprosy_registry.

type InfectiousDiseaseTabKey = 'filariasisPrevention' | 'schistosomiasisPrevention' | 'sthPrevention' | 'leprosyPrevention';

interface InfectiousDiseaseTabDefinition {
    key: InfectiousDiseaseTabKey;
    label: string;
}

const infectiousDiseaseTabs: InfectiousDiseaseTabDefinition[] = [
    { key: 'filariasisPrevention', label: 'Filariasis Prevention' },
    { key: 'schistosomiasisPrevention', label: 'Schistosomiasis Prevention' },
    { key: 'sthPrevention', label: 'Soil-Transmitted Helminthiasis Prevention' },
    { key: 'leprosyPrevention', label: 'Leprosy Prevention' },
];

export interface InfectiousDiseaseClient {
    filariasis?: FilariasisRecord[];
    schistosomiasis?: SchistosomiasisRecord[];
    sth?: SthRecord[];
    leprosy?: LeprosyRecord[];
}

export default function InfectiousDisease({ clients }: { clients?: InfectiousDiseaseClient }) {
    const [activeSubTab, setActiveSubTab] = useState<InfectiousDiseaseTabKey>('filariasisPrevention');

    return (
        <div className="space-y-4">
            <div className="rounded-lg border border-gray-200 bg-white shadow-sm">
                <div className="border-b border-gray-200 px-4 pt-3">
                    <h2 className="text-base font-semibold text-gray-900">Infectious Disease Prevention and Control Services</h2>
                    <p className="mb-3 text-sm text-gray-500">Select a service area to view or record client data</p>
                    <nav className="flex flex-wrap gap-4">
                        {infectiousDiseaseTabs.map((tab) => (
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
                    {activeSubTab === 'filariasisPrevention' && (
                        <FilariasisRegistry records={clients?.filariasis} />
                    )}
                    {activeSubTab === 'schistosomiasisPrevention' && (
                        <SchistosomiasisRegistry records={clients?.schistosomiasis} />
                    )}
                    {activeSubTab === 'sthPrevention' && (
                        <SoilTransmittedHelminthiasisRegistry records={clients?.sth} />
                    )}
                    {activeSubTab === 'leprosyPrevention' && (
                        <LeprosyRegistry records={clients?.leprosy} />
                    )}
                </div>
            </div>
        </div>
    );
}