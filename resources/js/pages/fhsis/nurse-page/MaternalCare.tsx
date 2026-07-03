import React, { useMemo, useState } from 'react';

// Mirrors the "TARGET CLIENT LIST FOR MATERNAL CARE AND SERVICES" (TCL_Maternal_8ANC_4PNC)
// worksheet: prenatal care (8 ANC visits), nutritional assessment, tetanus-diphtheria
// immunization, deworming, and IFA/MM/CC supplementation tracking.

export interface MaternalCareClient {
    id: string;
    dateOfRegistration: string; // mm/dd/yy
    familySerialNumber: string;
    fullName: string; // LastName, FullName, MI
    address: string;
    age: number | '';
    ageGroup: 'A' | 'B' | 'C' | ''; // A 10-14, B 15-19, C 20-49
    lmp: string; // Last Menstrual Period
    gravidaParity: string; // G-P
    edd: string; // Expected Date of Delivery
    ancVisitsCompleted: number; // 0-8
    completed8Anc: boolean;
    withHighBp: boolean;
    withDangerSigns: boolean;
    dangerSignsNote: string;
    referred: boolean;
    bmiCategory: 'Low' | 'Normal' | 'High' | '';
    tdDosesGiven: number; // 0-5
    dewormed: boolean;
    ifaTabletsGiven: number;
    ifaCompleted: boolean;
    mmTabletsGiven: number;
    mmCompleted: boolean;
    ccTabletsGiven: number;
    ccCompleted: boolean;
    remarks: string;
}

const emptyForm: Omit<MaternalCareClient, 'id'> = {
    dateOfRegistration: '',
    familySerialNumber: '',
    fullName: '',
    address: '',
    age: '',
    ageGroup: '',
    lmp: '',
    gravidaParity: '',
    edd: '',
    ancVisitsCompleted: 0,
    completed8Anc: false,
    withHighBp: false,
    withDangerSigns: false,
    dangerSignsNote: '',
    referred: false,
    bmiCategory: '',
    tdDosesGiven: 0,
    dewormed: false,
    ifaTabletsGiven: 0,
    ifaCompleted: false,
    mmTabletsGiven: 0,
    mmCompleted: false,
    ccTabletsGiven: 0,
    ccCompleted: false,
    remarks: '',
};

const sampleClients: MaternalCareClient[] = [
    {
        id: 'mc-1',
        dateOfRegistration: '01/15/26',
        familySerialNumber: 'FSN-0231',
        fullName: 'Dela Cruz, Maria Santos',
        address: 'Purok 3, Brgy. San Isidro',
        age: 24,
        ageGroup: 'C',
        lmp: '11/02/25',
        gravidaParity: 'G2P1',
        edd: '08/09/26',
        ancVisitsCompleted: 4,
        completed8Anc: false,
        withHighBp: false,
        withDangerSigns: false,
        dangerSignsNote: '',
        referred: false,
        bmiCategory: 'Normal',
        tdDosesGiven: 2,
        dewormed: true,
        ifaTabletsGiven: 90,
        ifaCompleted: false,
        mmTabletsGiven: 0,
        mmCompleted: false,
        ccTabletsGiven: 60,
        ccCompleted: false,
        remarks: 'On track, next visit 2nd trimester',
    },
];

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium text-gray-700">{label}</span>
            {children}
        </label>
    );
}

const inputClass =
    'rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-pink-500';

