import React, { useMemo, useState } from 'react';

// Schistosomiasis Registry — mirrors the fields on the
// Schistosomiasis_Registry.xlsx paper form: screening, suspected/clinical
// case treatment, confirmed case work-up, and MDA (Praziquantel) coverage.

export type AgeGroup = 'A' | 'B' | 'C' | 'D' | 'E';
export type Sex = 'M' | 'F';
export type Residency = 1 | 2; // 1 - Resident, 2 - Non-Resident

export interface SchistosomiasisRecord {
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
    historyOfTravelExposure: boolean;
    screened: { done: boolean; dateScreened?: string };
    suspectedCase: {
        withSignsSymptoms?: boolean;
        treated?: { done: boolean; dateStarted?: string };
        retreatment?: { done: boolean; date?: string };
        cured?: { done: boolean; date?: string };
    };
    confirmedCase: {
        diagnosticTest?: string;
        dateOfDiagnosis?: string;
        result?: 'positive' | 'negative';
        dateConfirmed?: string;
        complicated?: boolean;
        treated?: { done: boolean; dateStarted?: string };
        retreatment?: { done: boolean; date?: string };
        cured?: { done: boolean; date?: string };
    };
    dateReferredToHospital?: string;
    mdaPraziquantelGiven: { done: boolean; date?: string };
    remarks?: string;
}

const AGE_GROUP_LABEL: Record<AgeGroup, string> = {
    A: '1–4 yrs',
    B: '5–14 yrs',
    C: '15–19 yrs',
    D: '20–59 yrs',
    E: '60+ yrs',
};

const mockRecords: SchistosomiasisRecord[] = [
    {
        id: 'sch-0001',
        no: 1,
        dateOfRegistration: '11/27/25',
        familySerialNumber: 'FSN-00118',
        patientFullName: 'dela Cruz, Juan',
        completeAddress: 'Barangay Masinag',
        residency: 1,
        dateOfBirth: '01/15/1996',
        age: 29,
        ageGroup: 'D',
        sex: 'M',
        historyOfTravelExposure: true,
        screened: { done: true, dateScreened: '11/27/25' },
        suspectedCase: {
            withSignsSymptoms: true,
            treated: { done: true, dateStarted: '11/27/25' },
        },
        confirmedCase: {
            result: undefined,
            complicated: false,
        },
        mdaPraziquantelGiven: { done: true, date: '11/27/25' },
        remarks: '',
    },
    {
        id: 'sch-0002',
        no: 2,
        dateOfRegistration: '12/03/25',
        familySerialNumber: 'FSN-00119',
        patientFullName: 'Lopez, Ana T.',
        completeAddress: 'Sitio Ilaya, Brgy. Masinag',
        residency: 1,
        dateOfBirth: '06/22/1970',
        age: 55,
        ageGroup: 'D',
        sex: 'F',
        historyOfTravelExposure: false,
        screened: { done: true, dateScreened: '12/03/25' },
        suspectedCase: {},
        confirmedCase: {
            diagnosticTest: 'Kato-Katz',
            dateOfDiagnosis: '12/04/25',
            result: 'positive',
            dateConfirmed: '12/05/25',
            complicated: false,
            treated: { done: true, dateStarted: '12/06/25' },
            cured: { done: true, date: '01/06/26' },
        },
        mdaPraziquantelGiven: { done: true, date: '12/06/25' },
        remarks: 'Cured on follow-up',
    },
];

function Pill({ text, tone }: { text: string; tone: 'green' | 'red' | 'amber' | 'gray' }) {
    const toneClasses = {
        green: 'bg-green-50 text-green-700',
        red: 'bg-red-50 text-red-700',
        amber: 'bg-amber-50 text-amber-700',
        gray: 'bg-gray-100 text-gray-500',
    }[tone];
    return <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${toneClasses}`}>{text}</span>;
}

function StatusPill({ value }: { value?: boolean }) {
    if (value === undefined) return <span className="text-gray-300">—</span>;
    return value ? <Pill text="Yes" tone="amber" /> : <Pill text="No" tone="gray" />;
}

export default function SchistosomiasisRegistry({ records = mockRecords }: { records?: SchistosomiasisRecord[] }) {
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

    const confirmedCount = records.filter((r) => r.confirmedCase.result === 'positive').length;
    const mdaCount = records.filter((r) => r.mdaPraziquantelGiven.done).length;

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 className="text-sm font-semibold text-gray-900">Schistosomiasis Registry</h3>
                    <p className="text-xs text-gray-500">Screening, case work-up, and MDA (Praziquantel) tracking for schistosomiasis</p>
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
                <StatChip label="Confirmed Positive" value={confirmedCount} tone="red" />
                <StatChip label="Given Praziquantel (MDA)" value={mdaCount} tone="amber" />
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
                                <Th>Travel/Exposure Hx</Th>
                                <Th>Screened</Th>
                                <Th>Suspected Case Treated</Th>
                                <Th>Diagnostic Result</Th>
                                <Th>Complicated</Th>
                                <Th>Confirmed Case Treated</Th>
                                <Th>Cured</Th>
                                <Th>MDA (Praziquantel)</Th>
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
                                    <Td><StatusPill value={r.historyOfTravelExposure} /></Td>
                                    <Td className="whitespace-nowrap text-xs text-gray-500">
                                        {r.screened.done ? r.screened.dateScreened || 'Yes' : '—'}
                                    </Td>
                                    <Td><StatusPill value={r.suspectedCase.treated?.done} /></Td>
                                    <Td>
                                        {r.confirmedCase.result === 'positive' ? (
                                            <Pill text="Positive" tone="red" />
                                        ) : r.confirmedCase.result === 'negative' ? (
                                            <Pill text="Negative" tone="green" />
                                        ) : (
                                            <span className="text-gray-300">—</span>
                                        )}
                                    </Td>
                                    <Td><StatusPill value={r.confirmedCase.complicated} /></Td>
                                    <Td><StatusPill value={r.confirmedCase.treated?.done} /></Td>
                                    <Td>
                                        <StatusPill value={r.suspectedCase.cured?.done ?? r.confirmedCase.cured?.done} />
                                    </Td>
                                    <Td className="whitespace-nowrap text-xs text-gray-500">
                                        {r.mdaPraziquantelGiven.done ? r.mdaPraziquantelGiven.date || 'Yes' : '—'}
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
            <p className="text-sm font-semibold text-gray-700">No schistosomiasis records found</p>
            <p className="mt-1 max-w-md text-sm text-gray-500">Try a different search term, or add a new record to get started.</p>
        </div>
    );
}
