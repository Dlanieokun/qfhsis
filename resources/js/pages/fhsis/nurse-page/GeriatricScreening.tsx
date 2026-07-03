import React, { useMemo, useState } from 'react';

// Mirrors the "TCL_GERIATRICS&IMMU" (Target Client List for Geriatric Screening
// and Senior Citizen Immunization) worksheet: 9-domain geriatric screening
// (memory, depression, polypharmacy, urinary incontinence, functional capacity,
// falls, malnutrition, hearing, vision), care plan/referral, and PPV/Influenza
// immunization tracking.

export type GeriatricDomain = 'A' | 'B' | 'C' | 'D' | 'E' | 'F' | 'G' | 'H' | 'I';

const domainLabels: Record<GeriatricDomain, string> = {
    A: 'Memory',
    B: 'Depression',
    C: 'Polypharmacy',
    D: 'Urinary Incontinence',
    E: 'Functional Capacity',
    F: 'Fall (History & Screening Test)',
    G: 'Malnutrition',
    H: 'Hearing',
    I: 'Vision',
};

export interface GeriatricClient {
    id: string;
    dateOfScreening: string;
    familySerialNumber: string;
    name: string;
    address: string;
    dateOfBirth: string;
    age: number | '';
    sex: 'M' | 'F' | '';
    positiveDomains: GeriatricDomain[]; // positive findings among A-I
    careplanOrReferred: boolean;
    receivedPpvAt60: boolean;
    ppvDateGiven: string;
    influenzaDateGiven: string;
    remarks: string;
}

const emptyForm: Omit<GeriatricClient, 'id'> = {
    dateOfScreening: '',
    familySerialNumber: '',
    name: '',
    address: '',
    dateOfBirth: '',
    age: '',
    sex: '',
    positiveDomains: [],
    careplanOrReferred: false,
    receivedPpvAt60: false,
    ppvDateGiven: '',
    influenzaDateGiven: '',
    remarks: '',
};

const sampleClients: GeriatricClient[] = [
    {
        id: 'ger-1',
        dateOfScreening: '03/12/26',
        familySerialNumber: 'FSN-0512',
        name: 'Santos, Pedro Reyes',
        address: 'Purok 1, Brgy. Poblacion',
        dateOfBirth: '06/21/1958',
        age: 67,
        sex: 'M',
        positiveDomains: ['C', 'H'],
        careplanOrReferred: true,
        receivedPpvAt60: true,
        ppvDateGiven: '06/21/2018',
        influenzaDateGiven: '11/05/25',
        remarks: 'Referred to ENT for hearing evaluation',
    },
];

const inputClass =
    'rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500';

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium text-gray-700">{label}</span>
            {children}
        </label>
    );
}