export default function MaternalCare({ clients }: { clients?: MaternalCareClient[] }) {
    const [records, setRecords] = useState<MaternalCareClient[]>(clients && clients.length ? clients : sampleClients);
    const [search, setSearch] = useState('');
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState(emptyForm);

    const filtered = useMemo(
        () => records.filter((c) => c.fullName.toLowerCase().includes(search.toLowerCase())),
        [records, search],
    );

    const stats = useMemo(
        () => ({
            total: records.length,
            completedAnc: records.filter((c) => c.completed8Anc).length,
            highBp: records.filter((c) => c.withHighBp).length,
            dangerSigns: records.filter((c) => c.withDangerSigns).length,
        }),
        [records],
    );

    function submit(e: React.FormEvent) {
        e.preventDefault();
        setRecords((prev) => [...prev, { ...form, id: `mc-${prev.length + 1}-${Date.now()}` }]);
        setForm(emptyForm);
        setShowForm(false);
    }

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatCard label="Registered Mothers" value={stats.total} color="pink" />
                <StatCard label="Completed 8 ANC" value={stats.completedAnc} color="green" />
                <StatCard label="Elevated BP" value={stats.highBp} color="amber" />
                <StatCard label="Danger Signs Flagged" value={stats.dangerSigns} color="red" />
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div>
                    <h2 className="text-base font-semibold text-gray-900">Maternal Care and Services</h2>
                    <p className="text-sm text-gray-500">Target Client List — Prenatal Care (8 ANC) &amp; Postnatal Care (4 PNC)</p>
                </div>
                <div className="flex items-center gap-2">
                    <input
                        type="text"
                        placeholder="Search by name..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className={inputClass}
                    />
                    <button
                        onClick={() => setShowForm(true)}
                        className="rounded-md bg-pink-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-pink-700"
                    >
                        + Add Client
                    </button>
                </div>
            </div>

            <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
                <table className="min-w-full divide-y divide-gray-200 text-sm">
                    <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <Th rowSpan={2}>No.</Th>
                            <Th rowSpan={2}>Date Registered</Th>
                            <Th rowSpan={2}>Family Serial No.</Th>
                            <Th rowSpan={2}>Full Name</Th>
                            <Th rowSpan={2}>Address</Th>
                            <Th rowSpan={2}>Age / Group</Th>
                            <Th colSpan={3} className="text-center">
                                Prenatal Care
                            </Th>
                            <Th rowSpan={2}>BMI Category</Th>
                            <Th colSpan={3} className="text-center">
                                Immunization / Safety
                            </Th>
                            <Th colSpan={4} className="text-center">
                                Supplementation (IFA / MM / CC)
                            </Th>
                            <Th rowSpan={2}>Remarks</Th>
                        </tr>
                        <tr>
                            <Th>LMP / G-P</Th>
                            <Th>EDD</Th>
                            <Th>ANC Visits</Th>
                            <Th>Td Doses</Th>
                            <Th>High BP</Th>
                            <Th>Danger Signs</Th>
                            <Th>Deworming</Th>
                            <Th>IFA (given / done)</Th>
                            <Th>MM (given / done)</Th>
                            <Th>CC (given / done)</Th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {filtered.map((c, i) => (
                            <tr key={c.id} className="hover:bg-pink-50/40">
                                <Td>{i + 1}</Td>
                                <Td>{c.dateOfRegistration}</Td>
                                <Td>{c.familySerialNumber}</Td>
                                <Td className="font-medium text-gray-900">{c.fullName}</Td>
                                <Td>{c.address}</Td>
                                <Td>
                                    {c.age} {c.ageGroup && `(${c.ageGroup})`}
                                </Td>
                                <Td>
                                    {c.lmp} {c.gravidaParity}
                                </Td>
                                <Td>{c.edd}</Td>
                                <Td>{c.ancVisitsCompleted}/8 {c.completed8Anc && '✓'}</Td>
                                <Td>{c.bmiCategory}</Td>
                                <Td>{c.tdDosesGiven}/5</Td>
                                <Td>
                                    <Badge on={c.withHighBp} onLabel="Yes" offLabel="No" tone="amber" />
                                </Td>
                                <Td>
                                    <Badge on={c.withDangerSigns} onLabel={c.dangerSignsNote || 'Yes'} offLabel="No" tone="red" />
                                </Td>
                                <Td>{c.dewormed ? '✓' : '—'}</Td>
                                <Td>
                                    {c.ifaTabletsGiven} {c.ifaCompleted && '✓'}
                                </Td>
                                <Td>
                                    {c.mmTabletsGiven} {c.mmCompleted && '✓'}
                                </Td>
                                <Td>
                                    {c.ccTabletsGiven} {c.ccCompleted && '✓'}
                                </Td>
                                <Td className="max-w-[160px] truncate" title={c.remarks}>
                                    {c.remarks}
                                </Td>
                            </tr>
                        ))}
                        {filtered.length === 0 && (
                            <tr>
                                <td colSpan={18} className="px-4 py-6 text-center text-gray-400">
                                    No matching records.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {showForm && (
                <Modal title="Add Maternal Care Client" onClose={() => setShowForm(false)}>
                    <form onSubmit={submit} className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <Field label="Date of Registration">
                            <input className={inputClass} placeholder="mm/dd/yy" value={form.dateOfRegistration} onChange={(e) => setForm({ ...form, dateOfRegistration: e.target.value })} />
                        </Field>
                        <Field label="Family Serial Number">
                            <input className={inputClass} value={form.familySerialNumber} onChange={(e) => setForm({ ...form, familySerialNumber: e.target.value })} />
                        </Field>
                        <Field label="Full Name (Last, First, MI)">
                            <input className={inputClass} required value={form.fullName} onChange={(e) => setForm({ ...form, fullName: e.target.value })} />
                        </Field>
                        <Field label="Complete Address">
                            <input className={inputClass} value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} />
                        </Field>
                        <Field label="Age">
                            <input type="number" className={inputClass} value={form.age} onChange={(e) => setForm({ ...form, age: e.target.value ? Number(e.target.value) : '' })} />
                        </Field>
                        <Field label="Age Group">
                            <select className={inputClass} value={form.ageGroup} onChange={(e) => setForm({ ...form, ageGroup: e.target.value as MaternalCareClient['ageGroup'] })}>
                                <option value="">Select</option>
                                <option value="A">A — 10-14 y/o</option>
                                <option value="B">B — 15-19 y/o</option>
                                <option value="C">C — 20-49 y/o</option>
                            </select>
                        </Field>
                        <Field label="LMP">
                            <input className={inputClass} placeholder="mm/dd/yy" value={form.lmp} onChange={(e) => setForm({ ...form, lmp: e.target.value })} />
                        </Field>
                        <Field label="Gravida-Parity (G-P)">
                            <input className={inputClass} value={form.gravidaParity} onChange={(e) => setForm({ ...form, gravidaParity: e.target.value })} />
                        </Field>
                        <Field label="Expected Date of Delivery">
                            <input className={inputClass} placeholder="mm/dd/yy" value={form.edd} onChange={(e) => setForm({ ...form, edd: e.target.value })} />
                        </Field>
                        <Field label="ANC Visits Completed (0-8)">
                            <input type="number" min={0} max={8} className={inputClass} value={form.ancVisitsCompleted} onChange={(e) => setForm({ ...form, ancVisitsCompleted: Number(e.target.value) })} />
                        </Field>
                        <Field label="BMI Category (1st Trimester)">
                            <select className={inputClass} value={form.bmiCategory} onChange={(e) => setForm({ ...form, bmiCategory: e.target.value as MaternalCareClient['bmiCategory'] })}>
                                <option value="">Select</option>
                                <option value="Low">Low (&lt;18.5)</option>
                                <option value="Normal">Normal (18.5-22.9)</option>
                                <option value="High">High (≥23.0)</option>
                            </select>
                        </Field>
                        <Field label="Td Doses Given (0-5)">
                            <input type="number" min={0} max={5} className={inputClass} value={form.tdDosesGiven} onChange={(e) => setForm({ ...form, tdDosesGiven: Number(e.target.value) })} />
                        </Field>
                        <Field label="IFA Tablets Given">
                            <input type="number" className={inputClass} value={form.ifaTabletsGiven} onChange={(e) => setForm({ ...form, ifaTabletsGiven: Number(e.target.value) })} />
                        </Field>
                        <Field label="MM Tablets Given">
                            <input type="number" className={inputClass} value={form.mmTabletsGiven} onChange={(e) => setForm({ ...form, mmTabletsGiven: Number(e.target.value) })} />
                        </Field>
                        <Field label="CC Tablets Given">
                            <input type="number" className={inputClass} value={form.ccTabletsGiven} onChange={(e) => setForm({ ...form, ccTabletsGiven: Number(e.target.value) })} />
                        </Field>
                        <div className="flex flex-wrap items-center gap-4 sm:col-span-3">
                            <Checkbox label="Completed 8 ANC" checked={form.completed8Anc} onChange={(v) => setForm({ ...form, completed8Anc: v })} />
                            <Checkbox label="With High/Elevated BP" checked={form.withHighBp} onChange={(v) => setForm({ ...form, withHighBp: v })} />
                            <Checkbox label="With Danger Signs" checked={form.withDangerSigns} onChange={(v) => setForm({ ...form, withDangerSigns: v })} />
                            <Checkbox label="Referred" checked={form.referred} onChange={(v) => setForm({ ...form, referred: v })} />
                            <Checkbox label="Dewormed (2nd Tri)" checked={form.dewormed} onChange={(v) => setForm({ ...form, dewormed: v })} />
                            <Checkbox label="IFA Completed" checked={form.ifaCompleted} onChange={(v) => setForm({ ...form, ifaCompleted: v })} />
                            <Checkbox label="MM Completed" checked={form.mmCompleted} onChange={(v) => setForm({ ...form, mmCompleted: v })} />
                            <Checkbox label="CC Completed" checked={form.ccCompleted} onChange={(v) => setForm({ ...form, ccCompleted: v })} />
                        </div>
                        {form.withDangerSigns && (
                            <Field label="Danger Sign(s) Identified">
                                <input className={inputClass} placeholder="e.g. severe headache" value={form.dangerSignsNote} onChange={(e) => setForm({ ...form, dangerSignsNote: e.target.value })} />
                            </Field>
                        )}
                        <div className="sm:col-span-3">
                            <Field label="Remarks / Actions Taken">
                                <textarea className={inputClass} rows={2} value={form.remarks} onChange={(e) => setForm({ ...form, remarks: e.target.value })} />
                            </Field>
                        </div>
                        <div className="flex justify-end gap-2 sm:col-span-3">
                            <button type="button" onClick={() => setShowForm(false)} className="rounded-md border border-gray-300 px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" className="rounded-md bg-pink-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-pink-700">
                                Save Client
                            </button>
                        </div>
                    </form>
                </Modal>
            )}
        </div>
    );
}

