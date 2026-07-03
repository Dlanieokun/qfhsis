import React, { useMemo, useState } from 'react';

// Mirrors the "TCL_OHC" (Target Client List for Oral Health Care) worksheet:
// Routine Preventive Oral Care (RPOC) for 0-11 months, and RPOC for 1 year old
// and above / pregnant clients (oral screening, risk assessment, oral prophylaxis,
// fluoride varnish, counseling — each with up to 2 visits).

export type AgeGroupOHC = 'A' | 'B' | 'C' | 'D' | 'E' | 'F' | 'G' | 'H' | '';

export interface OralHealthClient {
    id: string;
    dateOfVisit: string;
    familySerialNumber: string;
    fullName: string;
    address: string;
    dateOfBirth: string;
    ageMonths: number | '';
    ageYears: number | '';
    sex: 'M' | 'F' | '';
    ageGroup: AgeGroupOHC;
    // 0-11 months RPOC (single visit, checkboxes)
    infantOralScreening: boolean;
    infantRiskAssessment: boolean;
    infantOhi: boolean;
    infantCounseling: boolean;
    infantFluorideVarnish: boolean;
    infantRpocComplete: boolean;
    // 1yr+ / pregnant RPOC (up to 2 visits per service)
    oralScreening1st: string; // date
    oralScreening2nd: string;
    riskAssessment1st: string;
    riskAssessment2nd: string;
    oralProphylaxis1st: string;
    oralProphylaxis2nd: string;
    fluorideVarnish1st: string;
    fluorideVarnish2nd: string;
    counseling1st: string;
    counseling2nd: string;
    rpocVisit1Complete: boolean;
    rpocVisit2Complete: boolean;
    serviceLocation: 'A' | 'B' | ''; // A - Facility, B - Non-Facility
    remarks: string;
}

const emptyForm: Omit<OralHealthClient, 'id'> = {
    dateOfVisit: '',
    familySerialNumber: '',
    fullName: '',
    address: '',
    dateOfBirth: '',
    ageMonths: '',
    ageYears: '',
    sex: '',
    ageGroup: '',
    infantOralScreening: false,
    infantRiskAssessment: false,
    infantOhi: false,
    infantCounseling: false,
    infantFluorideVarnish: false,
    infantRpocComplete: false,
    oralScreening1st: '',
    oralScreening2nd: '',
    riskAssessment1st: '',
    riskAssessment2nd: '',
    oralProphylaxis1st: '',
    oralProphylaxis2nd: '',
    fluorideVarnish1st: '',
    fluorideVarnish2nd: '',
    counseling1st: '',
    counseling2nd: '',
    rpocVisit1Complete: false,
    rpocVisit2Complete: false,
    serviceLocation: '',
    remarks: '',
};

const ageGroupLabels: Record<string, string> = {
    A: 'A — 1-4 y/o',
    B: 'B — 5-9 y/o',
    C: 'C — 10-19 y/o',
    D: 'D — 20-59 y/o',
    E: 'E — >60 y/o',
    F: 'F — Pregnant 10-14',
    G: 'G — Pregnant 15-19',
    H: 'H — Pregnant 20-49',
};

const sampleClients: OralHealthClient[] = [
    {
        id: 'oh-1',
        dateOfVisit: '02/03/26',
        familySerialNumber: 'FSN-0110',
        fullName: 'Ramos, Josefina T.',
        address: 'Purok 5, Brgy. Mabini',
        dateOfBirth: '05/10/2000',
        ageMonths: '',
        ageYears: 25,
        sex: 'F',
        ageGroup: 'D',
        infantOralScreening: false,
        infantRiskAssessment: false,
        infantOhi: false,
        infantCounseling: false,
        infantFluorideVarnish: false,
        infantRpocComplete: false,
        oralScreening1st: '02/03/26',
        oralScreening2nd: '',
        riskAssessment1st: '02/03/26',
        riskAssessment2nd: '',
        oralProphylaxis1st: '',
        oralProphylaxis2nd: '',
        fluorideVarnish1st: '',
        fluorideVarnish2nd: '',
        counseling1st: '02/03/26',
        counseling2nd: '',
        rpocVisit1Complete: true,
        rpocVisit2Complete: false,
        serviceLocation: 'A',
        remarks: 'Advised to return for 2nd visit',
    },
];

const inputClass =
    'rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500';

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium text-gray-700">{label}</span>
            {children}
        </label>
    );
}

