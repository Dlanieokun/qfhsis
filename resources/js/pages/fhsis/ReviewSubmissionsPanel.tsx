import React, { useCallback, useEffect, useState } from 'react';
import axios from 'axios';

interface Region { regCode: string; regDesc: string; }
interface Province { provCode: string; provDesc: string; regCode: string; }
interface Municipality { citymunCode: string; citymunDesc: string; provCode: string; }
interface Barangay { brgyCode: string; brgyDesc: string; citymunCode: string; }

interface Submission {
    id: number;
    form: string;
    month: string;
    year: string;
    region_code: string | null;
    province_code: string | null;
    municipality_code: string | null;
    barangay_codes: string[] | null;
    status: 'submitted' | 'confirmed' | 'rejected';
    remarks: string | null;
    reviewed_at: string | null;
    created_at: string;
    updated_at: string;
    user?: { id: number; name: string; role: string };
    reviewer?: { id: number; name: string } | null;
}

type StatusFilter = 'submitted' | 'confirmed' | 'rejected';

const FORM_LABELS: Record<string, string> = {
    m1: 'M1 All Programs',
    q1: 'Q1 All Programs',
    m2: 'M2 8PAA',
    a1: 'A1 All Programs',
    mo: 'M2 Morbidity',
};

const MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

const STATUS_STYLES: Record<string, string> = {
    submitted: 'bg-amber-50 text-amber-700 border-amber-200',
    confirmed: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    rejected: 'bg-red-50 text-red-700 border-red-200',
};

const STATUS_LABELS: Record<string, string> = {
    submitted: 'Pending review',
    confirmed: 'Confirmed',
    rejected: 'Rejected',
};

// What the logged-in reviewer is reviewing, for the panel's heading.
const REVIEWS_LABEL: Record<string, string> = {
    'Public Health Nurse': 'BHS submissions in your municipality',
    PHO: 'Public Health Nurse submissions in your province',
    DOH: 'PHO submissions in your region',
};

interface ReviewSubmissionsPanelProps {
    role: string;
    regions: Region[];
    provinces: Province[];
    municipalities: Municipality[];
    barangays: Barangay[];
}

