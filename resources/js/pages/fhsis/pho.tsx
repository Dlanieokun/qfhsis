import React, { useState, useEffect } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
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

// ─── Form Data Shapes ────────────────────────────────────────────────────────
interface FamilyPlanningBrackets {
    '10-14': number;
    '15-19': number;
    '20-49': number;
    total: number;
}
interface FamilyPlanningData {
    demandSatisfied: FamilyPlanningBrackets;
    currentUsersByMethod: Record<string, FamilyPlanningBrackets>;
}

interface AgeBrackets { '10-14': number; '15-19': number; '20-49': number; total: number; }
interface SexBrackets { male: number; female: number; total: number; }

interface MaternalCareData {
    prenatal: Record<string, AgeBrackets>;
    intrapartum: Record<string, SexBrackets>;
    postpartum: Record<string, AgeBrackets>;
}

interface ChildCareData {
    imm0_11: Record<string, SexBrackets>;
    immPrev: Record<string, SexBrackets>;
    schoolImm: Record<string, SexBrackets>;
    nutrition: Record<string, SexBrackets>;
    nutrition2: Record<string, SexBrackets>;
    mgmtSick: Record<string, SexBrackets>;
}

interface OralHealthData {
    infantFirstVisit: SexBrackets;
    firstVisit: Record<string, SexBrackets>;
    firstVisitFacility: Record<string, SexBrackets>;
    firstVisitNonFacility: Record<string, SexBrackets>;
    completed2Visits: Record<string, SexBrackets>;
    completed2VisitsFacility: Record<string, SexBrackets>;
    completed2VisitsNonFacility: Record<string, SexBrackets>;
}

interface CervicalCancerTotals {
    screened: number; via: number; papSmear: number; hpvDna: number; assessedOnly: number;
    suspicious: number; linkedToCare: number; linkedTreated: number; linkedReferred: number;
}
interface BreastCancerTotals {
    seen: number; highRiskOrSymptomatic: number; providedCbe: number; providedMammogram: number;
    remarkableCbe: number; remarkableMammogram: number; linkedToCare: number; asymptomaticScreened: number;
}
interface NonCommunicableDiseaseData {
    lifestyle2059: Record<string, SexBrackets>;
    lifestyle60plus: Record<string, SexBrackets>;
    cvd2059: SexBrackets;
    cvd60plus: SexBrackets;
    dm2059: SexBrackets;
    dm60plus: SexBrackets;
    blindness: Record<string, SexBrackets>;
    mentalHealth: Record<string, SexBrackets>;
    cervical: CervicalCancerTotals;
    breast: BreastCancerTotals;
}

interface EnvironmentalHealthData {
    water: { levelI: number; levelII: number; levelIII: number; safelyManaged: number; total: number };
    sanitation: {
        pourFlushSeptic: number; pourFlushSewer: number; vip: number;
        basicSanitationFacility: number; safelyManagedSanitation: number; total: number;
    };
}

interface InfectiousDiseaseData {
    filariasis: Record<string, SexBrackets>;
    rabies: Record<string, SexBrackets>;
    schistosomiasis: Record<string, SexBrackets>;
    sth: Record<string, SexBrackets>;
    leprosy: Record<string, SexBrackets>;
}

interface PhoPageProps {
    familyPlanning?: FamilyPlanningData;
    maternalCare?: MaternalCareData;
    childCare?: ChildCareData;
    oralHealth?: OralHealthData;
    nonCommunicableDisease?: NonCommunicableDiseaseData;
    environmentalHealth?: EnvironmentalHealthData;
    infectiousDisease?: InfectiousDiseaseData;
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

// ─── Submit Report Modal ──────────────────────────────────────────────────────
interface SubmitReportModalProps {
    isOpen: boolean;
    month: string;
    year: string;
    isSubmitting: boolean;
    error: string | null;
    hasExisting: boolean;
    checkingExisting: boolean;
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

    const handleTabChange = (tab: typeof activeTab) => {
        setActiveTab(tab);
        setIsFilterApplied(tab === 'mo');
        setSubmitMonth('');
        setHasExistingSubmission(false);
    };

    // ─── Submit Report modal state ───────────────────────────────────────────
    const [isSubmitModalOpen, setIsSubmitModalOpen] = useState(false);
    const [submitMonth, setSubmitMonth] = useState('');
    const [submitYear, setSubmitYear] = useState(String(new Date().getFullYear()));
    const [isSubmittingReport, setIsSubmittingReport] = useState(false);
    const [submitReportError, setSubmitReportError] = useState<string | null>(null);

    // Called by whichever form is active when its own "Apply Filter(s)"
    // button is clicked — reveals the Submit button and pre-fills the
    // modal's month/year with whatever period the user just filtered by.
    const handleFormFilterApplied = (month: string, year: string) => {
        setIsFilterApplied(true);
        if (month) setSubmitMonth(month);
        if (year) setSubmitYear(year);
    };

    // Whether submit_program_report already has a row for this
    // form + month + year (the Submit button only shows when it doesn't).
    const [hasExistingSubmission, setHasExistingSubmission] = useState(false);
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
            setHasExistingSubmission(false);
            return;
        }

        let cancelled = false;
        setCheckingExistingSubmission(true);

        const timer = setTimeout(async () => {
            try {
                const response = await axios.get('/qfhsis/public/api/reports/submit-program-report', {
                    params: { form: activeTab, month: submitMonth, year: submitYear },
                });
                if (cancelled) return;
                const total = response.data?.data?.total ?? response.data?.data?.data?.length ?? 0;
                setHasExistingSubmission(total > 0);
            } catch {
                if (!cancelled) setHasExistingSubmission(false);
            } finally {
                if (!cancelled) setCheckingExistingSubmission(false);
            }
        }, 350);

        return () => {
            cancelled = true;
            clearTimeout(timer);
        };
    }, [isFilterApplied, activeTab, submitMonth, submitYear]);

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
            const response = await axios.post(
                '/qfhsis/public/api/reports/submit-program-report',
                {
                    // Persisted into the submit_program_report table
                    form: activeTab,
                    month: submitMonth,
                    year: submitYear,
                    region_code: user?.region_code ?? '',
                    province_code: user?.province_code ?? '',
                    municipality_code: user?.municipality_code ?? '',
                    barangay_codes: parseArray(user?.barangay_codes),
                },
            );

            if (response.status < 200 || response.status >= 300) {
                throw new Error(response.data?.message || `Request failed with status ${response.status}`);
            }

            setIsSubmitModalOpen(false);
            setHasExistingSubmission(true);
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

            <div className="max-w-7xl mx-auto px-6 pt-6 flex justify-end">
                {isFilterApplied && (
                    checkingExistingSubmission ? (
                        <span className="text-sm text-gray-400 italic">Checking submission status…</span>
                    ) : hasExistingSubmission ? (
                        <span className="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-500 border border-gray-200">
                            Already Submitted
                        </span>
                    ) : (
                        <button
                            type="button"
                            onClick={openSubmitModal}
                            className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow transition hover:bg-emerald-700"
                        >
                            Submit Report
                        </button>
                    )
                )}
            </div>

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
                                onApplyFilter={handleFormFilterApplied}
                            />
                        }
                        {activeTab === 'mo' && 
                            <MorbidityPage
                                // regions={regions}
                                // provinces={provinces}
                                // municipalities={municipalities}
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
                onMonthChange={setSubmitMonth}
                onYearChange={setSubmitYear}
                onCancel={closeSubmitModal}
                onSubmit={handleSubmitReport}
            />
        </AppLayout>
    );
}