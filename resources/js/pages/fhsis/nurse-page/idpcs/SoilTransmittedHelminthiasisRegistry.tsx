import React, { useMemo, useState } from 'react';

// Soil-Transmitted Helminthiasis (STH) Registry — mirrors the fields on the
// Soil-Transmitted_Helminthiasis_Registry.xlsx paper form: screening,
// treatment, and the January / July mass deworming (MDA) rounds.

export type AgeGroup = 'A' | 'B' | 'C' | 'D' | 'E';
export type Sex = 'M' | 'F';
export type Residency = 1 | 0; // 1 - Resident, 0 - Non-Resident
export type ScreeningResult = 0 | 1 | 2; // 0 - Negative, 1 - Suspected, 2 - Positive
export type Treatment = 0 | 1 | 2; // 0 - None, 1 - Albendazole, 2 - Mebendazole
export type MdaVenue = 1 | 2; // 1 - School-based, 2 - Community-based

export interface SthRecord {
    id: string;
    no: number;
    dateOfRegistration: string;
    familySerialNumber: string;
    patientFullName: string;
    completeAddress: string;
    residency: Residency;
    dateOfBirth: string;
    age: number;
    ageGroup: AgeGroup;
    sex: Sex;
    screened: { done: boolean; dateOfScreening?: string };
    screeningResult?: ScreeningResult;
    dateOfResult?: string;
    treatmentGiven?: { type: Treatment; dateGiven?: string };
    januaryMda?: { date: string; venue: MdaVenue };
    julyMda?: { date: string; venue: MdaVenue };
    remarks?: string;
}

const AGE_GROUP_LABEL: Record<AgeGroup, string> = {
    A: '1–4 yrs',
    B: '5–14 yrs',
    C: '15–19 yrs',
    D: '20–59 yrs',
    E: '60+ yrs',
};

const TREATMENT_LABEL: Record<Treatment, string> = {
    0: 'None',
    1: 'Albendazole',
    2: 'Mebendazole',
};

const MDA_VENUE_LABEL: Record<MdaVenue, string> = {
    1: 'School-based',
    2: 'Community-based',
};

const mockRecords: SthRecord[] = [
    {
        id: 'sth-0001',
        no: 1,
        dateOfRegistration: '01/09/26',
        familySerialNumber: 'FSN-00305',
        patientFullName: 'Garcia, Liza P.',
        completeAddress: 'Purok 5, Brgy. Masinag',
        residency: 1,
        dateOfBirth: '03/11/2018',
        age: 7,
        ageGroup: 'B',
        sex: 'F',
        screened: { done: true, dateOfScreening: '01/09/26' },
        screeningResult: 0,
        dateOfResult: '01/10/26',
        treatmentGiven: { type: 1, dateGiven: '01/12/26' },
        januaryMda: { date: '01/12/26', venue: 1 },
        remarks: '',
    },
    {
        id: 'sth-0002',
        no: 2,
        dateOfRegistration: '01/09/26',
        familySerialNumber: 'FSN-00306',
        patientFullName: 'Ramos, Kyle J.',
        completeAddress: 'Sitio Ilaya, Brgy. Masinag',
        residency: 0,
        dateOfBirth: '09/28/2015',
        age: 10,
        ageGroup: 'B',
        sex: 'M',
        screened: { done: true, dateOfScreening: '01/09/26' },
        screeningResult: 2,
        dateOfResult: '01/10/26',
        treatmentGiven: { type: 1, dateGiven: '01/12/26' },
        januaryMda: { date: '01/12/26', venue: 1 },
        remarks: 'Follow-up stool exam scheduled',
    },
];