export default function ReviewSubmissionsPanel({
    role,
    regions,
    provinces,
    municipalities,
    barangays,
}: ReviewSubmissionsPanelProps) {
    const [status, setStatus] = useState<StatusFilter>('submitted');
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [rows, setRows] = useState<Submission[]>([]);
    const [pendingCount, setPendingCount] = useState<number | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [open, setOpen] = useState(true);

    // Reject dialog
    const [rejecting, setRejecting] = useState<Submission | null>(null);
    const [remarks, setRemarks] = useState('');
    const [actionBusy, setActionBusy] = useState<number | null>(null);
    const [actionError, setActionError] = useState<string | null>(null);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.get('/qfhsis/public/api/reports/submit-program-report', {
                params: { review: 1, status, page },
            });
            const data = response.data?.data;
            setRows(data?.data ?? []);
            setLastPage(data?.last_page ?? 1);
            if (status === 'submitted') setPendingCount(data?.total ?? 0);
        } catch (err: any) {
            setError(err?.response?.data?.message ?? 'Failed to load submissions.');
        } finally {
            setLoading(false);
        }
    }, [status, page]);

    useEffect(() => {
        load();
    }, [load]);

    const review = async (submission: Submission, action: 'confirm' | 'reject', reason?: string) => {
        setActionBusy(submission.id);
        setActionError(null);
        try {
            await axios.post(`/qfhsis/public/api/reports/submit-program-report/${submission.id}/review`, {
                action,
                remarks: reason,
            });
            setRejecting(null);
            setRemarks('');
            await load();
        } catch (err: any) {
            setActionError(
                err?.response?.data?.errors?.remarks?.[0]
                ?? err?.response?.data?.message
                ?? 'Failed to save review.',
            );
        } finally {
            setActionBusy(null);
        }
    };

    const addressOf = (s: Submission) => {
        const parts: string[] = [];
        const muni = municipalities.find((m) => m.citymunCode === s.municipality_code)?.citymunDesc;
        const prov = provinces.find((p) => p.provCode === s.province_code)?.provDesc;
        const reg = regions.find((r) => r.regCode === s.region_code)?.regDesc;
        if (muni) parts.push(muni);
        if (prov) parts.push(prov);
        if (!muni && !prov && reg) parts.push(reg);
        const brgys = (s.barangay_codes ?? []).map(
            (code) => barangays.find((b) => b.brgyCode === code)?.brgyDesc ?? code,
        );
        const place = parts.join(', ') || '—';
        return brgys.length > 0 ? `${place} (${brgys.join(', ')})` : place;
    };

    const periodOf = (s: Submission) =>
        s.form === 'a1' ? `Annual ${s.year}` : `${MONTHS[Number(s.month) - 1] ?? s.month} ${s.year}`;

    const tabs: { id: StatusFilter; label: string }[] = [
        { id: 'submitted', label: 'Pending' },
        { id: 'confirmed', label: 'Confirmed' },
        { id: 'rejected', label: 'Rejected' },
    ];

    return (
        <div className="max-w-7xl mx-auto px-6 pt-4">
            <div className="rounded-xl border border-gray-200 bg-white shadow-sm">
                <button
                    type="button"
                    onClick={() => setOpen((v) => !v)}
                    className="flex w-full items-center justify-between px-4 py-3 text-left"
                >
                    <div>
                        <h2 className="text-sm font-bold text-gray-800">
                            Review submissions
                            {pendingCount !== null && pendingCount > 0 && (
                                <span className="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">
                                    {pendingCount} pending
                                </span>
                            )}
                        </h2>
                        <p className="text-xs text-gray-500">{REVIEWS_LABEL[role] ?? 'Submissions to review'}</p>
                    </div>
                    <span className="text-xs text-gray-400">{open ? 'Hide' : 'Show'}</span>
                </button>

                {open && (
                    <div className="border-t border-gray-100 px-4 pb-4 pt-3">
                        <div className="mb-3 flex gap-1">
                            {tabs.map((t) => (
                                <button
                                    key={t.id}
                                    type="button"
                                    onClick={() => { setStatus(t.id); setPage(1); }}
                                    className={`rounded px-3 py-1 text-xs font-medium border transition ${
                                        status === t.id
                                            ? 'bg-blue-600 text-white border-blue-600'
                                            : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'
                                    }`}
                                >
                                    {t.label}
                                </button>
                            ))}
                        </div>

                        {(error || actionError) && (
                            <div className="mb-3 rounded border border-red-200 bg-red-50 p-2 text-xs text-red-700">
                                {error ?? actionError}
                            </div>
                        )}

                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-xs">
                                <thead>
                                    <tr className="bg-gray-100 text-left text-gray-600">
                                        <th className="border border-gray-200 px-2 py-1.5">Submitted by</th>
                                        <th className="border border-gray-200 px-2 py-1.5">Form</th>
                                        <th className="border border-gray-200 px-2 py-1.5">Period</th>
                                        <th className="border border-gray-200 px-2 py-1.5">Address</th>
                                        <th className="border border-gray-200 px-2 py-1.5">Submitted</th>
                                        <th className="border border-gray-200 px-2 py-1.5">Status / remarks</th>
                                        <th className="border border-gray-200 px-2 py-1.5 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {loading && rows.length === 0 ? (
                                        <tr><td colSpan={7} className="px-2 py-4 text-center italic text-gray-400">Loading…</td></tr>
                                    ) : rows.length === 0 ? (
                                        <tr><td colSpan={7} className="px-2 py-4 text-center italic text-gray-400">
                                            No {STATUS_LABELS[status].toLowerCase()} submissions.
                                        </td></tr>
                                    ) : rows.map((s) => (
                                        <tr key={s.id} className="align-top">
                                            <td className="border border-gray-200 px-2 py-1.5">
                                                <div className="font-medium text-gray-800">{s.user?.name ?? '—'}</div>
                                                <div className="text-[10px] text-gray-400">{s.user?.role}</div>
                                            </td>
                                            <td className="border border-gray-200 px-2 py-1.5">{FORM_LABELS[s.form] ?? s.form}</td>
                                            <td className="border border-gray-200 px-2 py-1.5">{periodOf(s)}</td>
                                            <td className="border border-gray-200 px-2 py-1.5">{addressOf(s)}</td>
                                            <td className="border border-gray-200 px-2 py-1.5 whitespace-nowrap">
                                                {new Date(s.updated_at).toLocaleString()}
                                            </td>
                                            <td className="border border-gray-200 px-2 py-1.5">
                                                <span className={`inline-block rounded-full border px-2 py-0.5 text-[10px] font-semibold ${STATUS_STYLES[s.status]}`}>
                                                    {STATUS_LABELS[s.status]}
                                                </span>
                                                {s.reviewer && s.status !== 'submitted' && (
                                                    <div className="mt-1 text-[10px] text-gray-400">by {s.reviewer.name}</div>
                                                )}
                                                {s.remarks && (
                                                    <div className="mt-1 text-[11px] text-gray-600">“{s.remarks}”</div>
                                                )}
                                            </td>
                                            <td className="border border-gray-200 px-2 py-1.5 text-center whitespace-nowrap">
                                                {s.status === 'submitted' ? (
                                                    <div className="flex justify-center gap-1">
                                                        <button
                                                            type="button"
                                                            disabled={actionBusy === s.id}
                                                            onClick={() => review(s, 'confirm')}
                                                            className="rounded bg-emerald-600 px-2.5 py-1 text-[11px] font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
                                                        >
                                                            Confirm
                                                        </button>
                                                        <button
                                                            type="button"
                                                            disabled={actionBusy === s.id}
                                                            onClick={() => { setActionError(null); setRemarks(''); setRejecting(s); }}
                                                            className="rounded bg-red-600 px-2.5 py-1 text-[11px] font-medium text-white hover:bg-red-700 disabled:opacity-60"
                                                        >
                                                            Reject
                                                        </button>
                                                    </div>
                                                ) : (
                                                    <span className="text-gray-300">—</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {lastPage > 1 && (
                            <div className="mt-3 flex items-center justify-end gap-2 text-xs text-gray-600">
                                <button
                                    type="button"
                                    disabled={page <= 1 || loading}
                                    onClick={() => setPage((p) => p - 1)}
                                    className="rounded border border-gray-300 px-2 py-1 disabled:opacity-40"
                                >
                                    Previous
                                </button>
                                <span>Page {page} of {lastPage}</span>
                                <button
                                    type="button"
                                    disabled={page >= lastPage || loading}
                                    onClick={() => setPage((p) => p + 1)}
                                    className="rounded border border-gray-300 px-2 py-1 disabled:opacity-40"
                                >
                                    Next
                                </button>
                            </div>
                        )}
                    </div>
                )}
            </div>

            {rejecting && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="reject-submission-title"
                >
                    <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                        <h3 id="reject-submission-title" className="text-lg font-bold text-gray-800">
                            Reject submission
                        </h3>
                        <p className="mt-1 text-sm text-gray-500">
                            {rejecting.user?.name} · {FORM_LABELS[rejecting.form] ?? rejecting.form} · {periodOf(rejecting)}
                        </p>

                        <label className="mt-4 mb-1 block text-xs font-semibold text-gray-700">
                            Remarks (reason for rejection)
                        </label>
                        <textarea
                            value={remarks}
                            onChange={(e) => setRemarks(e.target.value)}
                            rows={4}
                            maxLength={1000}
                            className="w-full rounded border border-gray-300 px-2 py-1.5 text-sm outline-none focus:border-blue-500"
                            placeholder="Explain what needs to be corrected…"
                        />

                        {actionError && (
                            <div className="mt-3 rounded border border-red-200 bg-red-50 p-2 text-xs text-red-700">{actionError}</div>
                        )}

                        <div className="mt-5 flex justify-end gap-2">
                            <button
                                type="button"
                                disabled={actionBusy !== null}
                                onClick={() => setRejecting(null)}
                                className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                disabled={actionBusy !== null || remarks.trim() === ''}
                                onClick={() => review(rejecting, 'reject', remarks.trim())}
                                className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-60"
                            >
                                {actionBusy !== null ? 'Rejecting…' : 'Reject'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
