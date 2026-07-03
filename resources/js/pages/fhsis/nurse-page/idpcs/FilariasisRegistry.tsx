import React, { useMemo, useState } from 'react';

// Filariasis Registry — mirrors the fields on the Filariasis_Registry.xlsx
// paper form: registration, blood test result, chronic manifestations
// (lymphedema / elephantiasis / hydrocele), and drugs given.

export type AgeGroup = 'A' | 'B' | 'C';
export type Sex = 'M' | 'F';
export type YesNo = 1 | 2; // 1 - Yes, 2 - No
export type TestResult = 1 | 2; // 1 - positive, 2 - negative

export interface FilariasisRecord {
    id: string;
    no: number;
    dateOfRegistration: string; // mm/dd/yy
    familySerialNumber: string;
    patientFullName: string; // LastName, FirstName MI
    completeAddress: string;
    dateOfBirth: string;
    age: number;
    ageGroup: AgeGroup;
    sex: Sex;
    bloodTest: {
        typeNBE: boolean;
        typeRDT: boolean;
        dateOfTest: string;
        result?: TestResult;
    };
    chronicManifestations: {
        lymphedemaExamined?: YesNo;
        lymphedema?: boolean;
        elephantiasisExamined?: YesNo;
        elephantiasis?: boolean;
        hydroceleExamined?: YesNo;
        hydrocele?: boolean; // male only
    };
    drugsGiven: {
        albendazoleDate?: string;
        decDate?: string;
        ivermectinDate?: string;
    };
    remarks?: string;
}

const AGE_GROUP_LABEL: Record<AgeGroup, string> = {
    A: '2–4 yrs',
    B: '5–14 yrs',
    C: '15+ yrs',
};

const mockRecords: FilariasisRecord[] = [
    {
        id: 'flr-0001',
        no: 1,
        dateOfRegistration: '01/14/26',
        familySerialNumber: 'FSN-00231',
        patientFullName: 'Santos, Maria D.',
        completeAddress: 'Purok 3, Brgy. Masinag',
        dateOfBirth: '05/02/1990',
        age: 35,
        ageGroup: 'C',
        sex: 'F',
        bloodTest: { typeNBE: true, typeRDT: false, dateOfTest: '01/14/26', result: 2 },
        chronicManifestations: {
            lymphedemaExamined: 2,
            elephantiasisExamined: 2,
            hydroceleExamined: 2,
        },
        drugsGiven: { albendazoleDate: '01/14/26', decDate: '01/14/26' },
        remarks: '',
    },
    {
        id: 'flr-0002',
        no: 2,
        dateOfRegistration: '01/20/26',
        familySerialNumber: 'FSN-00232',
        patientFullName: 'Reyes, Pedro M.',
        completeAddress: 'Sitio Ilaya, Brgy. Masinag',
        dateOfBirth: '08/19/1958',
        age: 67,
        ageGroup: 'C',
        sex: 'M',
        bloodTest: { typeNBE: false, typeRDT: true, dateOfTest: '01/20/26', result: 1 },
        chronicManifestations: {
            lymphedemaExamined: 1,
            lymphedema: true,
            elephantiasisExamined: 2,
            hydroceleExamined: 1,
            hydrocele: false,
        },
        drugsGiven: { albendazoleDate: '01/21/26', decDate: '01/21/26', ivermectinDate: '01/21/26' },
        remarks: 'Referred to RHU for lymphedema management',
    },
];

function YesNoBadge({ value, yesLabel = 'Yes', noLabel = 'No' }: { value?: boolean; yesLabel?: string; noLabel?: string }) {
    if (value === undefined) return <span className="text-gray-300">—</span>;
    return (
        <span
            className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
                value ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-500'
            }`}
        >
            {value ? yesLabel : noLabel}
        </span>
    );
}

function ResultBadge({ result }: { result?: TestResult }) {
    if (!result) return <span className="text-gray-300">—</span>;
    return result === 1 ? (
        <span className="inline-flex rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Positive</span>
    ) : (
        <span className="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Negative</span>
    );
}

export default function FilariasisRegistry({ records = mockRecords }: { records?: FilariasisRecord[] }) {
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

    const positiveCount = records.filter((r) => r.bloodTest.result === 1).length;

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 className="text-sm font-semibold text-gray-900">Filariasis Registry</h3>
                    <p className="text-xs text-gray-500">Mass drug administration and case tracking for lymphatic filariasis</p>
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
                <StatChip label="Blood Test Positive" value={positiveCount} tone="red" />
                <StatChip label="With Chronic Manifestation" value={records.filter((r) => r.chronicManifestations.lymphedema || r.chronicManifestations.elephantiasis || r.chronicManifestations.hydrocele).length} tone="amber" />
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
                                <Th>Family Serial No.</Th>
                                <Th>Patient Name</Th>
                                <Th>Address</Th>
                                <Th>Age / Group</Th>
                                <Th>Sex</Th>
                                <Th>Blood Test</Th>
                                <Th>Result</Th>
                                <Th>Lymphedema</Th>
                                <Th>Elephantiasis</Th>
                                <Th>Hydrocele</Th>
                                <Th>Drugs Given</Th>
                                <Th>Remarks</Th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 bg-white">
                            {filtered.map((r) => (
                                <tr key={r.id} className="hover:bg-gray-50">
                                    <Td>{r.no}</Td>
                                    <Td>{r.dateOfRegistration}</Td>
                                    <Td>{r.familySerialNumber}</Td>
                                    <Td className="font-medium text-gray-900">{r.patientFullName}</Td>
                                    <Td>{r.completeAddress}</Td>
                                    <Td>
                                        {r.age} <span className="text-gray-400">({AGE_GROUP_LABEL[r.ageGroup]})</span>
                                    </Td>
                                    <Td>{r.sex}</Td>
                                    <Td>{r.bloodTest.typeNBE ? 'NBE' : r.bloodTest.typeRDT ? 'RDT' : '—'}</Td>
                                    <Td><ResultBadge result={r.bloodTest.result} /></Td>
                                    <Td><YesNoBadge value={r.chronicManifestations.lymphedema} /></Td>
                                    <Td><YesNoBadge value={r.chronicManifestations.elephantiasis} /></Td>
                                    <Td><YesNoBadge value={r.sex === 'M' ? r.chronicManifestations.hydrocele : undefined} /></Td>
                                    <Td className="whitespace-nowrap text-xs text-gray-500">
                                        {[
                                            r.drugsGiven.albendazoleDate && `Albendazole ${r.drugsGiven.albendazoleDate}`,
                                            r.drugsGiven.decDate && `DEC ${r.drugsGiven.decDate}`,
                                            r.drugsGiven.ivermectinDate && `Ivermectin ${r.drugsGiven.ivermectinDate}`,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ') || '—'}
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
            <p className="text-sm font-semibold text-gray-700">No filariasis records found</p>
            <p className="mt-1 max-w-md text-sm text-gray-500">Try a different search term, or add a new record to get started.</p>
        </div>
    );
}
