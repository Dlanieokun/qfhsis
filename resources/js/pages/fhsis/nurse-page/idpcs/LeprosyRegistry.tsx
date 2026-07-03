import React, { useMemo, useState } from 'react';

// Leprosy Registry — mirrors the fields on the Leprosy_Registry.xlsx paper
// form: case detection/classification, MDT treatment, reclassification,
// and outcome/disability tracking.

export type AgeGroup = 'A' | 'B' | 'C';
export type Sex = 'M' | 'F';
export type CaseHistory = 0 | 1 | 2 | 3; // New, Relapse, Defaulter, Transfer-in
export type ClinicalClassification = 1 | 2; // PB, MB
export type TreatmentOutcome = 1 | 2 | 3 | 4 | 5; // Ongoing, Completed, Defaulted, Transferred Out, Died

export interface LeprosyRecord {
    id: string;
    no: number;
    dateOfRegistration: string;
    fullName: string;
    completeAddress: string;
    dateOfBirth: string;
    age: number;
    ageGroup: AgeGroup;
    sex: Sex;
    confirmedCase: { confirmed: boolean; dateOfDiagnosis?: string };
    caseHistory: CaseHistory;
    previousFacility?: string;
    clinicalClassification: ClinicalClassification;
    treatmentStartDate?: string;
    monthsTreatedPrior?: string;
    reclassified?: { done: boolean; date?: string };
    updatedClassification?: ClinicalClassification;
    treatmentOutcome: TreatmentOutcome;
    completedFixedMdt: { done: boolean; dateCompleted?: string };
    beyondFixedMdt?: { done: boolean; dateCompleted?: string };
    withGrade2Disability: boolean;
    remarks?: string;
}

const AGE_GROUP_LABEL: Record<AgeGroup, string> = {
    A: '0–14 yrs',
    B: '15–18 yrs',
    C: '19+ yrs',
};

const CASE_HISTORY_LABEL: Record<CaseHistory, string> = {
    0: 'New',
    1: 'Relapse',
    2: 'Defaulter',
    3: 'Transfer-in',
};

const CLASSIFICATION_LABEL: Record<ClinicalClassification, string> = {
    1: 'Paucibacillary (PB)',
    2: 'Multibacillary (MB)',
};

const OUTCOME_LABEL: Record<TreatmentOutcome, { text: string; tone: string }> = {
    1: { text: 'Ongoing Treatment', tone: 'bg-blue-50 text-blue-700' },
    2: { text: 'Completed Treatment', tone: 'bg-green-50 text-green-700' },
    3: { text: 'Defaulted', tone: 'bg-amber-50 text-amber-700' },
    4: { text: 'Transferred Out', tone: 'bg-gray-100 text-gray-600' },
    5: { text: 'Died', tone: 'bg-red-50 text-red-700' },
};

const mockRecords: LeprosyRecord[] = [
    {
        id: 'lep-0001',
        no: 1,
        dateOfRegistration: '01/06/26',
        fullName: 'Fernandez, Rico B.',
        completeAddress: 'Purok 2, Brgy. Masinag',
        dateOfBirth: '04/17/1985',
        age: 40,
        ageGroup: 'C',
        sex: 'M',
        confirmedCase: { confirmed: true, dateOfDiagnosis: '01/06/26' },
        caseHistory: 0,
        clinicalClassification: 2,
        treatmentStartDate: '01/08/26',
        monthsTreatedPrior: '3 months',
        treatmentOutcome: 1,
        completedFixedMdt: { done: false },
        withGrade2Disability: false,
        remarks: '',
    },
    {
        id: 'lep-0002',
        no: 2,
        dateOfRegistration: '11/02/25',
        fullName: 'Villar, Corazon S.',
        completeAddress: 'Sitio Ilaya, Brgy. Masinag',
        dateOfBirth: '02/28/1962',
        age: 63,
        ageGroup: 'C',
        sex: 'F',
        confirmedCase: { confirmed: true, dateOfDiagnosis: '11/02/25' },
        caseHistory: 0,
        clinicalClassification: 1,
        treatmentStartDate: '11/05/25',
        treatmentOutcome: 2,
        completedFixedMdt: { done: true, dateCompleted: '05/05/26' },
        withGrade2Disability: false,
        remarks: 'Treatment completed, discharged',
    },
];

