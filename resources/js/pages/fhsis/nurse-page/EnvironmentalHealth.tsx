import React, { useMemo, useState } from 'react';

// Mirrors the "Masterlist_ENVI" worksheet: Masterlist for Access to Basic Safe
// Water Supply, and Access to Basic Sanitation Facility / Safely Managed
// Sanitation Services (SMDWS / SMSS).

export interface EnvironmentalHealthRecord {
    id: string;
    householdHead: string;
    waterSourceLevel: 'I' | 'II' | 'III' | ''; // Level I/II/III
    otherWaterSource: string;
    locatedInsideDwelling: boolean;
    availableAtLeast12Hrs: boolean;
    microDate: string;
    microResultEcoli: boolean; // 1 - presence of E. coli
    arsenicTestDate: string;
    arsenicWithinLimit: boolean; // 1 - within allowable PNSDW limit
    smdws: boolean; // Safely Managed Drinking Water Service
    sanitaryToiletType: 'septic' | 'sewer' | 'vip' | ''; // pour/flush septic, pour/flush sewer, VIP/composting
    unsanitaryToiletType: '3' | '2' | '1' | '0' | ''; // water sealed open drain / overhung / open pit / none
    toiletShared: boolean;
    basicSanitationFacility: boolean;
    excretaDisposalType: 'insitu' | 'offsite-transport' | 'offsite-sewer' | '';
    smss: boolean; // Safely Managed Sanitation Service
    remarks: string;
}

const emptyForm: Omit<EnvironmentalHealthRecord, 'id'> = {
    householdHead: '',
    waterSourceLevel: '',
    otherWaterSource: '',
    locatedInsideDwelling: false,
    availableAtLeast12Hrs: false,
    microDate: '',
    microResultEcoli: false,
    arsenicTestDate: '',
    arsenicWithinLimit: false,
    smdws: false,
    sanitaryToiletType: '',
    unsanitaryToiletType: '',
    toiletShared: false,
    basicSanitationFacility: false,
    excretaDisposalType: '',
    smss: false,
    remarks: '',
};

const sampleRecords: EnvironmentalHealthRecord[] = [
    {
        id: 'env-1',
        householdHead: 'Peter Bautista',
        waterSourceLevel: 'III',
        otherWaterSource: '',
        locatedInsideDwelling: true,
        availableAtLeast12Hrs: true,
        microDate: '01/20/26',
        microResultEcoli: false,
        arsenicTestDate: '',
        arsenicWithinLimit: false,
        smdws: true,
        sanitaryToiletType: 'septic',
        unsanitaryToiletType: '',
        toiletShared: false,
        basicSanitationFacility: true,
        excretaDisposalType: 'offsite-transport',
        smss: true,
        remarks: 'Compliant with SMDWS and SMSS',
    },
    {
        id: 'env-2',
        householdHead: 'John Villareal',
        waterSourceLevel: 'I',
        otherWaterSource: '',
        locatedInsideDwelling: false,
        availableAtLeast12Hrs: false,
        microDate: '',
        microResultEcoli: false,
        arsenicTestDate: '',
        arsenicWithinLimit: false,
        smdws: false,
        sanitaryToiletType: '',
        unsanitaryToiletType: '1',
        toiletShared: false,
        basicSanitationFacility: false,
        excretaDisposalType: '',
        smss: false,
        remarks: 'For follow-up sanitation intervention',
    },
];

const inputClass =
    'rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500';

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <label className="flex flex-col gap-1 text-sm">
            <span className="font-medium text-gray-700">{label}</span>
            {children}
        </label>
    );
}