export default function OralHealthCare({ clients }: { clients?: OralHealthClient[] }) {
    const [records, setRecords] = useState<OralHealthClient[]>(clients && clients.length ? clients : sampleClients);
    const [search, setSearch] = useState('');
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState(emptyForm);
    const isInfant = form.ageGroup === '' && form.ageMonths !== '';

    const filtered = useMemo(
        () => records.filter((c) => c.fullName.toLowerCase().includes(search.toLowerCase())),
        [records, search],
    );

    const stats = useMemo(
        () => ({
            total: records.length,
            infantRpoc: records.filter((c) => c.infantRpocComplete).length,
            visit1: records.filter((c) => c.rpocVisit1Complete).length,
            visit2: records.filter((c) => c.rpocVisit2Complete).length,
        }),
        [records],
    );

    function submit(e: React.FormEvent) {
        e.preventDefault();
        setRecords((prev) => [...prev, { ...form, id: `oh-${prev.length + 1}-${Date.now()}` }]);
        setForm(emptyForm);
        setShowForm(false);
    }

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatCard label="Total Clients" value={stats.total} color="teal" />
                <StatCard label="Infant RPOC Complete" value={stats.infantRpoc} color="green" />
                <StatCard label="1st RPOC Visit Complete" value={stats.visit1} color="sky" />
                <StatCard label="2nd RPOC Visit Complete" value={stats.visit2} color="indigo" />
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div>
                    <h2 className="text-base font-semibold text-gray-900">Oral Health Care and Services</h2>
                    <p className="text-sm text-gray-500">Target Client List — Routine Preventive Oral Care (RPOC)</p>
                </div>
                <div className="flex items-center gap-2">
                    <input type="text" placeholder="Search by name..." value={search} onChange={(e) => setSearch(e.target.value)} className={inputClass} />
                    <button onClick={() => setShowForm(true)} className="rounded-md bg-teal-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-teal-700">
                        + Add Client
                    </button>
                </div>
            </div>

            <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
                <table className="min-w-full divide-y divide-gray-200 text-sm">
                    <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <Th rowSpan={2}>No.</Th>
                            <Th rowSpan={2}>Date of Visit</Th>
                            <Th rowSpan={2}>Family Serial No.</Th>
                            <Th rowSpan={2}>Name</Th>
                            <Th rowSpan={2}>Address</Th>
                            <Th rowSpan={2}>DOB</Th>
                            <Th rowSpan={2}>Sex</Th>
                            <Th rowSpan={2}>Age / Group</Th>
                            <Th colSpan={2} className="text-center">
                                RPOC 0-11 months
                            </Th>
                            <Th colSpan={2} className="text-center">
                                RPOC 1yr+ / Pregnant
                            </Th>
                            <Th rowSpan={2}>Service Location</Th>
                            <Th rowSpan={2}>Remarks</Th>
                        </tr>
                        <tr>
                            <Th>Services Done</Th>
                            <Th>Complete</Th>
                            <Th>Visit 1 (Screen/Risk/Prophy/FV/Couns)</Th>
                            <Th>Visit 2</Th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {filtered.map((c, i) => (
                            <tr key={c.id} className="hover:bg-teal-50/40">
                                <Td>{i + 1}</Td>
                                <Td>{c.dateOfVisit}</Td>
                                <Td>{c.familySerialNumber}</Td>
                                <Td className="font-medium text-gray-900">{c.fullName}</Td>
                                <Td>{c.address}</Td>
                                <Td>{c.dateOfBirth}</Td>
                                <Td>{c.sex}</Td>
                                <Td>
                                    {c.ageMonths !== '' ? `${c.ageMonths} mo` : c.ageYears !== '' ? `${c.ageYears} y` : ''}{' '}
                                    {c.ageGroup && `(${c.ageGroup})`}
                                </Td>
                                <Td>
                                    {[
                                        c.infantOralScreening && 'OS',
                                        c.infantRiskAssessment && 'RA',
                                        c.infantOhi && 'OHI',
                                        c.infantCounseling && 'C',
                                        c.infantFluorideVarnish && 'FV',
                                    ]
                                        .filter(Boolean)
                                        .join(', ') || '—'}
                                </Td>
                                <Td>{c.infantRpocComplete ? '✓' : '—'}</Td>
                                <Td className="max-w-[160px] truncate" title={`Screen ${c.oralScreening1st} · Risk ${c.riskAssessment1st} · Prophy ${c.oralProphylaxis1st} · FV ${c.fluorideVarnish1st} · Couns ${c.counseling1st}`}>
                                    {c.rpocVisit1Complete ? '✓ Complete' : c.oralScreening1st || '—'}
                                </Td>
                                <Td className="max-w-[160px] truncate" title={`Screen ${c.oralScreening2nd} · Risk ${c.riskAssessment2nd} · Prophy ${c.oralProphylaxis2nd} · FV ${c.fluorideVarnish2nd} · Couns ${c.counseling2nd}`}>
                                    {c.rpocVisit2Complete ? '✓ Complete' : c.oralScreening2nd || '—'}
                                </Td>
                                <Td>{c.serviceLocation === 'A' ? 'Facility' : c.serviceLocation === 'B' ? 'Non-Facility' : ''}</Td>
                                <Td className="max-w-[160px] truncate" title={c.remarks}>
                                    {c.remarks}
                                </Td>
                            </tr>
                        ))}
                        {filtered.length === 0 && (
                            <tr>
                                <td colSpan={13} className="px-4 py-6 text-center text-gray-400">
                                    No matching records.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {showForm && (
                <Modal title="Add Oral Health Client" onClose={() => setShowForm(false)}>
                    <form onSubmit={submit} className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <Field label="Date of Visit">
                            <input className={inputClass} placeholder="mm/dd/yy" value={form.dateOfVisit} onChange={(e) => setForm({ ...form, dateOfVisit: e.target.value })} />
                        </Field>
                        <Field label="Family Serial Number">
                            <input className={inputClass} value={form.familySerialNumber} onChange={(e) => setForm({ ...form, familySerialNumber: e.target.value })} />
                        </Field>
                        <Field label="Name (Last, First, MI)">
                            <input className={inputClass} required value={form.fullName} onChange={(e) => setForm({ ...form, fullName: e.target.value })} />
                        </Field>
                        <Field label="Complete Address">
                            <input className={inputClass} value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} />
                        </Field>
                        <Field label="Date of Birth">
                            <input className={inputClass} placeholder="mm/dd/yy" value={form.dateOfBirth} onChange={(e) => setForm({ ...form, dateOfBirth: e.target.value })} />
                        </Field>
                        <Field label="Sex">
                            <select className={inputClass} value={form.sex} onChange={(e) => setForm({ ...form, sex: e.target.value as OralHealthClient['sex'] })}>
                                <option value="">Select</option>
                                <option value="M">Male</option>
                                <option value="F">Female</option>
                            </select>
                        </Field>
                        <Field label="Age (months, if 0-11mo)">
                            <input type="number" className={inputClass} value={form.ageMonths} onChange={(e) => setForm({ ...form, ageMonths: e.target.value ? Number(e.target.value) : '' })} />
                        </Field>
                        <Field label="Age (years, if 1yr+)">
                            <input type="number" className={inputClass} value={form.ageYears} onChange={(e) => setForm({ ...form, ageYears: e.target.value ? Number(e.target.value) : '' })} />
                        </Field>
                        <Field label="Age Group">
                            <select className={inputClass} value={form.ageGroup} onChange={(e) => setForm({ ...form, ageGroup: e.target.value as AgeGroupOHC })}>
                                <option value="">Select</option>
                                {Object.entries(ageGroupLabels).map(([k, v]) => (
                                    <option key={k} value={k}>
                                        {v}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <div className="rounded-md border border-gray-200 p-3 sm:col-span-3">
                            <p className="mb-2 text-sm font-semibold text-gray-800">RPOC for 0-11 months</p>
                            <div className="flex flex-wrap gap-4">
                                <Checkbox label="Oral Screening" checked={form.infantOralScreening} onChange={(v) => setForm({ ...form, infantOralScreening: v })} />
                                <Checkbox label="Risk Assessment" checked={form.infantRiskAssessment} onChange={(v) => setForm({ ...form, infantRiskAssessment: v })} />
                                <Checkbox label="Oral Hygiene Instruction" checked={form.infantOhi} onChange={(v) => setForm({ ...form, infantOhi: v })} />
                                <Checkbox label="Counseling" checked={form.infantCounseling} onChange={(v) => setForm({ ...form, infantCounseling: v })} />
                                <Checkbox label="Fluoride Varnish (9-11mo)" checked={form.infantFluorideVarnish} onChange={(v) => setForm({ ...form, infantFluorideVarnish: v })} />
                                <Checkbox label="RPOC Complete" checked={form.infantRpocComplete} onChange={(v) => setForm({ ...form, infantRpocComplete: v })} />
                            </div>
                        </div>

                        <div className="rounded-md border border-gray-200 p-3 sm:col-span-3">
                            <p className="mb-2 text-sm font-semibold text-gray-800">RPOC for 1 year old and above / Pregnant</p>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <Field label="Oral Screening (1st / 2nd)">
                                    <div className="flex gap-2">
                                        <input className={inputClass} placeholder="1st mm/dd/yy" value={form.oralScreening1st} onChange={(e) => setForm({ ...form, oralScreening1st: e.target.value })} />
                                        <input className={inputClass} placeholder="2nd mm/dd/yy" value={form.oralScreening2nd} onChange={(e) => setForm({ ...form, oralScreening2nd: e.target.value })} />
                                    </div>
                                </Field>
                                <Field label="Risk Assessment (1st / 2nd)">
                                    <div className="flex gap-2">
                                        <input className={inputClass} placeholder="1st mm/dd/yy" value={form.riskAssessment1st} onChange={(e) => setForm({ ...form, riskAssessment1st: e.target.value })} />
                                        <input className={inputClass} placeholder="2nd mm/dd/yy" value={form.riskAssessment2nd} onChange={(e) => setForm({ ...form, riskAssessment2nd: e.target.value })} />
                                    </div>
                                </Field>
                                <Field label="Oral Prophylaxis (1st / 2nd)">
                                    <div className="flex gap-2">
                                        <input className={inputClass} placeholder="1st mm/dd/yy" value={form.oralProphylaxis1st} onChange={(e) => setForm({ ...form, oralProphylaxis1st: e.target.value })} />
                                        <input className={inputClass} placeholder="2nd mm/dd/yy" value={form.oralProphylaxis2nd} onChange={(e) => setForm({ ...form, oralProphylaxis2nd: e.target.value })} />
                                    </div>
                                </Field>
                                <Field label="Fluoride Varnish (1st / 2nd)">
                                    <div className="flex gap-2">
                                        <input className={inputClass} placeholder="1st mm/dd/yy" value={form.fluorideVarnish1st} onChange={(e) => setForm({ ...form, fluorideVarnish1st: e.target.value })} />
                                        <input className={inputClass} placeholder="2nd mm/dd/yy" value={form.fluorideVarnish2nd} onChange={(e) => setForm({ ...form, fluorideVarnish2nd: e.target.value })} />
                                    </div>
                                </Field>
                                <Field label="Counseling (1st / 2nd)">
                                    <div className="flex gap-2">
                                        <input className={inputClass} placeholder="1st mm/dd/yy" value={form.counseling1st} onChange={(e) => setForm({ ...form, counseling1st: e.target.value })} />
                                        <input className={inputClass} placeholder="2nd mm/dd/yy" value={form.counseling2nd} onChange={(e) => setForm({ ...form, counseling2nd: e.target.value })} />
                                    </div>
                                </Field>
                            </div>
                            <div className="mt-3 flex flex-wrap gap-4">
                                <Checkbox label="1st Visit RPOC Complete" checked={form.rpocVisit1Complete} onChange={(v) => setForm({ ...form, rpocVisit1Complete: v })} />
                                <Checkbox label="2nd Visit RPOC Complete" checked={form.rpocVisit2Complete} onChange={(v) => setForm({ ...form, rpocVisit2Complete: v })} />
                            </div>
                        </div>

                        <Field label="Service Location">
                            <select className={inputClass} value={form.serviceLocation} onChange={(e) => setForm({ ...form, serviceLocation: e.target.value as OralHealthClient['serviceLocation'] })}>
                                <option value="">Select</option>
                                <option value="A">A — Facility</option>
                                <option value="B">B — Non-Facility</option>
                            </select>
                        </Field>
                        <div className="sm:col-span-2">
                            <Field label="Remarks">
                                <textarea className={inputClass} rows={2} value={form.remarks} onChange={(e) => setForm({ ...form, remarks: e.target.value })} />
                            </Field>
                        </div>

                        <div className="flex justify-end gap-2 sm:col-span-3">
                            <button type="button" onClick={() => setShowForm(false)} className="rounded-md border border-gray-300 px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" className="rounded-md bg-teal-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-teal-700">
                                Save Client
                            </button>
                        </div>
                    </form>
                </Modal>
            )}
        </div>
    );
}

function StatCard({ label, value, color }: { label: string; value: number; color: 'teal' | 'green' | 'sky' | 'indigo' }) {
    const colors: Record<string, string> = {
        teal: 'border-teal-200 bg-teal-50 text-teal-700',
        green: 'border-green-200 bg-green-50 text-green-700',
        sky: 'border-sky-200 bg-sky-50 text-sky-700',
        indigo: 'border-indigo-200 bg-indigo-50 text-indigo-700',
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

function Checkbox({ label, checked, onChange }: { label: string; checked: boolean; onChange: (v: boolean) => void }) {
    return (
        <label className="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} className="h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500" />
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