function ResultPill({ result }: { result?: ScreeningResult }) {
    if (result === undefined) return <span className="text-gray-300">—</span>;
    const map = {
        0: { text: 'Negative', tone: 'bg-green-50 text-green-700' },
        1: { text: 'Suspected', tone: 'bg-amber-50 text-amber-700' },
        2: { text: 'Positive', tone: 'bg-red-50 text-red-700' },
    } as const;
    const { text, tone } = map[result];
    return <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${tone}`}>{text}</span>;
}

export default function SoilTransmittedHelminthiasisRegistry({ records = mockRecords }: { records?: SthRecord[] }) {
    const [search, setSearch] = useState('');

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return records;
        return records.filter(
            (r) =>
                r.patientFullName.toLowerCase().includes(q) ||
                r.completeAddress.toLowerCase().includes(q) ||
                r.familySerialNumber.toLowerCase().includes(q)
        );
    }, [records, search]);

    const positiveCount = records.filter((r) => r.screeningResult === 2).length;
    const dewormedCount = records.filter((r) => r.januaryMda || r.julyMda).length;

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 className="text-sm font-semibold text-gray-900">Soil-Transmitted Helminthiasis Registry</h3>
                    <p className="text-xs text-gray-500">Screening, treatment, and January / July mass deworming (MDA) tracking</p>
                </div>
                <div className="flex items-center gap-2">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search name, address, or family no."
                        className="w-64 rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    />
                    <button className="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                        + Add Record
                    </button>
                </div>
            </div>

            <div className="flex flex-wrap gap-3">
                <StatChip label="Total Registered" value={records.length} />
                <StatChip label="Positive on Screening" value={positiveCount} tone="red" />
                <StatChip label="Dewormed (MDA)" value={dewormedCount} tone="amber" />
            </div>

            {filtered.length === 0 ? (
                <EmptyState />
            ) : (
                <div className="overflow-x-auto rounded-lg border border-gray-200">
                    <table className="min-w-full divide-y divide-gray-200 text-left text-sm">
                        <thead className="bg-gray-50">
                            <tr>
                                <Th>No.</Th>
                                <Th>Date Registered</Th>
                                <Th>Patient Name</Th>
                                <Th>Address</Th>
                                <Th>Residency</Th>
                                <Th>Age / Group</Th>
                                <Th>Sex</Th>
                                <Th>Screened</Th>
                                <Th>Screening Result</Th>
                                <Th>Treatment Given</Th>
                                <Th>January MDA</Th>
                                <Th>July MDA</Th>
                                <Th>Remarks</Th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 bg-white">
                            {filtered.map((r) => (
                                <tr key={r.id} className="hover:bg-gray-50">
                                    <Td>{r.no}</Td>
                                    <Td>{r.dateOfRegistration}</Td>
                                    <Td className="font-medium text-gray-900">{r.patientFullName}</Td>
                                    <Td>{r.completeAddress}</Td>
                                    <Td>{r.residency === 1 ? 'Resident' : 'Non-Resident'}</Td>
                                    <Td>
                                        {r.age} <span className="text-gray-400">({AGE_GROUP_LABEL[r.ageGroup]})</span>
                                    </Td>
                                    <Td>{r.sex}</Td>
                                    <Td className="whitespace-nowrap text-xs text-gray-500">
                                        {r.screened.done ? r.screened.dateOfScreening || 'Yes' : '—'}
                                    </Td>
                                    <Td><ResultPill result={r.screeningResult} /></Td>
                                    <Td className="whitespace-nowrap text-xs text-gray-500">
                                        {r.treatmentGiven ? `${TREATMENT_LABEL[r.treatmentGiven.type]}${r.treatmentGiven.dateGiven ? ` · ${r.treatmentGiven.dateGiven}` : ''}` : '—'}
                                    </Td>
                                    <Td className="whitespace-nowrap text-xs text-gray-500">
                                        {r.januaryMda ? `${r.januaryMda.date} (${MDA_VENUE_LABEL[r.januaryMda.venue]})` : '—'}
                                    </Td>
                                    <Td className="whitespace-nowrap text-xs text-gray-500">
                                        {r.julyMda ? `${r.julyMda.date} (${MDA_VENUE_LABEL[r.julyMda.venue]})` : '—'}
                                    </Td>
                                    <Td className="max-w-[180px] truncate text-gray-500" title={r.remarks}>
                                        {r.remarks || '—'}
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function Th({ children }: { children: React.ReactNode }) {
    return <th className="whitespace-nowrap px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{children}</th>;
}

function Td({ children, className = '', title }: { children: React.ReactNode; className?: string; title?: string }) {
    return <td className={`whitespace-nowrap px-3 py-2 text-gray-700 ${className}`} title={title}>{children}</td>;
}

function StatChip({ label, value, tone = 'blue' }: { label: string; value: number; tone?: 'blue' | 'red' | 'amber' }) {
    const toneClasses = {
        blue: 'bg-blue-50 text-blue-700',
        red: 'bg-red-50 text-red-700',
        amber: 'bg-amber-50 text-amber-700',
    }[tone];
    return (
        <div className={`rounded-md px-3 py-1.5 text-xs font-medium ${toneClasses}`}>
            {label}: <span className="font-semibold">{value}</span>
        </div>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 px-6 py-12 text-center">
            <p className="text-sm font-semibold text-gray-700">No STH records found</p>
            <p className="mt-1 max-w-md text-sm text-gray-500">Try a different search term, or add a new record to get started.</p>
        </div>
    );
}
