import React from 'react';

export interface TCLColumn {
    key: string;
    label: string;
    width?: string;
}

export interface TCLColumnGroup {
    /** Empty string title = ungrouped column(s), rendered spanning both header rows */
    title: string;
    columns: TCLColumn[];
}

export type TCLRow = Record<string, string>;

export interface TargetClientListTableProps {
    title: string;
    subtitle?: string;
    /** Optional legend / notes box rendered above the table (e.g. code definitions) */
    legend?: React.ReactNode;
    groups: TCLColumnGroup[];
    rows: TCLRow[];
    onChange: (rows: TCLRow[]) => void;
}

export function emptyRow(groups: TCLColumnGroup[]): TCLRow {
    const row: TCLRow = {};
    groups.forEach((g) => g.columns.forEach((c) => (row[c.key] = '')));
    return row;
}

export default function TargetClientListTable({
    title,
    subtitle,
    legend,
    groups,
    rows,
    onChange,
}: TargetClientListTableProps) {
    const flatColumns = groups.flatMap((g) => g.columns);

    const addRow = () => onChange([...rows, emptyRow(groups)]);

    const removeRow = (idx: number) => onChange(rows.filter((_, i) => i !== idx));

    const updateCell = (idx: number, key: string, value: string) => {
        const next = rows.slice();
        next[idx] = { ...next[idx], [key]: value };
        onChange(next);
    };

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 className="text-sm font-semibold text-gray-900">{title}</h3>
                    {subtitle && <p className="text-xs text-gray-500">{subtitle}</p>}
                </div>
                <div className="flex items-center gap-2">
                    <span className="text-xs text-gray-400">{rows.length} client{rows.length === 1 ? '' : 's'}</span>
                    <button
                        onClick={addRow}
                        className="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-blue-700"
                    >
                        + Add Client
                    </button>
                </div>
            </div>

            {legend && (
                <div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    {legend}
                </div>
            )}

            <div className="max-h-[70vh] overflow-auto rounded-lg border border-gray-200">
                <table className="min-w-full border-collapse text-xs">
                    <thead>
                        <tr>
                            <th
                                rowSpan={2}
                                className="sticky left-0 top-0 z-20 border border-gray-200 bg-gray-100 px-2 py-2 text-center font-semibold text-gray-700"
                            >
                                No.
                            </th>
                            {groups.map((g, gi) =>
                                g.title ? (
                                    <th
                                        key={`grp-${gi}`}
                                        colSpan={g.columns.length}
                                        className="sticky top-0 z-10 border border-gray-200 bg-gray-100 px-2 py-2 text-center font-semibold text-gray-700"
                                    >
                                        {g.title}
                                    </th>
                                ) : (
                                    g.columns.map((c) => (
                                        <th
                                            key={c.key}
                                            rowSpan={2}
                                            style={{ minWidth: c.width || '130px' }}
                                            className="sticky top-0 z-10 border border-gray-200 bg-gray-100 px-2 py-2 text-center font-semibold text-gray-700"
                                        >
                                            {c.label}
                                        </th>
                                    ))
                                )
                            )}
                            <th
                                rowSpan={2}
                                className="sticky top-0 z-10 border border-gray-200 bg-gray-100 px-2 py-2 text-center font-semibold text-gray-700"
                            >
                                Actions
                            </th>
                        </tr>
                        <tr>
                            {groups
                                .filter((g) => g.title)
                                .flatMap((g) => g.columns)
                                .map((c) => (
                                    <th
                                        key={c.key}
                                        style={{ minWidth: c.width || '120px' }}
                                        className="sticky top-[33px] z-10 border border-gray-200 bg-gray-50 px-2 py-1.5 text-center font-medium text-gray-600"
                                    >
                                        {c.label}
                                    </th>
                                ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={flatColumns.length + 2}
                                    className="border border-gray-200 px-2 py-10 text-center text-gray-400"
                                >
                                    No client records yet. Click &ldquo;+ Add Client&rdquo; to begin.
                                </td>
                            </tr>
                        ) : (
                            rows.map((row, idx) => (
                                <tr key={idx} className="hover:bg-blue-50/40">
                                    <td className="sticky left-0 z-10 border border-gray-200 bg-white px-2 py-1 text-center text-gray-500">
                                        {idx + 1}
                                    </td>
                                    {flatColumns.map((c) => (
                                        <td key={c.key} className="border border-gray-200 p-0">
                                            <input
                                                value={row[c.key] || ''}
                                                onChange={(e) => updateCell(idx, c.key, e.target.value)}
                                                className="w-full min-w-[100px] bg-transparent px-2 py-1.5 text-xs text-gray-800 outline-none focus:bg-blue-50"
                                            />
                                        </td>
                                    ))}
                                    <td className="border border-gray-200 px-2 py-1 text-center">
                                        <button
                                            onClick={() => removeRow(idx)}
                                            className="text-xs text-red-500 hover:text-red-700"
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
