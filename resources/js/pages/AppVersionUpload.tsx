import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Download, FileUp, History, Smartphone, UploadCloud, X } from 'lucide-react';
import { useRef, useState } from 'react';

interface AppVersion {
    id: number;
    version_name: string;
    download_url: string;
    file_size: number;
    checksum: string | null;
    uploaded_at: string | null;
    uploaded_by: string | null;
}

interface AppVersionUploadProps {
    versions: AppVersion[];
}

type FlashProps = { flash?: { success?: string | null; error?: string | null } };

const BASE = '/qfhsis/public/fhsis/app-version';
const MAX_BYTES = 40 * 1024 * 1024; // matches the server-side 40 MB limit

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'FHSIS Dashboard', href: '/qfhsis/public/fhsis/dashboard' },
    { title: 'App Version', href: BASE },
];

const formatSize = (bytes: number) => {
    if (!bytes) return '—';
    const mb = bytes / (1024 * 1024);
    return mb >= 1 ? `${mb.toFixed(2)} MB` : `${(bytes / 1024).toFixed(0)} KB`;
};

const formatDate = (iso: string | null) =>
    iso
        ? new Date(iso).toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })
        : '—';

export default function AppVersionUpload({ versions }: AppVersionUploadProps) {
    const { flash } = usePage<FlashProps>().props;
    const fileInput = useRef<HTMLInputElement>(null);
    const [dragOver, setDragOver] = useState(false);
    const [clientError, setClientError] = useState<string | null>(null);

    const { data, setData, post, processing, progress, errors, reset, clearErrors } = useForm<{
        version_name: string;
        apk: File | null;
    }>({
        version_name: '',
        apk: null,
    });

    const latest = versions[0];

    const pickFile = (file: File | null | undefined) => {
        setClientError(null);
        clearErrors('apk');
        if (!file) return;
        if (!file.name.toLowerCase().endsWith('.apk')) {
            setClientError('Please choose an .apk file.');
            return;
        }
        if (file.size > MAX_BYTES) {
            setClientError('The APK must not be larger than 40 MB.');
            return;
        }
        setData('apk', file);
    };

    const clearFile = () => {
        setData('apk', null);
        if (fileInput.current) fileInput.current.value = '';
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!data.apk) {
            setClientError('Please choose an .apk file.');
            return;
        }
        post(BASE, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                if (fileInput.current) fileInput.current.value = '';
            },
        });
    };

    const apkError = clientError ?? errors.apk;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="App Version" />

            <div className="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 bg-neutral-50 min-h-screen text-slate-800 antialiased">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl border border-slate-100 shadow-sm mb-6">
                    <div>
                        <div className="flex items-center gap-2 text-blue-600 font-semibold text-sm tracking-wide uppercase">
                            <Smartphone className="w-4 h-4" />
                            <span>Mobile Application</span>
                        </div>
                        <h1 className="text-2xl font-bold text-slate-900 tracking-tight mt-1">App Version</h1>
                        <p className="text-slate-500 text-xs mt-0.5">Upload a new Android APK and publish its version to the mobile app</p>
                    </div>

                    <div className="rounded-xl bg-emerald-50 border border-emerald-100 px-4 py-2.5 self-stretch sm:self-auto">
                        <div className="text-emerald-700 text-[11px] font-bold uppercase tracking-wider">Current version</div>
                        <div className="text-2xl font-bold text-emerald-800 leading-tight">{latest ? latest.version_name : '—'}</div>
                    </div>
                </div>

                {/* Flash messages */}
                {flash?.success && (
                    <div className="mb-4 flex items-center gap-2 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm font-medium text-emerald-800">
                        <CheckCircle2 className="w-4 h-4 shrink-0" />
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-4 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm font-medium text-rose-700">{flash.error}</div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-5 gap-6">
                    {/* Upload form */}
                    <form onSubmit={submit} className="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col gap-5 self-start">
                        <div className="flex items-center gap-2 font-semibold text-slate-900">
                            <UploadCloud className="w-5 h-5 text-blue-600" />
                            Upload new version
                        </div>

                        <div>
                            <label htmlFor="version_name" className="block text-xs font-semibold text-slate-600 mb-1.5">
                                Version name
                            </label>
                            <input
                                id="version_name"
                                type="text"
                                placeholder={latest ? `e.g. newer than ${latest.version_name}` : 'e.g. 1.0.0'}
                                value={data.version_name}
                                onChange={(e) => setData('version_name', e.target.value)}
                                className="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl px-4 py-2 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition"
                            />
                            {errors.version_name ? (
                                <p className="mt-1 text-xs text-rose-600">{errors.version_name}</p>
                            ) : (
                                <p className="mt-1 text-[11px] text-slate-400">Use numbers like 1.0.4 — the app compares versions numerically.</p>
                            )}
                        </div>

                        <div>
                            <span className="block text-xs font-semibold text-slate-600 mb-1.5">APK file</span>
                            {data.apk ? (
                                <div className="flex items-center gap-3 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3">
                                    <FileUp className="w-5 h-5 text-blue-600 shrink-0" />
                                    <div className="min-w-0 flex-1">
                                        <div className="text-sm font-medium text-slate-800 truncate">{data.apk.name}</div>
                                        <div className="text-xs text-slate-500">{formatSize(data.apk.size)}</div>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={clearFile}
                                        disabled={processing}
                                        className="rounded-lg p-1 text-slate-400 hover:bg-white hover:text-slate-600 disabled:opacity-50"
                                        aria-label="Remove file"
                                    >
                                        <X className="w-4 h-4" />
                                    </button>
                                </div>
                            ) : (
                                <label
                                    onDragOver={(e) => {
                                        e.preventDefault();
                                        setDragOver(true);
                                    }}
                                    onDragLeave={() => setDragOver(false)}
                                    onDrop={(e) => {
                                        e.preventDefault();
                                        setDragOver(false);
                                        pickFile(e.dataTransfer.files?.[0]);
                                    }}
                                    className={`flex cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed px-4 py-8 text-center transition ${
                                        dragOver ? 'border-blue-400 bg-blue-50' : 'border-slate-200 bg-slate-50 hover:border-blue-300'
                                    }`}
                                >
                                    <UploadCloud className="w-8 h-8 text-slate-400" />
                                    <span className="text-sm font-medium text-slate-600">Click or drag an .apk file here</span>
                                    <span className="text-[11px] text-slate-400">Maximum 40 MB</span>
                                    <input
                                        ref={fileInput}
                                        type="file"
                                        accept=".apk,application/vnd.android.package-archive"
                                        className="hidden"
                                        onChange={(e) => pickFile(e.target.files?.[0])}
                                    />
                                </label>
                            )}
                            {apkError && <p className="mt-1 text-xs text-rose-600">{apkError}</p>}
                        </div>

                        {processing && progress && (
                            <div>
                                <div className="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                    <div className="h-full bg-blue-600 transition-all" style={{ width: `${progress.percentage ?? 0}%` }} />
                                </div>
                                <p className="mt-1 text-[11px] text-slate-500">Uploading… {progress.percentage ?? 0}%</p>
                            </div>
                        )}

                        <button
                            type="submit"
                            disabled={processing || !data.version_name.trim() || !data.apk}
                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <UploadCloud className="w-4 h-4" />
                            {processing ? 'Uploading…' : 'Upload version'}
                        </button>

                        <p className="text-[11px] text-slate-400">
                            The new APK replaces the current download. Phones checking <code>/api/app-version/latest</code> will be offered the update.
                        </p>
                    </form>

                    {/* History */}
                    <div className="lg:col-span-3 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden self-start">
                        <div className="flex items-center gap-2 px-6 py-4 border-b border-slate-100 font-semibold text-slate-900">
                            <History className="w-5 h-5 text-blue-600" />
                            Version history
                        </div>

                        {versions.length === 0 ? (
                            <div className="p-12 text-center text-sm text-slate-400">
                                <Smartphone className="w-10 h-10 mx-auto text-slate-300 mb-2" />
                                <p className="font-semibold text-slate-600">No versions uploaded yet</p>
                                <p className="text-xs">Upload the first APK to publish it to the mobile app.</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                        <tr>
                                            <th className="px-6 py-3">Version</th>
                                            <th className="px-6 py-3">Size</th>
                                            <th className="px-6 py-3">Uploaded</th>
                                            <th className="px-6 py-3">By</th>
                                            <th className="px-6 py-3" />
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {versions.map((v, i) => (
                                            <tr key={v.id} className="text-slate-700">
                                                <td className="px-6 py-3 font-semibold text-slate-900 whitespace-nowrap">
                                                    {v.version_name}
                                                    {i === 0 && (
                                                        <span className="ml-2 rounded-full bg-emerald-50 border border-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700">
                                                            Current
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-3 whitespace-nowrap">{formatSize(v.file_size)}</td>
                                                <td className="px-6 py-3 whitespace-nowrap">{formatDate(v.uploaded_at)}</td>
                                                <td className="px-6 py-3">{v.uploaded_by ?? '—'}</td>
                                                <td className="px-6 py-3 text-right">
                                                    {i === 0 && (
                                                        <a
                                                            href={v.download_url}
                                                            className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-blue-600 hover:bg-blue-50"
                                                        >
                                                            <Download className="w-3.5 h-3.5" />
                                                            Download
                                                        </a>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {versions.length > 1 && (
                            <p className="px-6 py-3 border-t border-slate-100 text-[11px] text-slate-400">
                                Only the current APK is kept on the server; older entries are kept for reference.
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
