import React from 'react';

// Read-only view of the "TARGET CLIENT LIST FOR FAMILY PLANNING SERVICES"
// (TCL_FP) worksheet from family_planning.xlsx:
//   No. | Date of Registration | Family Serial Number | Full Name | Complete
//   Address | Age / Date of Birth | Age Group | Type of Client | Source |
//   Previous Method | Follow-Up Visits (Jan-Dec, each with a scheduled and
//   an actual date) | Drop-Out (Date + Reason) | Remarks/Actions Taken
//
// This component only displays data passed in via the `clients` prop — it
// does not let the user add, edit, or remove rows. Wire it up to
// PublicNurseController so `clients` comes from the database.

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as const;
type Month = (typeof MONTHS)[number];

interface FollowUpVisit {
    scheduled: string;
    actual: string;
}

export interface FamilyPlanningClient {
    id: string;
    dateOfRegistration: string;
    familySerialNumber: string;
    fullName: string;
    completeAddress: string;
    age: string;
    dateOfBirth: string;
    ageGroup: '' | 'A' | 'B' | 'C';
    typeOfClient: string;
    source: '' | 'Public' | 'Private';
    previousMethod: string;
    followUpVisits: Record<Month, FollowUpVisit>;
    dropOutDate: string;
    dropOutReason: string;
    remarks: string;
}

interface FamilyPlanningProps {
    clients?: FamilyPlanningClient[];
}

const th = 'border border-gray-200 bg-gray-50 px-2 py-2 text-xs font-semibold text-gray-700 align-bottom whitespace-nowrap';
const td = 'border border-gray-200 px-2 py-1.5 text-xs text-gray-700 align-top';

function formatDate(value: string): string {
    if (!value) return '—';
    const date = new Date(value);
    if (isNaN(date.getTime())) return value;
    return date.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

export default function FamilyPlanning({ clients = [] }: FamilyPlanningProps) {
    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-gray-800">Target Client List for Family Planning Services</h2>
                <span className="text-xs text-gray-400">{clients.length} record{clients.length === 1 ? '' : 's'} · View only</span>
            </div>

            <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
                <table className="w-full border-collapse">
                    <thead>
                        <tr>
                            <th className={th}>No.</th>
                            <th className={th}>Date of Registration</th>
                            <th className={th}>Family Serial No.</th>
                            <th className={th}>Full Name (Last, First, MI)</th>
                            <th className={th}>Complete Address</th>
                            <th className={th}>Age / Date of Birth</th>
                            <th className={th}>Age Group</th>
                            <th className={th}>Type of Client</th>
                            <th className={th}>Source</th>
                            <th className={th}>Previous Method</th>
                            {MONTHS.map((month) => (
                                <th key={month} className={th}>
                                    {month}
                                    <div className="mt-1 flex gap-2 text-[10px] font-normal text-gray-400">
                                        <span>Sched.</span>
                                        <span>Actual</span>
                                    </div>
                                </th>
                            ))}
                            <th className={th}>Drop-Out Date</th>
                            <th className={th}>Drop-Out Reason</th>
                            <th className={th}>Remarks / Actions Taken</th>
                        </tr>
                    </thead>
                    <tbody>
                        {clients.length === 0 && (
                            <tr>
                                <td className={`${td} text-center text-gray-400`} colSpan={10 + MONTHS.length + 3}>
                                    No records to display.
                                </td>
                            </tr>
                        )}
                        {clients.map((client, index) => (
                            <tr key={client.id} className="odd:bg-white even:bg-gray-50/50">
                                <td className={`${td} text-center`}>{index + 1}</td>
                                <td className={td}>{formatDate(client.dateOfRegistration)}</td>
                                <td className={td}>{client.familySerialNumber || '—'}</td>
                                <td className={`${td} min-w-[160px] font-medium text-gray-800`}>{client.fullName || '—'}</td>
                                <td className={`${td} min-w-[160px]`}>{client.completeAddress || '—'}</td>
                                <td className={td}>
                                    <div>{client.age || '—'}</div>
                                    <div className="text-gray-400">{formatDate(client.dateOfBirth)}</div>
                                </td>
                                <td className={`${td} text-center`}>{client.ageGroup || '—'}</td>
                                <td className={td}>{client.typeOfClient || '—'}</td>
                                <td className={td}>{client.source || '—'}</td>
                                <td className={td}>{client.previousMethod || '—'}</td>
                                {MONTHS.map((month) => (
                                    <td key={month} className={td}>
                                        <div>{formatDate(client.followUpVisits[month]?.scheduled ?? '')}</div>
                                        <div className="text-gray-400">{formatDate(client.followUpVisits[month]?.actual ?? '')}</div>
                                    </td>
                                ))}
                                <td className={td}>{formatDate(client.dropOutDate)}</td>
                                <td className={td}>{client.dropOutReason || '—'}</td>
                                <td className={`${td} min-w-[140px]`}>{client.remarks || '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}