export default function GeriatricScreening({ clients }: { clients?: GeriatricClient[] }) {
    const [records, setRecords] = useState<GeriatricClient[]>(clients && clients.length ? clients : sampleClients);
    const [search, setSearch] = useState('');
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState(emptyForm);

    const filtered = useMemo(
        () => records.filter((c) => c.name.toLowerCase().includes(search.toLowerCase())),
        [records, search],
    );

    const stats = useMemo(
        () => ({
            total: records.length,
            withFindings: records.filter((c) => c.positiveDomains.length > 0).length,
            referred: records.filter((c) => c.careplanOrReferred).length,
            ppv: records.filter((c) => c.receivedPpvAt60).length,
        }),
        [records],
    );

    function toggleDomain(d: GeriatricDomain) {
        setForm((f) => ({
            ...f,
            positiveDomains: f.positiveDomains.includes(d) ? f.positiveDomains.filter((x) => x !== d) : [...f.positiveDomains, d],
        }));
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        setRecords((prev) => [...prev, { ...form, id: `ger-${prev.length + 1}-${Date.now()}` }]);
        setForm(emptyForm);
        setShowForm(false);
    }

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatCard label="Senior Citizens Screened" value={stats.total} color="violet" />
                <StatCard label="With Positive Findings" value={stats.withFindings} color="amber" />
                <StatCard label="Care Plan / Referred" value={stats.referred} color="sky" />
                <StatCard label="Received PPV" value={stats.ppv} color="green" />
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div>
                    <h2 className="text-base font-semibold text-gray-900">Geriatric Health</h2>
                    <p className="text-sm text-gray-500">Target Client List — Geriatric Screening &amp; Senior Citizen Immunization</p>
                </div>
                <div className="flex items-center gap-2">
                    <input type="text" placeholder="Search by name..." value={search} onChange={(e) => setSearch(e.target.value)} className={inputClass} />
                    <button onClick={() => setShowForm(true)} className="rounded-md bg-violet-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-violet-700">
                        + Add Client
                    </button>
                </div>
            </div>

            <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
                <table className="min-w-full divide-y divide-gray-200 text-sm">
                    <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <Th>No.</Th>
                            <Th>Date Screened</Th>
                            <Th>Family Serial No.</Th>
                            <Th>Name</Th>
                            <Th>Address</Th>
                            <Th>DOB / Age / Sex</Th>
                            <Th>Positive Findings</Th>
                            <Th>Care Plan / Referred</Th>
                            <Th>PPV Received</Th>
                            <Th>PPV Date</Th>
                            <Th>Influenza Date</Th>
                            <Th>Remarks</Th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {filtered.map((c, i) => (
                            <tr key={c.id} className="hover:bg-violet-50/40">
                                <Td>{i + 1}</Td>
                                <Td>{c.dateOfScreening}</Td>
                                <Td>{c.familySerialNumber}</Td>
                                <Td className="font-medium text-gray-900">{c.name}</Td>
                                <Td>{c.address}</Td>
                                <Td>
                                    {c.dateOfBirth} · {c.age} y/o · {c.sex}
                                </Td>
                                <Td className="max-w-[220px]">
                                    {c.positiveDomains.length === 0 ? (
                                        <span className="text-gray-400">None</span>
                                    ) : (
                                        <div className="flex flex-wrap gap-1">
                                            {c.positiveDomains.map((d) => (
                                                <span key={d} title={domainLabels[d]} className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                                    {d}
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                </Td>
                                <Td>{c.careplanOrReferred ? '✓' : '—'}</Td>
                                <Td>{c.receivedPpvAt60 ? '✓' : '—'}</Td>
                                <Td>{c.ppvDateGiven}</Td>
                                <Td>{c.influenzaDateGiven}</Td>
                                <Td className="max-w-[160px] truncate" title={c.remarks}>
                                    {c.remarks}
                                </Td>
                            </tr>
                        ))}
                        {filtered.length === 0 && (
                            <tr>
                                <td colSpan={12} className="px-4 py-6 text-center text-gray-400">
                                    No matching records.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {showForm && (
                <Modal title="Add Geriatric Screening Record" onClose={() => setShowForm(false)}>
                    <form onSubmit={submit} className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <Field label="Date of Screening">
                            <input className={inputClass} placeholder="mm/dd/yy" value={form.dateOfScreening} onChange={(e) => setForm({ ...form, dateOfScreening: e.target.value })} />
                        </Field>
                        <Field label="Family Serial Number">
                            <input className={inputClass} value={form.familySerialNumber} onChange={(e) => setForm({ ...form, familySerialNumber: e.target.value })} />
                        </Field>
                        <Field label="Name (Last, First, MI)">
                            <input className={inputClass} required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
                        </Field>
                        <Field label="Complete Address">
                            <input className={inputClass} value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} />
                        </Field>
                        <Field label="Date of Birth">
                            <input className={inputClass} placeholder="mm/dd/yy" value={form.dateOfBirth} onChange={(e) => setForm({ ...form, dateOfBirth: e.target.value })} />
                        </Field>
                        <Field label="Age">
                            <input type="number" className={inputClass} value={form.age} onChange={(e) => setForm({ ...form, age: e.target.value ? Number(e.target.value) : '' })} />
                        </Field>
                        <Field label="Sex">
                            <select className={inputClass} value={form.sex} onChange={(e) => setForm({ ...form, sex: e.target.value as GeriatricClient['sex'] })}>
                                <option value="">Select</option>
                                <option value="M">Male</option>
                                <option value="F">Female</option>
                            </select>
                        </Field>

                        <div className="rounded-md border border-gray-200 p-3 sm:col-span-3">
                            <p className="mb-2 text-sm font-semibold text-gray-800">Screening Results — check any positive domain</p>
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                {(Object.keys(domainLabels) as GeriatricDomain[]).map((d) => (
                                    <label key={d} className="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" checked={form.positiveDomains.includes(d)} onChange={() => toggleDomain(d)} className="h-4 w-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500" />
                                        {d} — {domainLabels[d]}
                                    </label>
                                ))}
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-4 sm:col-span-3">
                            <Checkbox label="Given individualized care plan / referred" checked={form.careplanOrReferred} onChange={(v) => setForm({ ...form, careplanOrReferred: v })} />
                            <Checkbox label="Received PPV upon reaching 60" checked={form.receivedPpvAt60} onChange={(v) => setForm({ ...form, receivedPpvAt60: v })} />
                        </div>
                        <Field label="PPV Date Given">
                            <input className={inputClass} placeholder="mm/dd/yy" value={form.ppvDateGiven} onChange={(e) => setForm({ ...form, ppvDateGiven: e.target.value })} />
                        </Field>
                        <Field label="Influenza Date Given">
                            <input className={inputClass} placeholder="mm/dd/yy" value={form.influenzaDateGiven} onChange={(e) => setForm({ ...form, influenzaDateGiven: e.target.value })} />
                        </Field>
                        <div className="sm:col-span-3">
                            <Field label="Remarks">
                                <textarea className={inputClass} rows={2} value={form.remarks} onChange={(e) => setForm({ ...form, remarks: e.target.value })} />
                            </Field>
                        </div>

                        <div className="flex justify-end gap-2 sm:col-span-3">
                            <button type="button" onClick={() => setShowForm(false)} className="rounded-md border border-gray-300 px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" className="rounded-md bg-violet-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-violet-700">
                                Save Record
                            </button>
                        </div>
                    </form>
                </Modal>
            )}
        </div>
    );
}

function StatCard({ label, value, color }: { label: string; value: number; color: 'violet' | 'amber' | 'sky' | 'green' }) {
    const colors: Record<string, string> = {
        violet: 'border-violet-200 bg-violet-50 text-violet-700',
        amber: 'border-amber-200 bg-amber-50 text-amber-700',
        sky: 'border-sky-200 bg-sky-50 text-sky-700',
        green: 'border-green-200 bg-green-50 text-green-700',
    };
    return (
        <div className={`rounded-lg border p-4 shadow-sm ${colors[color]}`}>
            <p className="text-xs font-medium uppercase tracking-wide opacity-80">{label}</p>
            <p className="mt-1 text-2xl font-semibold">{value}</p>
        </div>
    );
}

function Th({ children, className = '' }: { children: React.ReactNode; className?: string }) {
    return <th className={`whitespace-nowrap border-b border-gray-200 px-3 py-2 text-left font-semibold ${className}`}>{children}</th>;
}

function Td({ children, className = '', title }: { children: React.ReactNode; className?: string; title?: string }) {
    return (
        <td title={title} className={`whitespace-nowrap px-3 py-2 text-gray-700 ${className}`}>
            {children}
        </td>
    );
}

function Checkbox({ label, checked, onChange }: { label: string; checked: boolean; onChange: (v: boolean) => void }) {
    return (
        <label className="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} className="h-4 w-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500" />
            {label}
        </label>
    );
}

function Modal({ title, children, onClose }: { title: string; children: React.ReactNode; onClose: () => void }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        ✕
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}
