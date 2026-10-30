import React, { useState, useEffect, useRef } from 'react';
import { Head } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import axios from 'axios';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';

// Import child components
import M1AllPrograms from './M1AllPrograms';
import Q1AllPrograms from './Q1AllPrograms';
import M28PAA from './M28PAA';
import A1AllPrograms from './A1AllPrograms';
import MorbidityPage from './MorbidityPage';
import ReviewSubmissionsPanel from './ReviewSubmissionsPanel';

// Parse JSON-encoded or plain arrays stored on the user model (e.g. barangay_codes)
const parseArray = (val: unknown): string[] => {
    if (Array.isArray(val)) return val as string[];
    if (typeof val === 'string') {
        try { const p = JSON.parse(val); return Array.isArray(p) ? p : []; } catch { return []; }
    }
    return [];
};

// ─── Location Data Shapes ────────────────────────────────────────────────────
interface Region { regCode: string; regDesc: string; }
interface Province { provCode: string; provDesc: string; regCode: string; }
interface Municipality { citymunCode: string; citymunDesc: string; provCode: string; }
interface Barangay { brgyCode: string; brgyDesc: string; citymunCode: string; }

// Address captured from the form filter when "Apply Filters" is clicked
export interface FilterLocation {
    region_code: string;
    province_code: string;
    municipality_code: string;
    barangay_codes: string[];
}

// Result of the user's own submission for the selected form/period/address
interface ExistingSubmission {
    status: 'submitted' | 'confirmed' | 'rejected';
    remarks: string | null;
    reviewer: string | null;
}

// Whether the consolidated report can be submitted yet (Public Health Nurse / PHO)
interface Readiness {
    applies: boolean;
    ready: boolean;
    required: number;
    confirmed: number;
    pending: string[];
    message: string;
}

interface PhoPageProps {
    // Location Data injected via controller
    regions?: Region[];
    provinces?: Province[];
    municipalities?: Municipality[];
    barangays?: Barangay[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'FHSIS', href: '/qfhsis/public/fhsis/dashboard' },
    { title: 'PHO Form M1', href: '/qfhsis/public/fhsis/pho' },
];

// Months used by the "Submit Report" modal
const SUBMIT_MONTHS = [
    { value: '01', label: 'January' },
    { value: '02', label: 'February' },
    { value: '03', label: 'March' },
    { value: '04', label: 'April' },
    { value: '05', label: 'May' },
    { value: '06', label: 'June' },
    { value: '07', label: 'July' },
    { value: '08', label: 'August' },
    { value: '09', label: 'September' },
    { value: '10', label: 'October' },
    { value: '11', label: 'November' },
    { value: '12', label: 'December' },
];

// Human-readable address shown inside the Submit modal
interface LocationLabels {
    region: string;
    province: string;
    municipality: string;
    barangays: string[];
}