function OutcomePill({ outcome }: { outcome: TreatmentOutcome }) {
    const { text, tone } = OUTCOME_LABEL[outcome];
    return <span className={`inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ${tone}`}>{text}</span>;
}

function YesNoPill({ value }: { value?: boolean }) {
    if (value === undefined) return <span className="text-gray-300">—</span>;
    return value ? (
        <span className="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Yes</span>
    ) : (
        <span className="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">No</span>
    );
}

export default function LeprosyRegistry({ records = mockRecords }: { records?: LeprosyRecord[] }) {
    const [search, setSearch] = useState('');

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return records;
        return records.filter(
            (r) => r.fullName.toLowerCase().includes(q) || r.completeAddress.toLowerCase().includes(q)
        );
    }, [records, search]);

    const confirmedCount = records.filter((r) => r.confirmedCase.confirmed).length;
    const ongoingCount = records.filter((r) => r.treatmentOutcome === 1).length;
    const disabilityCount = records.filter((r) => r.withGrade2Disability).length;

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 className="text-sm font-semibold text-gray-900">Leprosy Registry</h3>
                    <p className="text-xs text-gray-500">Case detection, classification, MDT treatment, and outcome tracking</p>
                </div>
                <div className="flex items-center gap-2">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search name or address"
                        className="w-64 rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    />
                    <button className="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                        + Add Record
                    </button>
                </div>
            </div>

            <div className="flex flex-wrap gap-3">
                <StatChip label="Total Registered" value={records.length} />
                <StatChip label="Confirmed Cases" value={confirmedCount} tone="red" />
                <StatChip label="Ongoing Treatment" value={ongoingCount} tone="blue" />
                <StatChip label="Grade 2 Disability" value={disabilityCount} tone="amber" />
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
                                <Th>Full Name</Th>
                                <Th>Address</Th>
                                <Th>Age / Group</Th>
                                <Th>Sex</Th>
                                <Th>Confirmed</Th>
                                <Th>Case History</Th>
                                <Th>Classification</Th>
                                <Th>Treatment Start</Th>
                                <Th>Outcome</Th>
                                <Th>Completed MDT</Th>
                                <Th>Grade 2 Disability</Th>
                                <Th>Remarks</Th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 bg-white">
                            {filtered.map((r) => (
                                <tr key={r.id} className="hover:bg-gray-50">
                                    <Td>{r.no}</Td>
                                    <Td>{r.dateOfRegistration}</Td>
                                    <Td className="font-medium text-gray-900">{r.fullName}</Td>
                                    <Td>{r.completeAddress}</Td>
                                    <Td>
                                        {r.age} <span className="text-gray-400">({AGE_GROUP_LABEL[r.ageGroup]})</span>
                                    </Td>
                                    <Td>{r.sex}</Td>
                                    <Td>
                                        <YesNoPill value={r.confirmedCase.confirmed} />
                                    </Td>
                                    <Td>{CASE_HISTORY_LABEL[r.caseHistory]}</Td>
                                    <Td className="whitespace-nowrap">{CLASSIFICATION_LABEL[r.clinicalClassification]}</Td>
                                    <Td>{r.treatmentStartDate || '—'}</Td>
                                    <Td><OutcomePill outcome={r.treatmentOutcome} /></Td>
                                    <Td className="whitespace-nowrap text-xs text-gray-500">
                                        {r.completedFixedMdt.done ? r.completedFixedMdt.dateCompleted || 'Yes' : '—'}
                                    </Td>
                                    <Td><YesNoPill value={r.withGrade2Disability} /></Td>
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
            <p className="text-sm font-semibold text-gray-700">No leprosy records found</p>
            <p className="mt-1 max-w-md text-sm text-gray-500">Try a different search term, or add a new record to get started.</p>
        </div>
    );
}
