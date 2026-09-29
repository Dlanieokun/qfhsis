<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MorbidityRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MorbidityReportController extends Controller
{
    /**
     * Age-bracket -> [male column, female column], in the same left-to-right
     * order as AGE_GROUPS in resources/js/Pages/.../MorbidityPage.tsx.
     */
    private const AGE_GROUPS = [
        'days_0_6'     => ['age0to6daysMale', 'age0to6daysFemale'],
        'days_7_28'    => ['age7to28daysMale', 'age7to28daysFemale'],
        'days_29_11mo' => ['age29daysto11moMale', 'age29daysto11moFemale'],
        'years_1_4'    => ['age1to4yrsMale', 'age1to4yrsFemale'],
        'years_5_9'    => ['age5to9yrsMale', 'age5to9yrsFemale'],
        'years_10_14'  => ['age10to14yrsMale', 'age10to14yrsFemale'],
        'years_15_19'  => ['age15to19yrsMale', 'age15to19yrsFemale'],
        'years_20_24'  => ['age20to24yrsMale', 'age20to24yrsFemale'],
        'years_25_29'  => ['age25to29yrsMale', 'age25to29yrsFemale'],
        'years_30_34'  => ['age30to34yrsMale', 'age30to34yrsFemale'],
        'years_35_39'  => ['age35to39yrsMale', 'age35to39yrsFemale'],
        'years_40_44'  => ['age40to44yrsMale', 'age40to44yrsFemale'],
        'years_45_49'  => ['age45to49yrsMale', 'age45to49yrsFemale'],
        'years_50_54'  => ['age50to54yrsMale', 'age50to54yrsFemale'],
        'years_55_59'  => ['age55to59yrsMale', 'age55to59yrsFemale'],
        'years_60_up'  => ['age60plusMale', 'age60plusFemale'],
    ];

    /**
     * GET /api/reports/m2-morbidity
     *
     * Powers the filter panel in MorbidityPage.tsx. Query params:
     *   year (required, YYYY), month (optional, 1-12 — omit for the whole year),
     *   region, province, municipality (optional codes), barangay[] (optional codes).
     *
     * Response: { "data": { "<icdCode>::<diseaseName>": { "days_0_6": {"male": 0, "female": 0}, ... }, ... } }
     * The key matches morbidityRowKey(icd, name) in MorbidityPage.tsx exactly, now that
     * diseaseName is a real column — so the one pair of rows that share ICD code A09.0
     * no longer collide.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'digits:4'],
            'month' => ['nullable', 'string'], // month NAME, e.g. "August" — matches reportMonth
            'region' => ['nullable', 'string'],
            'province' => ['nullable', 'string'],
            'municipality' => ['nullable', 'string'],
            'barangay' => ['nullable', 'array'],
            'barangay.*' => ['string'],
        ]);

        $query = MorbidityRecord::query()->where('reportYear', $validated['year']);

        if (!empty($validated['month'])) {
            $query->where('reportMonth', $validated['month']);
        }

        foreach (['region', 'province', 'municipality'] as $field) {
            if (!empty($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }

        if (!empty($validated['barangay'])) {
            $query->whereIn('barangay', $validated['barangay']);
        }

        // Facility-level users only ever see their own submissions.
        $user = $request->user();
        if ($user && !in_array($user->role, ['Administrator', 'DOH'], true)) {
            $query->where('user_id', $user->id);
        }

        $sumSelects = [];
        foreach (self::AGE_GROUPS as [$maleCol, $femaleCol]) {
            $sumSelects[] = "SUM($maleCol) as $maleCol";
            $sumSelects[] = "SUM($femaleCol) as $femaleCol";
        }

        $rows = $query
            ->selectRaw('icdCode, diseaseName, ' . implode(', ', $sumSelects))
            ->groupBy('icdCode', 'diseaseName')
            ->get();

        $data = [];
        foreach ($rows as $row) {
            $icd = $row->icdCode ?? '';
            $name = $row->diseaseName ?? '';
            if ($icd === '' && $name === '') {
                continue;
            }

            $bracket = [];
            foreach (self::AGE_GROUPS as $key => [$maleCol, $femaleCol]) {
                $bracket[$key] = [
                    'male' => (int) $row->{$maleCol},
                    'female' => (int) $row->{$femaleCol},
                ];
            }
            $data["{$icd}::{$name}"] = $bracket;
        }

        return response()->json(['data' => $data]);
    }

    /**
     * POST /api/reports/m2-morbidity
     *
     * Bulk-saves one facility's monthly submission (one row per ICD code, upserted
     * by user + month + location + icdCode so re-submitting the same month updates it).
     *
     * Expected payload:
     * {
     *   "reportMonth": "August", "reportYear": "2026",
     *   "region": "...", "province": "...", "municipality": "...", "barangay": "...",
     *   "rows": [
     *     { "icdCode": "A00", "diseaseName": "Cholera", "age0to6daysMale": 0, "age0to6daysFemale": 0, ... },
     *     ...
     *   ]
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $ageColumns = [];
        foreach (self::AGE_GROUPS as [$maleCol, $femaleCol]) {
            $ageColumns[] = $maleCol;
            $ageColumns[] = $femaleCol;
        }

        $validated = $request->validate([
            'reportMonth' => ['required', 'string'],
            'reportYear' => ['required', 'digits:4'],
            'region' => ['nullable', 'string'],
            'province' => ['nullable', 'string'],
            'municipality' => ['nullable', 'string'],
            'barangay' => ['nullable', 'string'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.icdCode' => ['required', 'string'],
            'rows.*.diseaseName' => ['required', 'string'],
            ...array_fill_keys(
                array_map(fn ($col) => "rows.*.$col", $ageColumns),
                ['nullable', 'integer', 'min:0']
            ),
        ]);

        $user = $request->user();

        DB::transaction(function () use ($validated, $ageColumns, $user) {
            foreach ($validated['rows'] as $row) {
                $keyAttributes = [
                    'user_id' => $user->id,
                    'reportMonth' => $validated['reportMonth'],
                    'reportYear' => $validated['reportYear'],
                    'region' => $validated['region'] ?? null,
                    'province' => $validated['province'] ?? null,
                    'municipality' => $validated['municipality'] ?? null,
                    'barangay' => $validated['barangay'] ?? null,
                    'icdCode' => $row['icdCode'],
                    'diseaseName' => $row['diseaseName'],
                ];

                $counts = [];
                foreach ($ageColumns as $col) {
                    $counts[$col] = (int) ($row[$col] ?? 0);
                }

                MorbidityRecord::updateOrCreate($keyAttributes, $counts);
            }
        });

        return response()->json(['message' => 'Morbidity report saved.'], 201);
    }

    /**
     * DELETE /api/reports/m2-morbidity/{morbidityRecord}
     */
    public function destroy(Request $request, MorbidityRecord $morbidityRecord): JsonResponse
    {
        $user = $request->user();
        if ($user && !in_array($user->role, ['Administrator', 'DOH'], true) && $morbidityRecord->user_id !== $user->id) {
            abort(403);
        }

        $morbidityRecord->delete();

        return response()->json(['message' => 'Record deleted.']);
    }
}