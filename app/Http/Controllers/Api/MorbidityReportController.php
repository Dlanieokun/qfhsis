<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MorbidityRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     *   year (required, YYYY), month (optional month NAME, e.g. "August" — omit for the whole year),
     *   region, province, municipality (optional codes), barangay[] (optional codes).
     * The page sends location CODES, but morbidity_records stores the place DESCRIPTIONS
     * (what the Android form saves), so codes are translated to descriptions before filtering.
     *
     * Response: { "data": { "<icdCode>::<diseaseName>": { "days_0_6": {"male": 0, "female": 0}, ... }, ... } }
     * The key matches morbidityRowKey(icd, name) in MorbidityPage.tsx exactly, now that
     * diseaseName is a real column — so the one pair of rows that share ICD code A09.0
     * no longer collide.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $this->validateFilters($request);

        return response()->json(['data' => $this->buildReportData($validated, $request)]);
    }

    /**
     * GET /fhsis/reports/export-m2-morbidity  (same query params as index())
     *
     * Fills the official M2_Morbidity.xlsx template with the filtered report and streams it as a
     * download — the "Download M2_Morbidity.xlsx" button in MorbidityPage.tsx links here.
     *
     * Requires phpoffice/phpspreadsheet and the blank template saved at
     * storage/app/templates/M2_Morbidity.xlsx (or public/templates/M2_Morbidity.xlsx).
     */
    public function export(Request $request): StreamedResponse
    {
        $validated = $this->validateFilters($request);
        $data = $this->buildReportData($validated, $request);
        $location = $this->resolveLocation($validated);

        $templatePath = null;
        foreach ([
            storage_path('app/templates/M2_Morbidity.xlsx'),
            public_path('templates/M2_Morbidity.xlsx'),
            resource_path('templates/M2_Morbidity.xlsx'),
        ] as $candidate) {
            if (is_file($candidate)) {
                $templatePath = $candidate;
                break;
            }
        }
        abort_if($templatePath === null, 500, 'M2_Morbidity.xlsx template not found (expected in storage/app/templates).');

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getSheetByName('M2_Morbidity') ?? $spreadsheet->getActiveSheet();

        // Form header — these cells hold "______" placeholders in the template.
        $user = $request->user();
        $sheet->setCellValue('O2', trim(($validated['month'] ?? '') . ' ' . $validated['year']));
        $sheet->setCellValue('O3', (string) ($user->facility_name ?? ''));
        $sheet->setCellValue('O4', implode(', ', $location['barangay']));
        $sheet->setCellValue('O5', (string) ($location['municipality'] ?? ''));
        $sheet->setCellValue('O6', (string) ($location['province'] ?? ''));
        $sheet->setCellValue('O7', '');

        // Data rows start at row 11. A row is matched by its "<ICD>::<Disease>" text — the same key
        // the report API returns. Columns: C.. = Male/Female/Total per age group (16 x 3 = C..AX),
        // then AY/AZ/BA = Grand Total Male / Female / Both Sexes.
        $ageKeys = array_keys(self::AGE_GROUPS);
        $put = function (int $col, int $row, int $value) use ($sheet): void {
            if ($value > 0) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . $row, $value);
            }
        };

        $lastRow = $sheet->getHighestDataRow();
        for ($row = 11; $row <= $lastRow; $row++) {
            $name = trim((string) $sheet->getCell("A{$row}")->getValue());
            $icd = trim((string) $sheet->getCell("B{$row}")->getValue());
            $key = "{$icd}::{$name}";
            if (!isset($data[$key])) {
                continue;
            }

            $grandMale = 0;
            $grandFemale = 0;
            foreach ($ageKeys as $i => $ageKey) {
                $male = (int) ($data[$key][$ageKey]['male'] ?? 0);
                $female = (int) ($data[$key][$ageKey]['female'] ?? 0);
                $col = 3 + $i * 3;
                $put($col, $row, $male);
                $put($col + 1, $row, $female);
                $put($col + 2, $row, $male + $female);
                $grandMale += $male;
                $grandFemale += $female;
            }
            $put(51, $row, $grandMale);
            $put(52, $row, $grandFemale);
            $put(53, $row, $grandMale + $grandFemale);
        }

        return response()->streamDownload(
            function () use ($spreadsheet) {
                (new Xlsx($spreadsheet))->save('php://output');
            },
            'M2_Morbidity.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'year' => ['required', 'digits:4'],
            'month' => ['nullable', 'string'], // month NAME, e.g. "August" — matches reportMonth
            'region' => ['nullable', 'string'],
            'province' => ['nullable', 'string'],
            'municipality' => ['nullable', 'string'],
            'barangay' => ['nullable', 'array'],
            'barangay.*' => ['string'],
        ]);
    }

    /**
     * The filter panel sends reference-table codes (regCode, provCode, citymunCode, brgyCode);
     * the rows store the descriptions. Look each code up, falling back to the raw value so a
     * description passed directly still works.
     *
     * @return array{region: ?string, province: ?string, municipality: ?string, barangay: string[]}
     */
    private function resolveLocation(array $validated): array
    {
        $location = ['region' => null, 'province' => null, 'municipality' => null, 'barangay' => []];

        $lookups = [
            'region'       => ['regions', 'regCode', 'regDesc'],
            'province'     => ['provinces', 'provCode', 'provDesc'],
            'municipality' => ['municipalities', 'citymunCode', 'citymunDesc'],
        ];
        foreach ($lookups as $field => [$table, $codeCol, $descCol]) {
            if (!empty($validated[$field])) {
                $location[$field] = DB::table($table)->where($codeCol, $validated[$field])->value($descCol)
                    ?? $validated[$field];
            }
        }

        if (!empty($validated['barangay'])) {
            $codes = $validated['barangay'];
            $descs = DB::table('barangays')->whereIn('brgyCode', $codes)->pluck('brgyDesc')->all();
            $location['barangay'] = !empty($descs) ? $descs : $codes;
        }

        return $location;
    }

    /** @return array<string, array<string, array{male: int, female: int}>> keyed "<icd>::<disease>" */
    private function buildReportData(array $validated, Request $request): array
    {
        $query = MorbidityRecord::query()->where('reportYear', $validated['year']);

        if (!empty($validated['month'])) {
            $query->where('reportMonth', $validated['month']);
        }

        $location = $this->resolveLocation($validated);
        foreach (['region', 'province', 'municipality'] as $field) {
            if ($location[$field] !== null) {
                $query->where($field, $location[$field]);
            }
        }
        if (!empty($location['barangay'])) {
            $query->whereIn('barangay', $location['barangay']);
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

        return $data;
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