export default function EnvironmentalHealth({ records: initial }: { records?: EnvironmentalHealthRecord[] }) {
    const [records, setRecords] = useState<EnvironmentalHealthRecord[]>(initial && initial.length ? initial : sampleRecords);
    const [search, setSearch] = useState('');
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState(emptyForm);

    const filtered = useMemo(
        () => records.filter((r) => r.householdHead.toLowerCase().includes(search.toLowerCase())),
        [records, search],
    );

    const stats = useMemo(
        () => ({
            total: records.length,
            smdws: records.filter((r) => r.smdws).length,
            smss: records.filter((r) => r.smss).length,
            noSanitary: records.filter((r) => !r.sanitaryToiletType).length,
        }),
        [records],
    );

    function submit(e: React.FormEvent) {
        e.preventDefault();
        setRecords((prev) => [...prev, { ...form, id: `env-${prev.length + 1}-${Date.now()}` }]);
        setForm(emptyForm);
        setShowForm(false);
    }

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatCard label="Households Surveyed" value={stats.total} color="emerald" />
                <StatCard label="Safely Managed Water (SMDWS)" value={stats.smdws} color="sky" />
                <StatCard label="Safely Managed Sanitation (SMSS)" value={stats.smss} color="indigo" />
                <StatCard label="Without Sanitary Toilet" value={stats.noSanitary} color="red" />
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div>
                    <h2 className="text-base font-semibold text-gray-900">Water Sanitation, and Hygiene (WASH)</h2>
                    <p className="text-sm text-gray-500">Masterlist for Environmental Health and Sanitation</p>
                </div>
                <div className="flex items-center gap-2">
                    <input type="text" placeholder="Search household head..." value={search} onChange={(e) => setSearch(e.target.value)} className={inputClass} />
                    <button onClick={() => setShowForm(true)} className="rounded-md bg-emerald-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-emerald-700">
                        + Add Household
                    </button>
                </div>
            </div>

            <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
                <table className="min-w-full divide-y divide-gray-200 text-sm">
                    <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <Th rowSpan={2}>No.</Th>
                            <Th rowSpan={2}>Household Head</Th>
                            <Th colSpan={4} className="text-center">
                                Water Supply
                            </Th>
                            <Th colSpan={5} className="text-center">
                                Sanitation Facility
                            </Th>
                            <Th rowSpan={2}>Remarks</Th>
                        </tr>
                        <tr>
                            <Th>Source Level</Th>
                            <Th>Inside Dwelling / 12+ hrs</Th>
                            <Th>Microbio / Arsenic</Th>
                            <Th>SMDWS</Th>
                            <Th>Toilet Type</Th>
                            <Th>Shared</Th>
                            <Th>Basic Sanitation</Th>
                            <Th>Excreta Disposal</Th>
                            <Th>SMSS</Th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {filtered.map((r, i) => (
                            <tr key={r.id} className="hover:bg-emerald-50/40">
                                <Td>{i + 1}</Td>
                                <Td className="font-medium text-gray-900">{r.householdHead}</Td>
                                <Td>{r.waterSourceLevel ? `Level ${r.waterSourceLevel}` : r.otherWaterSource || '—'}</Td>
                                <Td>
                                    {r.locatedInsideDwelling ? 'Inside' : 'Outside'} / {r.availableAtLeast12Hrs ? '≥12h' : '<12h'}
                                </Td>
                                <Td className="max-w-[150px] truncate" title={`Microbio: ${r.microDate || 'n/a'} (${r.microResultEcoli ? 'E.coli present' : 'negative'}); Arsenic: ${r.arsenicTestDate || 'n/a'} (${r.arsenicWithinLimit ? 'within limit' : 'n/a'})`}>
                                    {r.microDate || r.arsenicTestDate ? 'Tested' : '—'}
                                </Td>
                                <Td>
                                    <Badge on={r.smdws} tone="sky" />
                                </Td>
                                <Td>
                                    {r.sanitaryToiletType === 'septic' && 'Pour/flush → septic'}
                                    {r.sanitaryToiletType === 'sewer' && 'Pour/flush → sewer'}
                                    {r.sanitaryToiletType === 'vip' && 'VIP / Composting'}
                                    {!r.sanitaryToiletType && r.unsanitaryToiletType && (
                                        <span className="text-red-600">
                                            Unsanitary ({r.unsanitaryToiletType})
                                        </span>
                                    )}
                                    {!r.sanitaryToiletType && !r.unsanitaryToiletType && '—'}
                                </Td>
                                <Td>{r.toiletShared ? 'Yes' : 'No'}</Td>
                                <Td>
                                    <Badge on={r.basicSanitationFacility} tone="emerald" />
                                </Td>
                                <Td>
                                    {r.excretaDisposalType === 'insitu' && 'In-situ treatment'}
                                    {r.excretaDisposalType === 'offsite-transport' && 'Desludged & treated off-site'}
                                    {r.excretaDisposalType === 'offsite-sewer' && 'Sewered & treated off-site'}
                                    {!r.excretaDisposalType && '—'}
                                </Td>
                                <Td>
                                    <Badge on={r.smss} tone="indigo" />
                                </Td>
                                <Td className="max-w-[160px] truncate" title={r.remarks}>
                                    {r.remarks}
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
                <Modal title="Add Household Record" onClose={() => setShowForm(false)}>
                    <form onSubmit={submit} className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <Field label="Name of Household Head">
                            <input className={inputClass} required value={form.householdHead} onChange={(e) => setForm({ ...form, householdHead: e.target.value })} />
                        </Field>
                        <Field label="Type of Improved Water Source">
                            <select className={inputClass} value={form.waterSourceLevel} onChange={(e) => setForm({ ...form, waterSourceLevel: e.target.value as EnvironmentalHealthRecord['waterSourceLevel'] })}>
                                <option value="">Select</option>
                                <option value="I">Level I — Point source</option>
                                <option value="II">Level II — Communal faucet</option>
                                <option value="III">Level III — Waterworks / house connection</option>
                            </select>
                        </Field>
                        <Field label="Others, specify (unimproved source)">
                            <input className={inputClass} value={form.otherWaterSource} onChange={(e) => setForm({ ...form, otherWaterSource: e.target.value })} />
                        </Field>

                        <div className="flex flex-wrap items-center gap-4 sm:col-span-3">
                            <Checkbox label="Located inside dwelling / premises" checked={form.locatedInsideDwelling} onChange={(v) => setForm({ ...form, locatedInsideDwelling: v })} />
                            <Checkbox label="Available at least 12 hrs/day" checked={form.availableAtLeast12Hrs} onChange={(v) => setForm({ ...form, availableAtLeast12Hrs: v })} />
                            <Checkbox label="Safely Managed Drinking-Water Service (SMDWS)" checked={form.smdws} onChange={(v) => setForm({ ...form, smdws: v })} />
                        </div>

                        <Field label="Microbiological Testing Date">
                            <input className={inputClass} placeholder="mm/dd/yy" value={form.microDate} onChange={(e) => setForm({ ...form, microDate: e.target.value })} />
                        </Field>
                        <Field label="Arsenic Test Date (optional)">
                            <input className={inputClass} placeholder="mm/dd/yy" value={form.arsenicTestDate} onChange={(e) => setForm({ ...form, arsenicTestDate: e.target.value })} />
                        </Field>
                        <div className="flex items-end gap-4">
                            <Checkbox label="E. coli present" checked={form.microResultEcoli} onChange={(v) => setForm({ ...form, microResultEcoli: v })} />
                            <Checkbox label="Arsenic within PNSDW limit" checked={form.arsenicWithinLimit} onChange={(v) => setForm({ ...form, arsenicWithinLimit: v })} />
                        </div>

                        <Field label="Sanitary Toilet Facility Type">
                            <select className={inputClass} value={form.sanitaryToiletType} onChange={(e) => setForm({ ...form, sanitaryToiletType: e.target.value as EnvironmentalHealthRecord['sanitaryToiletType'] })}>
                                <option value="">None / not applicable</option>
                                <option value="septic">Pour/flush → septic tank</option>
                                <option value="sewer">Pour/flush → community sewer</option>
                                <option value="vip">Ventilated Pit (VIP) / Composting Toilet</option>
                            </select>
                        </Field>
                        <Field label="Unsanitary Toilet Type (if no sanitary facility)">
                            <select className={inputClass} value={form.unsanitaryToiletType} onChange={(e) => setForm({ ...form, unsanitaryToiletType: e.target.value as EnvironmentalHealthRecord['unsanitaryToiletType'] })}>
                                <option value="">N/A</option>
                                <option value="3">3 — Water sealed, connected to open drain</option>
                                <option value="2">2 — Overhung Latrine</option>
                                <option value="1">1 — Open Pit Latrine</option>
                                <option value="0">0 — Without Toilet</option>
                            </select>
                        </Field>
                        <div className="flex items-end">
                            <Checkbox label="Toilet shared with other household" checked={form.toiletShared} onChange={(v) => setForm({ ...form, toiletShared: v })} />
                        </div>

                        <div className="flex items-center gap-4 sm:col-span-3">
                            <Checkbox label="Basic Sanitation Facility (sanitary & not shared)" checked={form.basicSanitationFacility} onChange={(v) => setForm({ ...form, basicSanitationFacility: v })} />
                            <Checkbox label="Safely Managed Sanitation Service (SMSS)" checked={form.smss} onChange={(v) => setForm({ ...form, smss: v })} />
                        </div>

                        <Field label="Disposal / Treatment of Excreta">
                            <select className={inputClass} value={form.excretaDisposalType} onChange={(e) => setForm({ ...form, excretaDisposalType: e.target.value as EnvironmentalHealthRecord['excretaDisposalType'] })}>
                                <option value="">N/A</option>
                                <option value="insitu">Stored & treated in-situ, by-products reused/disposed</option>
                                <option value="offsite-transport">Stored, desludged, transported & treated off-site</option>
                                <option value="offsite-sewer">Conveyed via sewer & treated off-site</option>
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
                            <button type="submit" className="rounded-md bg-emerald-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-emerald-700">
                                Save Household
                            </button>
                        </div>
                    </form>
                </Modal>
            )}
        </div>
    );
}

function StatCard({ label, value, color }: { label: string; value: number; color: 'emerald' | 'sky' | 'indigo' | 'red' }) {
    const colors: Record<string, string> = {
        emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        sky: 'border-sky-200 bg-sky-50 text-sky-700',
        indigo: 'border-indigo-200 bg-indigo-50 text-indigo-700',
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

function Badge({ on, tone }: { on: boolean; tone: 'sky' | 'emerald' | 'indigo' }) {
    if (!on) return <span className="text-gray-400">No</span>;
    const toneClass = { sky: 'bg-sky-100 text-sky-800', emerald: 'bg-emerald-100 text-emerald-800', indigo: 'bg-indigo-100 text-indigo-800' }[tone];
    return <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${toneClass}`}>Yes</span>;
}

function Checkbox({ label, checked, onChange }: { label: string; checked: boolean; onChange: (v: boolean) => void }) {
    return (
        <label className="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} className="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
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