function StatCard({ label, value, color }: { label: string; value: number; color: 'pink' | 'green' | 'amber' | 'red' }) {
    const colors: Record<string, string> = {
        pink: 'border-pink-200 bg-pink-50 text-pink-700',
        green: 'border-green-200 bg-green-50 text-green-700',
        amber: 'border-amber-200 bg-amber-50 text-amber-700',
        red: 'border-red-200 bg-red-50 text-red-700',
    };
    return (
        <div className={`rounded-lg border p-4 shadow-sm ${colors[color]}`}>
            <p className="text-xs font-medium uppercase tracking-wide opacity-80">{label}</p>
            <p className="mt-1 text-2xl font-semibold">{value}</p>
        </div>
    );
}

function Th({ children, className = '', colSpan, rowSpan }: { children: React.ReactNode; className?: string; colSpan?: number; rowSpan?: number }) {
    return (
        <th colSpan={colSpan} rowSpan={rowSpan} className={`whitespace-nowrap border-b border-gray-200 px-3 py-2 text-left font-semibold ${className}`}>
            {children}
        </th>
    );
}

function Td({ children, className = '', title }: { children: React.ReactNode; className?: string; title?: string }) {
    return (
        <td title={title} className={`whitespace-nowrap px-3 py-2 text-gray-700 ${className}`}>
            {children}
        </td>
    );
}

function Badge({ on, onLabel, offLabel, tone }: { on: boolean; onLabel: string; offLabel: string; tone: 'amber' | 'red' }) {
    if (!on) return <span className="text-gray-400">{offLabel}</span>;
    const toneClass = tone === 'amber' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800';
    return <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${toneClass}`}>{onLabel}</span>;
}

function Checkbox({ label, checked, onChange }: { label: string; checked: boolean; onChange: (v: boolean) => void }) {
    return (
        <label className="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} className="h-4 w-4 rounded border-gray-300 text-pink-600 focus:ring-pink-500" />
            {label}
        </label>
    );
}

function Modal({ title, children, onClose }: { title: string; children: React.ReactNode; onClose: () => void }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
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