function LocationSummary({ location }: { location: LocationLabels }) {
    const rows: [string, string][] = [
        ['Region', location.region],
        ['Province', location.province],
        ['Municipality / City', location.municipality],
        ['Barangay', location.barangays.length ? location.barangays.join(', ') : ''],
    ];

    return (
        <div className="mt-4 rounded border border-gray-200 bg-gray-50 px-3 py-2">
            <p className="mb-1 text-xs font-semibold text-gray-700">Address (from filter)</p>
            <dl className="space-y-0.5 text-xs">
                {rows.map(([label, value]) => (
                    <div key={label} className="flex gap-2">
                        <dt className="w-28 shrink-0 text-gray-500">{label}</dt>
                        <dd className="text-gray-800 break-words">{value || '—'}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

// ─── Submit Report Modal ──────────────────────────────────────────────────────
interface SubmitReportModalProps {
    isOpen: boolean;
    month: string;
    year: string;
    isSubmitting: boolean;
    error: string | null;
    hasExisting: boolean;
    checkingExisting: boolean;
    location: LocationLabels;
    onMonthChange: (value: string) => void;
    onYearChange: (value: string) => void;
    onCancel: () => void;
    onSubmit: () => void;
}

function SubmitReportModal({
    isOpen,
    month,
    year,
    isSubmitting,
    error,
    hasExisting,
    checkingExisting,
    location,
    onMonthChange,
    onYearChange,
    onCancel,
    onSubmit,
}: SubmitReportModalProps) {
    if (!isOpen) return null;

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="submit-report-title"
            onClick={onCancel}
        >
            <div
                className="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl"
                onClick={(e) => e.stopPropagation()}
            >
                <h3 id="submit-report-title" className="text-lg font-bold text-gray-800">
                    Submit Report
                </h3>
                <p className="mt-1 text-xs text-gray-500">
                    Select the reporting month and year you want to submit.
                </p>

                <LocationSummary location={location} />

                <div className="mt-4 space-y-3">
                    <div>
                        <label className="mb-1 block text-xs font-semibold text-gray-700">Month</label>
                        <select
                            value={month}
                            onChange={(e) => onMonthChange(e.target.value)}
                            className="w-full rounded border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500"
                        >
                            <option value="">Select Month</option>
                            {SUBMIT_MONTHS.map((m) => (
                                <option key={m.value} value={m.value}>{m.label}</option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-semibold text-gray-700">Year</label>
                        <input
                            type="number"
                            value={year}
                            onChange={(e) => onYearChange(e.target.value)}
                            placeholder="YYYY"
                            className="w-full rounded border border-gray-300 px-3 py-2 text-sm outline-none focus:border-blue-500"
                        />
                    </div>

                    {checkingExisting && (
                        <p className="text-xs text-gray-400 italic">Checking existing submissions…</p>
                    )}

                    {!checkingExisting && hasExisting && (
                        <div className="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                            This report has already been submitted for the selected month and year.
                        </div>
                    )}

                    {error && (
                        <div className="rounded border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                            {error}
                        </div>
                    )}
                </div>

                <div className="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={isSubmitting}
                        className="rounded border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-100 disabled:opacity-60"
                    >
                        Cancel
                    </button>
                    {!hasExisting && (
                        <button
                            type="button"
                            onClick={onSubmit}
                            disabled={isSubmitting || checkingExisting || !month || !year}
                            className="rounded bg-blue-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-blue-400"
                        >
                            {isSubmitting ? 'Submitting...' : 'Submit'}
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function PhoPage({
    regions = [], provinces = [], municipalities = [], barangays = []
}: PhoPageProps) {
    const { auth } = usePage<SharedData>().props;
    const user = auth?.user as any;

    const [activeTab, setActiveTab] = useState<'m1' | 'q1' | 'm2' | 'a1' | 'mo'>('m1');

    // The Morbidity tab has no filter step of its own (it's a read-only view),
    // so it doesn't gate the Submit button behind an "Apply Filter" click.
    const [isFilterApplied, setIsFilterApplied] = useState(activeTab === 'mo');

    // Address (region/province/municipality/barangays) taken from the filter
    // the user last applied. Falls back to the user's profile address when a
    // tab doesn't report one.
    const [appliedLocation, setAppliedLocation] = useState<FilterLocation | null>(null);

    const handleTabChange = (tab: typeof activeTab) => {
        setActiveTab(tab);
        setIsFilterApplied(tab === 'mo');
        setSubmitMonth('');
        setExistingSubmission(null);
        setAppliedLocation(null);
    };

    // ─── Submit Report modal state ───────────────────────────────────────────
    const [isSubmitModalOpen, setIsSubmitModalOpen] = useState(false);
    const [submitMonth, setSubmitMonth] = useState('');
    const [submitYear, setSubmitYear] = useState(String(new Date().getFullYear()));
    const [isSubmittingReport, setIsSubmittingReport] = useState(false);
    const [submitReportError, setSubmitReportError] = useState<string | null>(null);

    // ─── Review workflow roles ───────────────────────────────────────────────
    // BHS submit to the Public Health Nurse, who submits to the PHO, who submits
    // to DOH. Each of those upper levels reviews (confirms / rejects) the level
    // below it. Administrator can submit but is outside the review chain.
    const role: string = user?.role ?? '';
    const canSubmitReport = ['BHS', 'Public Health Nurse', 'PHO', 'Administrator'].includes(role);
    const canReviewReports = ['Public Health Nurse', 'PHO', 'DOH'].includes(role);

    // Called by whichever form is active when its own "Apply Filter(s)"
    // button is clicked — reveals the Submit button and pre-fills the
    // modal's month/year (and the address) with whatever the user just filtered by.
    const handleFormFilterApplied = (month: string, year: string, location?: FilterLocation) => {
        setIsFilterApplied(true);
        if (month) setSubmitMonth(month);
        if (year) setSubmitYear(year);
        if (location) setAppliedLocation(location);
    };

    // Address that gets saved with the submission: the filter's address if the
    // form reported one, otherwise the logged-in user's profile address.
    const getSubmitLocation = () => ({
        region_code: appliedLocation?.region_code ?? user?.region_code ?? '',
        province_code: appliedLocation?.province_code ?? user?.province_code ?? '',
        municipality_code: appliedLocation?.municipality_code ?? user?.municipality_code ?? '',
        barangay_codes: appliedLocation?.barangay_codes ?? parseArray(user?.barangay_codes),
    });

    // Names for the address that will be saved, looked up from the location
    // lists (falls back to the raw code if a name can't be found).
    const submitLocation = getSubmitLocation();
    const submitLocationLabels: LocationLabels = {
        region:
            regions.find((r) => r.regCode === submitLocation.region_code)?.regDesc
            ?? submitLocation.region_code,
        province:
            provinces.find((p) => p.provCode === submitLocation.province_code)?.provDesc
            ?? submitLocation.province_code,
        municipality:
            municipalities.find((m) => m.citymunCode === submitLocation.municipality_code)?.citymunDesc
            ?? submitLocation.municipality_code,
        barangays: submitLocation.barangay_codes.map(
            (code) => barangays.find((b) => b.brgyCode === code)?.brgyDesc ?? code,
        ),
    };

    // The submit_program_report row (if any) for this form + month + year + address.
    // A rejected one can be resubmitted; anything else blocks a new submission.
    const [existingSubmission, setExistingSubmission] = useState<ExistingSubmission | null>(null);
    const hasExistingSubmission = existingSubmission !== null && existingSubmission.status !== 'rejected';

    // For Public Health Nurse / PHO: whether everything below has been confirmed yet.
    const [readiness, setReadiness] = useState<Readiness | null>(null);
    const [checkingExistingSubmission, setCheckingExistingSubmission] = useState(false);

    const openSubmitModal = () => {
        setSubmitReportError(null);
        setIsSubmitModalOpen(true);
    };

    const closeSubmitModal = () => {
        if (isSubmittingReport) return;
        setIsSubmitModalOpen(false);
        setSubmitReportError(null);
    };

    // Checks submit_program_report for a row matching this user + form +
    // month + year. Runs as soon as a filter is applied (so the top-level
    // area can switch between "Submit Report" and "Already Submitted"),
    // and again if the month/year are changed from inside the modal.
    useEffect(() => {
        if (!isFilterApplied || !submitMonth || !submitYear) {
            setExistingSubmission(null);
            return;
        }

        let cancelled = false;
        setCheckingExistingSubmission(true);

        const timer = setTimeout(async () => {
            try {
                const loc = getSubmitLocation();
                const response = await axios.get('/qfhsis/public/api/reports/submit-program-report', {
                    params: { form: activeTab, month: submitMonth, year: submitYear, mine: 1 },
                });
                if (cancelled) return;
                const rows: any[] = response.data?.data?.data ?? [];
                const norm = (codes: unknown) =>
                    (Array.isArray(codes) ? codes.map(String) : []).sort().join(',');
                const sameAddress = (r: any) =>
                    (r.region_code ?? '') === loc.region_code
                    && (r.province_code ?? '') === loc.province_code
                    && (r.municipality_code ?? '') === loc.municipality_code
                    && norm(r.barangay_codes) === norm(loc.barangay_codes);
                const match = rows.find(sameAddress);
                setExistingSubmission(
                    match
                        ? { status: match.status, remarks: match.remarks ?? null, reviewer: match.reviewer?.name ?? null }
                        : null,
                );
            } catch {
                if (!cancelled) setExistingSubmission(null);
            } finally {
                if (!cancelled) setCheckingExistingSubmission(false);
            }
        }, 350);

        return () => {
            cancelled = true;
            clearTimeout(timer);
        };
    }, [isFilterApplied, activeTab, submitMonth, submitYear, appliedLocation]);

    // Public Health Nurse / PHO can only submit the consolidated report once every
    // submission one level down (BHS / nurses) has been confirmed.
    useEffect(() => {
        if (!isFilterApplied || !submitMonth || !submitYear || !['Public Health Nurse', 'PHO'].includes(role)) {
            setReadiness(null);
            return;
        }

        let cancelled = false;
        axios
            .get('/qfhsis/public/api/reports/submit-program-report/readiness', {
                params: { form: activeTab, month: submitMonth, year: submitYear },
            })
            .then((response) => {
                if (!cancelled) setReadiness(response.data?.data ?? null);
            })
            .catch(() => {
                if (!cancelled) setReadiness(null);
            });

        return () => {
            cancelled = true;
        };
    }, [isFilterApplied, activeTab, submitMonth, submitYear, role, existingSubmission]);

    // The A1 form registers a function that saves its editable Health Facility &
    // Workforce table; it runs right before the report itself is submitted.
    const beforeSubmitRef = useRef<(() => Promise<void>) | null>(null);
    const registerBeforeSubmit = React.useCallback((handler: (() => Promise<void>) | null) => {
        beforeSubmitRef.current = handler;
    }, []);

    const handleSubmitReport = async () => {
        if (!submitMonth || !submitYear) {
            setSubmitReportError('Please select both month and year.');
            return;
        }

        if (hasExistingSubmission) {
            setSubmitReportError('This report has already been submitted for the selected month and year.');
            return;
        }

        setIsSubmittingReport(true);
        setSubmitReportError(null);

        try {
            // Persist any edits (A1 Health Facility & Workforce) before submitting.
            if (activeTab === 'a1' && beforeSubmitRef.current) {
                await beforeSubmitRef.current();
            }

            const response = await axios.post(
                '/qfhsis/public/api/reports/submit-program-report',
                {
                    // Persisted into the submit_program_report table
                    form: activeTab,
                    month: submitMonth,
                    year: submitYear,
                    // Address comes from the applied filter
                    ...getSubmitLocation(),
                },
            );

            if (response.status < 200 || response.status >= 300) {
                throw new Error(response.data?.message || `Request failed with status ${response.status}`);
            }

            setIsSubmitModalOpen(false);
            setExistingSubmission({ status: 'submitted', remarks: null, reviewer: null });
        } catch (err: any) {
            const message =
                err?.response?.data?.message ??
                (err instanceof Error ? err.message : 'Failed to submit report.');
            setSubmitReportError(message);
        } finally {
            setIsSubmittingReport(false);
        }
    };

    const tabs = [
        { id: 'm1', label: 'M1_All Programs' },
        { id: 'q1', label: 'Q1_All Programs' },
        { id: 'm2', label: 'M2_8PAA' },
        { id: 'a1', label: 'A1_All Program' },
        { id: 'mo', label: 'M2_Morbidity' },
    ] as const;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="PHO Reports" />

            {canReviewReports && (
                <ReviewSubmissionsPanel
                    role={role}
                    regions={regions}
                    provinces={provinces}
                    municipalities={municipalities}
                    barangays={barangays}
                />
            )}

            {canSubmitReport && isFilterApplied && (
                <div className="max-w-7xl mx-auto px-6 pt-6 flex flex-col items-end gap-2">
                    {checkingExistingSubmission ? (
                        <span className="text-sm text-gray-400 italic">Checking submission status…</span>
                    ) : existingSubmission && existingSubmission.status === 'confirmed' ? (
                        <span className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 border border-emerald-200">
                            Confirmed{existingSubmission.reviewer ? ` by ${existingSubmission.reviewer}` : ''}
                        </span>
                    ) : existingSubmission && existingSubmission.status === 'submitted' ? (
                        <span className="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-500 border border-gray-200">
                            Already Submitted · awaiting review
                        </span>
                    ) : (
                        <>
                            {existingSubmission && existingSubmission.status === 'rejected' && (
                                <div className="max-w-xl rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
                                    <span className="font-semibold">
                                        Rejected{existingSubmission.reviewer ? ` by ${existingSubmission.reviewer}` : ''}.
                                    </span>{' '}
                                    {existingSubmission.remarks ?? 'No remarks were given.'}
                                    <div className="mt-0.5 text-xs text-red-500">Fix the report and resubmit it.</div>
                                </div>
                            )}
                            {readiness?.applies && !readiness.ready && (
                                <div className="max-w-xl rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">
                                    {readiness.message}
                                    <div className="mt-0.5 text-xs text-amber-600">
                                        {readiness.confirmed} of {readiness.required} confirmed
                                    </div>
                                </div>
                            )}
                            <button
                                type="button"
                                onClick={openSubmitModal}
                                disabled={Boolean(readiness?.applies && !readiness.ready)}
                                className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {existingSubmission?.status === 'rejected' ? 'Resubmit Report' : 'Submit Report'}
                            </button>
                        </>
                    )}
                </div>
            )}

            <div className="max-w-7xl mx-auto px-6 pt-4">
                <motion.nav
                    initial={{ opacity: 0, y: -20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ type: 'spring', stiffness: 200, damping: 25 }}
                    className="flex justify-center p-2 rounded-2xl shadow-lg overflow-hidden"
                    style={{
                        background: 'linear-gradient(160deg, #0f2d6b 0%, #1a5276 25%, #117a65 60%, #0e6655 100%)',
                    }}
                >
                    <div className="flex gap-2 w-full justify-center">
                        {tabs.map((tab) => {
                            const isActive = activeTab === tab.id;
                            return (
                                <button
                                    key={tab.id}
                                    onClick={() => handleTabChange(tab.id)}
                                    className={[
                                        'px-6 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 hover:scale-[1.02]',
                                        isActive
                                            ? 'text-white'
                                            : 'text-white/70 hover:bg-white/10 hover:text-white',
                                    ].join(' ')}
                                    style={isActive ? {
                                        background: 'linear-gradient(90deg, rgba(255,255,255,0.22) 0%, rgba(255,255,255,0.08) 100%)',
                                        boxShadow: 'inset 0 0 0 1px rgba(255,255,255,0.15)',
                                    } : {}}
                                >
                                    {tab.label}
                                </button>
                            );
                        })}
                    </div>
                </motion.nav>
            </div>

            <div className="mt-6 max-w-7xl mx-auto px-6 pb-12">
                <AnimatePresence mode="wait">
                    <motion.div
                        key={activeTab}
                        initial={{ opacity: 0, y: 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -10 }}
                        transition={{ duration: 0.2 }}
                    >
                        {activeTab === 'm1' && (
                            <M1AllPrograms
                                regions={regions}
                                provinces={provinces}
                                municipalities={municipalities}
                                barangays={barangays}
                                onApplyFilter={handleFormFilterApplied}
                            />
                        )}
                        {activeTab === 'q1' && (
                            <Q1AllPrograms
                                regions={regions}
                                provinces={provinces}
                                municipalities={municipalities}
                                barangays={barangays}
                                onApplyFilter={handleFormFilterApplied}
                            />
                        )}
                        {activeTab === 'm2' &&
                            <M28PAA
                                regions={regions}
                                provinces={provinces}
                                municipalities={municipalities}
                                barangays={barangays}
                                onApplyFilter={handleFormFilterApplied}
                            />
                        }
                        {activeTab === 'a1' &&
                            <A1AllPrograms
                                regions={regions}
                                provinces={provinces}
                                municipalities={municipalities}
                                barangays={barangays}
                                onApplyFilter={handleFormFilterApplied}
                                registerSaveHandler={registerBeforeSubmit}
                            />
                        }
                        {activeTab === 'mo' &&
                            <MorbidityPage
                                regions={regions}
                                provinces={provinces}
                                municipalities={municipalities}
                                barangays={barangays}
                                onApplyFilter={handleFormFilterApplied}
                            />
                        }
                    </motion.div>
                </AnimatePresence>
            </div>

            <SubmitReportModal
                isOpen={isSubmitModalOpen}
                month={submitMonth}
                year={submitYear}
                isSubmitting={isSubmittingReport}
                error={submitReportError}
                hasExisting={hasExistingSubmission}
                checkingExisting={checkingExistingSubmission}
                location={submitLocationLabels}
                onMonthChange={setSubmitMonth}
                onYearChange={setSubmitYear}
                onCancel={closeSubmitModal}
                onSubmit={handleSubmitReport}
            />
        </AppLayout>
    );
}