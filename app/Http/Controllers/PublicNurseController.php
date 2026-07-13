<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds the data for the "Public Health Nurse" FHSIS dashboard
 * (resources/js/pages/fhsis/PublicNurse.tsx and its nurse-page/* children).
 *
 * Known schema gaps (documented inline where they matter):
 *  - family_planning_records has no client name column; name is pulled
 *    from household_profiles via profileId.
 *  - environmental_health_records has no arsenic testing columns, and does
 *    not record which specific sanitary toilet sub-type (septic / sewer /
 *    VIP) was installed — only pass/fail flags. Those UI fields are left
 *    blank because the data simply isn't captured yet.
 *  - A handful of leprosy/schistosomiasis columns are stored as free-text
 *    strings even though the frontend expects small integer enums; they are
 *    cast defensively below.
 */
class PublicNurseController extends Controller
{
    public function validateReport(Request $request)
    {
        $request->validate([
            'month' => 'required|string',
            'year'  => 'required|string',
        ]);

        // Gather the raw codes from the frontend payload
        $regCode  = $request->input('region');
        $provCode = $request->input('province');
        $munCode  = $request->input('municipality');

        // Resolve the descriptive name for the Region
        $regionName = null;
        if ($regCode) {
            $regionName = DB::table('regions')
                ->where('regCode', $regCode)
                ->value('regDesc');
        }

        // Resolve the descriptive name for the Province
        $provinceName = null;
        if ($provCode) {
            $provinceName = DB::table('provinces')
                ->where('provCode', $provCode)
                ->value('provDesc');
        }

        // Resolve the descriptive name for the Municipality
        $municipalityName = null;
        if ($munCode) {
            $municipalityName = DB::table('municipalities')
                ->where('citymunCode', $munCode)
                ->value('citymunDesc');
        }

        // Persist or update the report entry using text labels
        DB::table('submit_reports')->updateOrInsert(
            [
                'user_id'      => Auth::id(),
                'month'        => $request->input('month'),
                'year'         => $request->input('year'),
                'region'       => $regionName,        // Saves e.g., "REGION VIII"
                'province'     => $provinceName,      // Saves e.g., "LEYTE"
                'municipality' => $municipalityName,  // Saves e.g., "PALO"
            ],
            [
                'updated_at'   => now(),
                'created_at'   => now(),
            ]
        );

        return redirect()->back()->with('success', 'Report successfully validated and saved with description text.');
    }

    public function publicNurse(Request $request): Response
    {
        $month = $request->query('month') ?: now()->format('m');
        $year = $request->query('year') ?: now()->format('Y');
        
        // Retrieve active location filters from query parameters
        $regCode = $request->query('region');
        $provCode = $request->query('province');
        $citymunCode = $request->query('municipality');
        $brgyCode = $request->query('barangay');

        // Default configuration for Region VIII and Leyte if not provided
        if (!$request->has('region')) {
            $defaultRegion = DB::table('regions')->where('regDesc', 'like', '%VIII%')->first();
            $regCode = $defaultRegion ? $defaultRegion->regCode : null;

            if ($regCode) {
                $defaultProv = DB::table('provinces')
                    ->where('regCode', $regCode)
                    ->where('provDesc', 'like', '%LEYTE%')
                    ->where('provDesc', 'not like', '%SOUTHERN%')
                    ->first();
                $provCode = $defaultProv ? $defaultProv->provCode : null;
            }
        }

        // Fetch dynamic selection choices
        $regions = DB::table('regions')->select('regCode', 'regDesc')->get();
        $provinces = $regCode ? DB::table('provinces')->where('regCode', $regCode)->select('provCode', 'provDesc')->get() : collect();
        $municipalities = $provCode ? DB::table('municipalities')->where('provCode', $provCode)->select('citymunCode', 'citymunDesc')->get() : collect();
        $barangays = $citymunCode ? DB::table('barangays')->where('citymunCode', $citymunCode)->select('brgyCode', 'brgyDesc')->get() : collect();

        // Check descriptions to match against text columns in submit_reports table
        $regionDesc = $regCode ? DB::table('regions')->where('regCode', $regCode)->value('regDesc') : null;
        $provinceDesc = $provCode ? DB::table('provinces')->where('provCode', $provCode)->value('provDesc') : null;
        $municipalityDesc = $citymunCode ? DB::table('municipalities')->where('citymunCode', $citymunCode)->value('citymunDesc') : null;

        // Check if a entry already exists for this exact filter configuration
        $isValidated = DB::table('submit_reports')
            ->where('month', $month)
            ->where('year', $year)
            ->where('region', $regionDesc)
            ->where('province', $provinceDesc)
            ->where('municipality', $municipalityDesc)
            ->exists();

        return Inertia::render('fhsis/PublicNurse', [
            'familyPlanning' => $this->familyPlanning($month, $year),
            'maternalCare' => $this->maternalCare($month, $year),
            'childCare' => $this->childCare($month, $year),
            'oralHealth' => $this->oralHealth($month, $year),
            'nonCommunicableDisease' => $this->nonCommunicableDisease($month, $year),
            'geriatricHealth' => $this->geriatricHealth($month, $year),
            'infectiousDisease' => $this->infectiousDisease($month, $year),
            'wash' => $this->wash(),
            'regions' => $regions,
            'provinces' => $provinces,
            'municipalities' => $municipalities,
            'barangays' => $barangays,
            'isValidated' => $isValidated, // Pass boolean flag to frontend
            'filters' => [
                'month' => $month,
                'year' => $year,
                'region' => $regCode,
                'province' => $provCode,
                'municipality' => $citymunCode,
                'barangay' => $brgyCode,
            ],
        ]);
    }

    /* =====================================================================
     * M1 - All Programs (consolidated xlsx export)
     * ===================================================================*/

    /**
     * Generates "M1_All_Programs.xlsx" by loading the actual official M1
     * template (resources/templates/M1_All_Programs.xlsx — the same layout,
     * labels, merges, and SUM formulas as the paper/DOH form) and filling in
     * only the raw input cells with data from PhoController@getM1ReportData.
     *
     * We deliberately do NOT build the sheet from scratch: the template is
     * the source of truth for layout, so every value is written into the
     * exact cell the real form expects, and any cell that already contains
     * a formula (subtotal/grand-total rollups baked into the template) is
     * left untouched — Excel recalculates those automatically when the file
     * is opened. See put()/putRow() below.
     *
     * Indicators the app doesn't yet capture (schema gaps documented in
     * PhoController, e.g. HIV-AIDS/STI, Vital Statistics, resident vs.
     * TRANS-IN/OUT splits) are simply never written to, so they stay blank
     * exactly as in the blank template.
     */
    public function m1AllDownload(Request $request): StreamedResponse
    {
        $data = app(PhoController::class)->getM1ReportData();

        $templatePath = resource_path('templates/M1_All_Programs.xlsx');
        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getSheetByName('M1_All Programs') ?? $spreadsheet->getActiveSheet();

        $this->fillHeader($sheet, $request->query('month'), $request->query('year'));
        $this->fillSectionA($sheet, $data['familyPlanning']);
        $this->fillSectionB($sheet, $data['maternalCare']);
        $this->fillSectionC($sheet, $data['childCare']);
        $this->fillSectionD($sheet, $data['oralHealth']);
        $this->fillSectionE($sheet, $data['nonCommunicableDisease']);
        $this->fillSectionF($sheet, $data['environmentalHealth']);
        $this->fillSectionG($sheet, $data['infectiousDisease']);

        $filename = 'M1_All_Programs_' . now()->format('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /* ---- template cover-page header -------------------------------------- */

    private function fillHeader(Worksheet $sheet, ?string $month, ?string $year): void
    {
        $user = Auth::user();
        $monthName = $month ? Carbon::createFromDate(null, (int) $month, 1)->format('F') : now()->format('F');
        $year = $year ?: now()->format('Y');

        $this->put($sheet, 'D1', "FHSIS REPORT for the Month {$monthName}  Year {$year}");
        $this->put($sheet, 'D2', 'Name of Barangay: ' . ($user->barangay ?? ''));
        $this->put($sheet, 'D3', 'Name of BHS: ' . ($user->assigned_facility ?? ''));
        $this->put($sheet, 'D4', 'Name of Municipality/City: ' . ($user->municipality ?? ''));
        $this->put($sheet, 'D5', 'Name of Province: ' . ($user->province ?? ''));
    }

    /* ---- Section A. Family Planning --------------------------------------
     * Template rows: 11 (Demand Satisfied), 16-33 (per-method current users,
     * "Current User (End of the Month)" column group), 34 (Total). */

    private function fillSectionA(Worksheet $sheet, array $fp): void
    {
        $this->putRow($sheet, 11, ['E', 'I', 'M', 'Q'], $this->ageVals($fp['demandSatisfied']));

        $methodRows = [
            16 => 'btl', 17 => 'nsv', 18 => 'condom',
            20 => 'pills-pop', 21 => 'pills-coc', 22 => 'injectable',
            24 => 'implant-interval', 25 => 'implant-pp',
            27 => 'iud-interval', 28 => 'iud-pp',
            29 => 'lam', 30 => 'bbt', 31 => 'cmm', 32 => 'stm', 33 => 'sdm',
        ];

        $totals = ['10-14' => 0, '15-19' => 0, '20-49' => 0, 'total' => 0];
        foreach ($methodRows as $row => $key) {
            $bracket = $fp['currentUsersByMethod'][$key] ?? null;
            if (! $bracket) {
                continue;
            }
            $this->putRow($sheet, $row, ['R', 'S', 'T', 'U'], $this->ageVals($bracket));
            foreach ($totals as $k => $v) {
                $totals[$k] += $bracket[$k];
            }
        }
        $this->putRow($sheet, 34, ['R', 'S', 'T', 'U'], $this->ageVals($totals));
    }

    /* ---- Section B. Maternal Care and Services --------------------------- */

    private function fillSectionB(Worksheet $sheet, array $mc): void
    {
        // Prenatal: LEFT column (age bracket) rows 40/48-50/52-53.
        $p = $mc['prenatal'];
        $this->putRow($sheet, 40, ['B', 'C', 'D', 'E'], $this->ageVals($p['anc8Completed']));
        $this->putRow($sheet, 48, ['B', 'C', 'D', 'E'], $this->ageVals($p['nutritionNormal']));
        $this->putRow($sheet, 49, ['B', 'C', 'D', 'E'], $this->ageVals($p['nutritionLow']));
        $this->putRow($sheet, 50, ['B', 'C', 'D', 'E'], $this->ageVals($p['nutritionHigh']));
        $this->putRow($sheet, 52, ['B', 'C', 'D', 'E'], $this->ageVals($p['td2PlusFirstPregnancy']));
        $this->putRow($sheet, 53, ['B', 'C', 'D', 'E'], $this->ageVals($p['td2Plus']));

        // Prenatal: RIGHT column (age bracket) rows 40-54.
        $this->putRow($sheet, 40, ['Q', 'R', 'S', 'T'], $this->ageVals($p['ifaCompleted']));
        $this->putRow($sheet, 41, ['Q', 'R', 'S', 'T'], $this->ageVals($p['mmCompleted']));
        $this->putRow($sheet, 42, ['Q', 'R', 'S', 'T'], $this->ageVals($p['ccCompleted']));
        $this->putRow($sheet, 44, ['Q', 'R', 'S', 'T'], $this->ageVals($p['anemiaScreened']));
        $this->putRow($sheet, 45, ['Q', 'R', 'S', 'T'], $this->ageVals($p['anemiaDiagnosed']));
        $this->putRow($sheet, 47, ['Q', 'R', 'S', 'T'], $this->ageVals($p['gdmScreened']));
        $this->putRow($sheet, 48, ['Q', 'R', 'S', 'T'], $this->ageVals($p['gdmDiagnosed']));
        $this->putRow($sheet, 50, ['Q', 'R', 'S', 'T'], $this->ageVals($p['dewormed']));
        $this->putRow($sheet, 52, ['Q', 'R', 'S', 'T'], $this->ageVals($p['bpMeasured']));
        $this->putRow($sheet, 53, ['Q', 'R', 'S', 'T'], $this->ageVals($p['highBpOrDanger']));
        $this->putRow($sheet, 54, ['Q', 'R', 'S', 'T'], $this->ageVals($p['referred']));

        // Intrapartum: the template's LEFT column is bracketed by the
        // MOTHER's age, which this app doesn't tally for intrapartum (it
        // tallies by newborn sex instead — see PhoController@getMaternalCareData),
        // so only the aggregate "Total" cell is filled in; per-bracket cells
        // are left blank rather than guessed. The RIGHT column (birth weight,
        // rows 65-67) is genuinely sex-based and maps directly.
        $ip = $mc['intrapartum'];
        $this->put($sheet, 'E58', $ip['totalDeliveries']['total']);
        $this->put($sheet, 'E59', $ip['attendantPhysician']['total'] + $ip['attendantNurse']['total'] + $ip['attendantMidwife']['total']);
        $this->put($sheet, 'E60', $ip['attendantPhysician']['total']);
        $this->put($sheet, 'E61', $ip['attendantNurse']['total']);
        $this->put($sheet, 'E62', $ip['attendantMidwife']['total']);
        $this->put($sheet, 'E63', $ip['facilityPublic']['total'] + $ip['facilityPrivate']['total']);
        $this->put($sheet, 'E64', $ip['facilityPublic']['total']);
        $this->put($sheet, 'E65', $ip['facilityPrivate']['total']);
        $this->put($sheet, 'E66', $ip['deliveryVaginal']['total'] + $ip['deliveryCesarean']['total'] + $ip['deliveryCombined']['total']);
        $this->put($sheet, 'E67', $ip['deliveryVaginal']['total']);
        $this->put($sheet, 'E68', $ip['deliveryCesarean']['total']);
        $this->put($sheet, 'E69', $ip['deliveryCombined']['total']);

        $this->put($sheet, 'T58', $ip['outcomeFullTerm']['total'] + $ip['outcomePreTerm']['total'] + $ip['outcomeFetalDeath']['total'] + $ip['outcomeAbortion']['total']);
        $this->put($sheet, 'T59', $ip['outcomeFullTerm']['total']);
        $this->put($sheet, 'T60', $ip['outcomePreTerm']['total']);
        $this->put($sheet, 'T61', $ip['outcomeFetalDeath']['total']);
        $this->put($sheet, 'T62', $ip['outcomeAbortion']['total']);

        $this->putRow($sheet, 65, ['Q', 'R'], $this->sexVals($ip['birthWeightNormal']));
        $this->putRow($sheet, 66, ['Q', 'R'], $this->sexVals($ip['birthWeightLow']));
        $this->putRow($sheet, 67, ['Q', 'R'], $this->sexVals($ip['birthWeightUnknown']));

        // Postpartum: both columns are age-bracketed (this app does tally
        // postpartum by the mother's age bracket).
        $pp = $mc['postpartum'];
        $this->putRow($sheet, 74, ['B', 'C', 'D', 'E'], $this->ageVals($pp['pnc4Completed']));
        $this->putRow($sheet, 74, ['Q', 'R', 'S'], $this->ageVals($pp['ifaCompleted']));
        $this->putRow($sheet, 75, ['Q', 'R', 'S'], $this->ageVals($pp['vitACompleted']));
        $this->putRow($sheet, 77, ['Q', 'R', 'S'], $this->ageVals($pp['bpMeasured']));
        $this->putRow($sheet, 78, ['Q', 'R', 'S'], $this->ageVals($pp['highBpOrDanger']));
        $this->putRow($sheet, 79, ['Q', 'R', 'S'], $this->ageVals($pp['referred']));
    }

    /* ---- Section C. Child Care and Services ------------------------------- */

    private function fillSectionC(Worksheet $sheet, array $cc): void
    {
        $imm = $cc['imm0_11'];
        foreach ([86 => 'cpab', 87 => 'bcg24h', 88 => 'bcgLate', 89 => 'hepB24h', 90 => 'hepBLate', 91 => 'dpt1', 92 => 'dpt2', 93 => 'dpt3', 94 => 'opv1'] as $row => $key) {
            $this->putRow($sheet, $row, ['B', 'C'], $this->sexVals($imm[$key]));
        }
        foreach ([86 => 'opv2', 87 => 'opv3', 88 => 'ipv1', 89 => 'ipv2', 90 => 'pcv1', 91 => 'pcv2', 92 => 'pcv3', 93 => 'mmr1'] as $row => $key) {
            $this->putRow($sheet, $row, ['Q', 'R'], $this->sexVals($imm[$key]));
        }

        $prev = $cc['immPrev'];
        foreach ([96 => 'dpt1', 97 => 'dpt2', 98 => 'dpt3', 99 => 'opv1', 100 => 'opv2', 101 => 'opv3', 102 => 'ipv1', 103 => 'ipv2'] as $row => $key) {
            $this->putRow($sheet, $row, ['B', 'C'], $this->sexVals($prev[$key]));
        }
        foreach ([96 => 'pcv1', 97 => 'pcv2', 98 => 'pcv3', 99 => 'mmr1', 100 => 'mmr2', 101 => 'fic', 102 => 'cic'] as $row => $key) {
            $this->putRow($sheet, $row, ['Q', 'R'], $this->sexVals($prev[$key]));
        }

        $school = $cc['schoolImm'];
        foreach ([107 => 'grade1Td', 108 => 'grade1Mr', 109 => 'grade7Td', 110 => 'grade7Mr'] as $row => $key) {
            $this->putRow($sheet, $row, ['B', 'C'], $this->sexVals($school[$key]));
        }
        foreach ([107 => 'hpv1Sbi', 108 => 'hpv1Cbi', 109 => 'hpv2Cbi'] as $row => $key) {
            $this->putRow($sheet, $row, ['Q', 'R'], $this->sexVals($school[$key]));
        }

        $nut = $cc['nutrition'];
        foreach ([114 => 'breastfeedingInit', 115 => 'lbwIronComplete', 116 => 'vitA6to11', 117 => 'vitA12to59TwoDoses'] as $row => $key) {
            $this->putRow($sheet, $row, ['B', 'C'], $this->sexVals($nut[$key]));
        }
        foreach ([114 => 'mnp6to11', 115 => 'mnp12to23', 116 => 'lns6to11', 117 => 'lns12to23'] as $row => $key) {
            $this->putRow($sheet, $row, ['Q', 'R'], $this->sexVals($nut[$key]));
        }

        $n2 = $cc['nutrition2'];
        foreach ([120 => 'seen0to59', 121 => 'mamIdentified', 122 => 'samIdentified', 123 => 'mamEnrolled', 124 => 'mamCured', 125 => 'mamNonCured', 126 => 'mamDefaulted'] as $row => $key) {
            $this->putRow($sheet, $row, ['B', 'C'], $this->sexVals($n2[$key]));
        }
        foreach ([120 => 'mamDied', 121 => 'samAdmitted', 122 => 'samCured', 123 => 'samNonCured', 124 => 'samDefaulted', 125 => 'samDied'] as $row => $key) {
            $this->putRow($sheet, $row, ['Q', 'R'], $this->sexVals($n2[$key]));
        }

        $sick = $cc['mgmtSick'];
        foreach ([130 => 'sick6to11Seen', 131 => 'vitA6to11Sick', 132 => 'sick12to59Seen', 133 => 'vitA12to59Sick', 134 => 'diarrhea0to59Seen', 135 => 'orsOnly', 136 => 'orsZinc'] as $row => $key) {
            $this->putRow($sheet, $row, ['B', 'C'], $this->sexVals($sick[$key]));
        }
        // Row 131 right (antibioticAny) is a template rollup formula over
        // 132-135, so it's intentionally skipped here.
        foreach ([130 => 'pneumonia0to59Seen', 132 => 'amoxDrops', 133 => 'amoxClav', 134 => 'cefuroxime', 135 => 'otherAntibiotic'] as $row => $key) {
            $this->putRow($sheet, $row, ['Q', 'R'], $this->sexVals($sick[$key]));
        }
    }

    /* ---- Section D. Oral Health Care Services ----------------------------- */

    private function fillSectionD(Worksheet $sheet, array $oh): void
    {
        $this->putRow($sheet, 141, ['B', 'C'], $this->sexVals($oh['infantFirstVisit']));

        // LEFT: "1st visit" facility/non-facility rows per bracket (the
        // combined parent row above each pair is a template SUM formula).
        foreach ([
            'children1_4' => [143, 144],
            'children5_9' => [146, 147],
            'adolescents10_19' => [149, 150],
            'adults20_59' => [152, 153],
            'seniors60plus' => [155, 156],
        ] as $key => [$facilityRow, $nonFacilityRow]) {
            $this->putRow($sheet, $facilityRow, ['B', 'C'], $this->sexVals($oh['firstVisitFacility'][$key]));
            $this->putRow($sheet, $nonFacilityRow, ['B', 'C'], $this->sexVals($oh['firstVisitNonFacility'][$key]));
        }

        // RIGHT: "completed 2 visits" facility/non-facility rows (columns
        // shift to P/Q/R here, matching the template's own header for this
        // sub-table); again the parent SUM row above each pair is skipped.
        foreach ([
            'children1_4' => [142, 143],
            'children5_9' => [145, 146],
            'adolescents10_19' => [148, 149],
            'adults20_59' => [151, 152],
            'seniors60plus' => [154, 155],
        ] as $key => [$facilityRow, $nonFacilityRow]) {
            $this->putRow($sheet, $facilityRow, ['P', 'Q'], $this->sexVals($oh['completed2VisitsFacility'][$key]));
            $this->putRow($sheet, $nonFacilityRow, ['P', 'Q'], $this->sexVals($oh['completed2VisitsNonFacility'][$key]));
        }
    }

    /* ---- Section E. Non-Communicable Diseases ----------------------------- */

    private function fillSectionE(Worksheet $sheet, array $ncd): void
    {
        $l2059 = $ncd['lifestyle2059'];
        $l60plus = $ncd['lifestyle60plus'];
        $lifestyleRows = [
            167 => 'currentSmoker', 168 => 'smokerTobacco', 169 => 'smokerVaporized', 170 => 'smokerBoth',
            171 => 'providedBti', 172 => 'bingeAlcohol', 173 => 'insufficientPa', 174 => 'unhealthyDiet',
            175 => 'overweight', 176 => 'obese',
        ];
        foreach ($lifestyleRows as $row => $key) {
            $this->putRow($sheet, $row, ['B', 'C'], $this->sexVals($l2059[$key]));
            $this->putRow($sheet, $row, ['P', 'Q'], $this->sexVals($l60plus[$key]));
        }
        // CVD/Hypertension and Diabetes (rows 178-191) are never populated by
        // PhoController (tallies are commented out pending a data-model
        // decision), so nothing is written there — left blank as in the
        // template rather than reporting false zeros.

        $bl = $ncd['blindness'];
        $this->putRow($sheet, 196, ['B', 'C'], $this->sexVals($bl['screened0_9']));
        $this->putRow($sheet, 197, ['B', 'C'], $this->sexVals($bl['screened10_19']));
        $this->putRow($sheet, 198, ['B', 'C'], $this->sexVals($bl['screened20_59']));
        $this->putRow($sheet, 199, ['B', 'C'], $this->sexVals($bl['screened60plus']));

        // Mental health: one row (220) with 4 age brackets x male/female.
        $mh = $ncd['mentalHealth'];
        $this->putRow($sheet, 220, ['B', 'C'], $this->sexVals($mh['screened0_9']));
        $this->putRow($sheet, 220, ['D', 'E'], $this->sexVals($mh['screened10_19']));
        $this->putRow($sheet, 220, ['F', 'G'], $this->sexVals($mh['screened20_59']));
        $this->putRow($sheet, 220, ['H', 'I'], $this->sexVals($mh['screened60plus']));

        $cv = $ncd['cervical'];
        $this->put($sheet, 'D229', $cv['via']);
        $this->put($sheet, 'D230', $cv['papSmear']);
        $this->put($sheet, 'D231', $cv['hpvDna']);
        $this->put($sheet, 'D232', $cv['assessedOnly']);
        $this->put($sheet, 'D233', $cv['suspicious']);
        $this->put($sheet, 'D235', $cv['linkedTreated']);
        $this->put($sheet, 'D236', $cv['linkedReferred']);

        $br = $ncd['breast'];
        $this->put($sheet, 'T228', $br['seen']);
        $this->put($sheet, 'T229', $br['highRiskOrSymptomatic']);
        $this->put($sheet, 'T231', $br['providedCbe']);
        $this->put($sheet, 'T232', $br['providedMammogram']);
        $this->put($sheet, 'T234', $br['remarkableCbe']);
        $this->put($sheet, 'T235', $br['remarkableMammogram']);
    }

    /* ---- Section F. Environmental Health and Sanitation ------------------- */

    private function fillSectionF(Worksheet $sheet, array $envi): void
    {
        $w = $envi['water'];
        $this->put($sheet, 'B251', $w['levelI']);
        $this->put($sheet, 'B252', $w['levelII']);
        $this->put($sheet, 'B253', $w['levelIII']);
        $this->put($sheet, 'B254', $w['safelyManaged']);

        $s = $envi['sanitation'];
        $this->put($sheet, 'P251', $s['pourFlushSeptic']);
        $this->put($sheet, 'P252', $s['pourFlushSewer']);
        $this->put($sheet, 'P253', $s['vip']);
        $this->put($sheet, 'P254', $s['safelyManagedSanitation']);
    }

    /* ---- Section G. Infectious Disease Prevention and Control ------------- */

    private function fillSectionG(Worksheet $sheet, array $inf): void
    {
        $f = $inf['filariasis'];
        $this->putRow($sheet, 260, ['B', 'C'], $this->sexVals($f['examinedNbe']));
        $this->putRow($sheet, 261, ['B', 'C'], $this->sexVals($f['examinedRdt']));
        $this->putRow($sheet, 264, ['B', 'C'], $this->sexVals($f['positiveNbe']));
        $this->putRow($sheet, 265, ['B', 'C'], $this->sexVals($f['positiveRdt']));
        $this->putRow($sheet, 271, ['B', 'C'], $this->sexVals($f['lymphedema']));
        $this->putRow($sheet, 276, ['B', 'C'], $this->sexVals($f['elephantiasis']));
        $this->putRow($sheet, 263, ['Q', 'R'], $this->sexVals($f['hydrocele']));
        $this->putRow($sheet, 268, ['Q', 'R'], $this->sexVals($f['receivedMda']));

        $r = $inf['rabies'];
        $this->putRow($sheet, 287, ['B', 'C'], $this->sexVals($r['animalBites']));
        $this->putRow($sheet, 287, ['Q', 'R'], $this->sexVals($r['rabiesDeaths']));

        $sc = $inf['schistosomiasis'];
        $this->putRow($sheet, 291, ['B', 'C'], $this->sexVals($sc['patientsSeen']));
        $this->putRow($sheet, 297, ['B', 'C'], $this->sexVals($sc['suspectedCases']));
        $this->putRow($sheet, 308, ['B', 'C'], $this->sexVals($sc['suspectedTreated']));
        $this->putRow($sheet, 316, ['B', 'C'], $this->sexVals($sc['confirmedComplicated']));
        $this->putRow($sheet, 322, ['B', 'C'], $this->sexVals($sc['confirmedNonComplicated']));
        $this->putRow($sheet, 301, ['Q', 'R'], $this->sexVals($sc['confirmedTreated']));
        $this->putRow($sheet, 314, ['Q', 'R'], $this->sexVals($sc['referredToHospital']));
        $this->putRow($sheet, 320, ['Q', 'R'], $this->sexVals($sc['mdaGiven']));

        $sth = $inf['sth'];
        $this->putRow($sheet, 331, ['B', 'C'], $this->sexVals($sth['screened']));
        $this->putRow($sheet, 338, ['B', 'C'], $this->sexVals($sth['suspectedResident']));
        $this->putRow($sheet, 339, ['B', 'C'], $this->sexVals($sth['suspectedNonResident']));
        $this->putRow($sheet, 347, ['B', 'C'], $this->sexVals($sth['confirmedResident']));
        $this->putRow($sheet, 348, ['B', 'C'], $this->sexVals($sth['confirmedNonResident']));
        $this->putRow($sheet, 332, ['Q', 'R'], $this->sexVals($sth['treatedResident']));
        $this->putRow($sheet, 333, ['Q', 'R'], $this->sexVals($sth['treatedNonResident']));

        $lep = $inf['leprosy'];
        $this->putRow($sheet, 360, ['B', 'C'], $this->sexVals($lep['registered']));
        $this->putRow($sheet, 364, ['B', 'C'], $this->sexVals($lep['newlyDetected']));
        $this->putRow($sheet, 368, ['B', 'C'], $this->sexVals($lep['confirmed']));
        $this->putRow($sheet, 360, ['Q', 'R'], $this->sexVals($lep['completedMdt']));
        $this->putRow($sheet, 364, ['Q', 'R'], $this->sexVals($lep['treated']));
        $this->putRow($sheet, 368, ['Q', 'R'], $this->sexVals($lep['grade2Disability']));
    }

    /* ---- template-writing primitives --------------------------------------
     * put() never overwrites a cell that already contains a formula, so the
     * template's own subtotal/grand-total SUM() cells are always preserved
     * and simply recalculate in Excel once the file is opened. */

    private function put(Worksheet $sheet, string $coordinate, $value): void
    {
        $cell = $sheet->getCell($coordinate);
        $existing = $cell->getValue();
        if (is_string($existing) && str_starts_with($existing, '=')) {
            return;
        }
        $cell->setValue($value ?? 0);
    }

    private function putRow(Worksheet $sheet, int $row, array $columns, array $values): void
    {
        foreach ($columns as $i => $col) {
            $this->put($sheet, "{$col}{$row}", $values[$i] ?? 0);
        }
    }

    private function ageVals(array $bracket): array
    {
        return [
            $bracket['10-14'] ?? 0,
            $bracket['15-19'] ?? 0,
            $bracket['20-49'] ?? 0,
            $bracket['total'] ?? 0,
        ];
    }

    private function sexVals(array $bracket): array
    {
        return [
            $bracket['male'] ?? 0,
            $bracket['female'] ?? 0,
            $bracket['total'] ?? 0,
        ];
    }

    /* =====================================================================
     * Family Planning
     * ===================================================================*/

    private function familyPlanning(?string $month, ?string $year): array
    {
        $records = DB::table('family_planning_records as fp')
            ->leftJoin('household_profiles as hp', 'hp.id', '=', 'fp.profileId')
            ->select(
                'fp.*',
                'hp.memberLastName',
                'hp.memberFirstName',
                'hp.memberMiddleName'
            )
            ->get()
            ->filter(fn ($r) => $this->dateInPeriod($r->registrationDate, $month, $year));

        $followUpsByRecord = DB::table('family_planning_follow_ups')->get()->groupBy('recordId');
        $dropOutsByRecord = DB::table('family_planning_drop_outs')->get()->groupBy('recordId');

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        return $records->map(function ($r) use ($followUpsByRecord, $dropOutsByRecord, $months) {
            $visits = [];
            foreach ($months as $m) {
                $visits[$m] = ['scheduled' => '', 'actual' => ''];
            }

            foreach ($followUpsByRecord->get($r->id, collect()) as $fu) {
                $key = ucfirst(strtolower(substr($fu->monthName ?? '', 0, 3)));
                if (array_key_exists($key, $visits)) {
                    $visits[$key] = [
                        'scheduled' => $fu->scheduledDate ?? '',
                        'actual' => $fu->actualDate ?? '',
                    ];
                }
            }

            $dropOut = $dropOutsByRecord->get($r->id, collect())->last();

            $fullName = trim(implode(', ', array_filter([
                $r->memberLastName,
                trim(($r->memberFirstName ?? '') . ' ' . ($r->memberMiddleName ?? '')),
            ])));

            return [
                'id' => (string) $r->id,
                'dateOfRegistration' => $r->registrationDate ?? '',
                'familySerialNumber' => $r->familySerialNumber ?? '',
                'fullName' => $fullName,
                'completeAddress' => $r->address ?? '',
                'age' => $r->age !== null ? (string) $r->age : '',
                'dateOfBirth' => $r->birthDate ?? '',
                'ageGroup' => in_array($r->ageGroupCategory, ['A', 'B', 'C'], true) ? $r->ageGroupCategory : '',
                'typeOfClient' => $r->clientType ?? '',
                'source' => in_array($r->commoditySource, ['Public', 'Private'], true) ? $r->commoditySource : '',
                'previousMethod' => $r->previousMethod ?? '',
                'followUpVisits' => $visits,
                'dropOutDate' => $dropOut->dropOutDate ?? '',
                'dropOutReason' => $dropOut->reasonCode ?? '',
                'remarks' => $dropOut->remarks ?? '',
            ];
        })->values()->all();
    }

    /* =====================================================================
     * Maternal Care
     * ===================================================================*/

    private function maternalCare(?string $month, ?string $year): array
    {
        $records = DB::table('maternal_care_records')->get()
            ->filter(fn ($r) => $this->dateInPeriod($r->registrationDate, $month, $year));
        $ancByRecord = DB::table('prenatal_8anc_records')->get()->keyBy('maternalRecordId');
        $immuByRecord = DB::table('prenatal_immunization_records')->get()->keyBy('maternalRecordId');
        $suppByRecord = DB::table('prenatal_supplementation_records')->get()->keyBy('maternal_record_id');

        $sumTablets = function ($supp, string $prefix, array $suffixes): int {
            if (! $supp) {
                return 0;
            }
            $sum = 0;
            foreach ($suffixes as $suffix) {
                $field = "{$prefix}_{$suffix}_num";
                $sum += (int) ($supp->$field ?? 0);
            }

            return $sum;
        };

        return $records->map(function ($r) use ($ancByRecord, $immuByRecord, $suppByRecord, $sumTablets) {
            $anc = $ancByRecord->get($r->id);
            $immu = $immuByRecord->get($r->id);
            $supp = $suppByRecord->get($r->id);

            $ancVisitsCompleted = 0;
            if ($anc) {
                for ($i = 1; $i <= 8; $i++) {
                    $field = "visit{$i}Date";
                    if (! empty($anc->$field)) {
                        $ancVisitsCompleted++;
                    }
                }
            }

            $tdDosesGiven = 0;
            if ($immu) {
                for ($i = 1; $i <= 5; $i++) {
                    $field = "td{$i}Date";
                    if (! empty($immu->$field)) {
                        $tdDosesGiven++;
                    }
                }
            }

            $bmiCategory = '';
            $status = strtolower($r->bmiStatus ?? '');
            if (str_contains($status, 'low') || str_contains($status, 'under')) {
                $bmiCategory = 'Low';
            } elseif (str_contains($status, 'high') || str_contains($status, 'over') || str_contains($status, 'obese')) {
                $bmiCategory = 'High';
            } elseif (str_contains($status, 'normal')) {
                $bmiCategory = 'Normal';
            }

            return [
                'id' => (string) $r->id,
                'dateOfRegistration' => $r->registrationDate ?? '',
                'familySerialNumber' => $r->familySerialNumber ?? '',
                'fullName' => $r->patientName ?? '',
                'address' => $r->homeAddress ?? '',
                'age' => $r->age !== null ? (int) $r->age : '',
                'ageGroup' => in_array($r->ageGroup, ['A', 'B', 'C'], true) ? $r->ageGroup : '',
                'lmp' => $r->ImpDate ?? '',
                'gravidaParity' => $r->gravidaPara ?? '',
                'edd' => $r->eddDate ?? '',
                'ancVisitsCompleted' => $ancVisitsCompleted,
                'completed8Anc' => (bool) ($anc->completed8Anc ?? false),
                'withHighBp' => (bool) ($anc->highBp ?? false),
                'withDangerSigns' => (bool) ($anc->dangerSigns ?? false),
                'dangerSignsNote' => $anc->dangerSignsDetail ?? '',
                'referred' => (bool) ($anc->highBpReferred ?? false),
                'bmiCategory' => $bmiCategory,
                'tdDosesGiven' => $tdDosesGiven,
                'dewormed' => (bool) ($supp->received_deworming ?? false),
                'ifaTabletsGiven' => $sumTablets($supp, 'ifa', ['v1', 'v2', 'v3', 'v4', 'v5', 'v6']),
                'ifaCompleted' => (bool) ($supp->completed_ifa ?? false),
                'mmTabletsGiven' => $sumTablets($supp, 'mm', ['v1', 'v2', 'v3', 'v4', 'v5', 'v6']),
                'mmCompleted' => (bool) ($supp->completed_mm ?? false),
                'ccTabletsGiven' => $sumTablets($supp, 'cc', ['v2', 'v3', 'v4']),
                'ccCompleted' => (bool) ($supp->completed_cc ?? false),
                'remarks' => '',
            ];
        })->values()->all();
    }

    /* =====================================================================
     * Child Care (Immunization / Immunization School / Sick / Nutrition)
     * ===================================================================*/

    private function childCare(?string $month, ?string $year): array
    {
        return [
            'immunization' => DB::table('child_immunization_records')->get()
                ->filter(fn ($r) => $this->dateInPeriod($r->registrationDate, $month, $year))
                ->map(fn ($r) => $this->mapChildImmunization($r))->values()->all(),
            'immunizationSchool' => DB::table('child_immunization_school_records')->get()
                ->filter(fn ($r) => $this->dateInPeriod($r->registrationDate, $month, $year))
                ->map(fn ($r) => $this->mapChildImmunizationSchool($r))->values()->all(),
            'managementOfSick' => DB::table('child_sick_records')->get()
                ->filter(fn ($r) => $this->dateInPeriod($r->dateRegistration, $month, $year))
                ->map(fn ($r) => $this->mapChildManagementSick($r))->values()->all(),
            'nutrition' => DB::table('child_nutrition_records')->get()
                ->filter(fn ($r) => $this->dateInPeriod($r->dateRegistration, $month, $year))
                ->map(fn ($r) => $this->mapChildNutrition($r))->values()->all(),
        ];
    }

    private function mapChildImmunization($r): array
    {
        $cpab = [];
        if ($r->td2Mother) {
            $cpab[] = 'TD2';
        }
        if ($r->td3To5Mother) {
            $cpab[] = 'TD3-5';
        }

        return [
            'dateReg' => $this->s($r->registrationDate),
            'familySerial' => $this->s($r->familySerialNumber),
            'childName' => $this->s($r->childName),
            'dob' => $this->s($r->dateOfBirth),
            'ageMonths' => $this->s($r->ageMonths),
            'sex' => $this->s($r->sex),
            'motherName' => $this->s($r->motherName),
            'address' => $this->s($r->address),
            'cpab' => implode(', ', $cpab),
            'bcgDate' => $this->s($r->bcgWithin24hDate ?: $r->bcgLateDate),
            'hepaBDate' => $this->s($r->hepaBWithin24hDate ?: $r->hepaBLateDate),
            'dpt1' => $this->s($r->dpt1Date),
            'dpt2' => $this->s($r->dpt2Date),
            'dpt3' => $this->s($r->dpt3Date),
            'opv1' => $this->s($r->opv1Date),
            'opv2' => $this->s($r->opv2Date),
            'opv3' => $this->s($r->opv3Date),
            'ipv1' => $this->s($r->ipv1Date),
            'ipv2' => $this->s($r->ipv2Date),
            'pcv1' => $this->s($r->pcv1Date),
            'pcv2' => $this->s($r->pcv2Date),
            'pcv3' => $this->s($r->pcv3Date),
            'mmr1' => $this->s($r->mmr1Date),
            'mmr2' => $this->s($r->mmr2Date),
            'ficDate' => $this->s($r->ficDate),
            'cicDate' => $this->s($r->cicDate),
            'remarks' => $this->s($r->remarks),
        ];
    }

    private function mapChildImmunizationSchool($r): array
    {
        return [
            'dateReg' => $this->s($r->registrationDate),
            'familySerial' => $this->s($r->familySerialNumber),
            'childName' => $this->s($r->childName),
            'dob' => $this->s($r->dateOfBirth),
            'sex' => $this->s($r->sex),
            'ageYears' => $this->s($r->ageYears),
            'address' => $this->s($r->address),
            'gradeLevel' => $this->s($r->gradeLevel),
            'tdVaccine' => $this->s($r->tdDate),
            'mrVaccine' => $this->s($r->mrDate),
            'hpvDose1' => $this->s($r->hpv1SbiDate),
            'cbiHpvAge9' => $this->s($r->hpv1CbiDate),
            'cbiHpvDose2' => $this->s($r->hpv2CbiDate),
            'cbiCompleted' => $r->hpvCompleted ? $this->s($r->hpvCompletedDate) : '0',
            'remarks' => $this->s($r->remarks),
        ];
    }

    private function mapChildManagementSick($r): array
    {
        return [
            'dateReg' => $this->s($r->dateRegistration),
            'familySerial' => $this->s($r->familySerialNumber),
            'childName' => $this->s($r->childName),
            'dob' => $this->s($r->dateOfBirth),
            'ageMonths' => $this->s($r->ageMonths),
            'sex' => $this->s($r->sex),
            'motherName' => $this->s($r->motherName),
            'address' => $this->s($r->address),
            'vitA611' => $r->vitaminA100IU ? $this->s($r->vitaminADateGiven) : '',
            'vitA1259' => $r->vitaminA200IU ? $this->s($r->vitaminADateGiven) : '',
            'diagnosis' => $r->diagnosisMeasles ? '1' : ($r->diagnosisPersistentDiarrhea ? '2' : ''),
            'orsOnly' => $r->orsOnly ? $this->s($r->diarrheaDateGiven) : '',
            'orsZinc' => $r->orsAndZinc ? $this->s($r->diarrheaDateGiven) : '',
            'amoxicillin' => $r->amoxicillinDrops ? $this->s($r->pneumoniaDateGiven) : '',
            'amoxClav' => $r->amoxicillinClavulanate ? $this->s($r->pneumoniaDateGiven) : '',
            'cefuroxime' => $r->cefuroxime ? $this->s($r->pneumoniaDateGiven) : '',
            'othersTreatment' => $r->pneumoniaOthers
                ? trim($this->s($r->pneumoniaDateGiven) . ' - ' . $this->s($r->pneumoniaOthersSpec), ' -')
                : '',
            'remarks' => $this->s($r->remarks),
        ];
    }

    private function mapChildNutrition($r): array
    {
        $mnp611 = $r->mnp6to11Completed ? 'Completed' : ($r->mnp6to11Provided ? 'Provided' : '');
        $mnp1223 = $r->mnp12to23Completed ? 'Completed' : ($r->mnp12to23Provided ? 'Provided' : '');
        $lns611 = $r->lns6to11Completed ? 'Completed' : ($r->lns6to11Provided ? 'Provided' : '');
        $lns1223 = $r->lns12to23Completed ? 'Completed' : ($r->lns12to23Provided ? 'Provided' : '');

        return [
            'dateReg' => $this->s($r->dateRegistration),
            'familySerial' => $this->s($r->familySerialNumber),
            'childName' => $this->s($r->childName),
            'dob' => $this->s($r->dateOfBirth),
            'ageMonths' => $this->s($r->ageMonths),
            'sex' => $this->s($r->sex),
            'motherName' => $this->s($r->motherName),
            'address' => $this->s($r->address),
            'lengthAtBirth' => $this->s($r->lengthAtBirth),
            'weightAtBirth' => $this->s($r->weightAtBirth),
            'birthWeightStatus' => $this->s($r->birthWeightStatus),
            'breastfeedingInit' => $this->s($r->breastfeedingDate),
            'deliveryPlace' => $this->s($r->placeOfDelivery),
            'iron1mo' => $this->s($r->iron1Month),
            'iron2mo' => $this->s($r->iron2Months),
            'iron3mo' => $this->s($r->iron3Months),
            'ironCompleted' => $r->ironCompleted ? $this->s($r->ironCompletedDate) : '0',
            'vitA611' => $this->s($r->vitaA6to11),
            // Frontend only tracks year 1 & 2 doses; Y3/Y4 columns exist in the
            // DB for longer-term tracking but aren't surfaced in this table yet.
            'vitA1259_1a' => $this->s($r->vitaA200Y1D1),
            'vitA1259_1b' => $this->s($r->vitaA200Y1D2),
            'vitA1259_2a' => $this->s($r->vitaA200Y2D1),
            'vitA1259_2b' => $this->s($r->vitaA200Y2D2),
            'mnp611' => $mnp611,
            'mnp611Remarks' => $this->s($r->mnp6to11Remarks),
            'mnp1223' => $mnp1223,
            'mnp1223Remarks' => $this->s($r->mnp12to23Remarks),
            'lns611' => $lns611,
            'lns611Remarks' => $this->s($r->lns6to11Remarks),
            'lns1223' => $lns1223,
            'lns1223Remarks' => $this->s($r->lns12to23Remarks),
            'mamIdentified' => $this->s($r->mamIdentified),
            'mamEnrolled' => $this->s($r->mamEnrolled),
            'mamCured' => $this->s($r->mamCured),
            'mamNonCured' => $this->s($r->mamNonCured),
            'mamDefaulted' => $this->s($r->mamDefaulted),
            'mamDied' => $this->s($r->mamDied),
            'samIdentified' => $this->s($r->samIdentified),
            'samAdmitted' => $this->s($r->samAdmitted),
            'samCured' => $this->s($r->samCured),
            'samNonCured' => $this->s($r->samNonCured),
            'samDefaulted' => $this->s($r->samDefaulted),
            'samDied' => $this->s($r->samDied),
            'remarks' => $this->s($r->remarks),
        ];
    }

    /* =====================================================================
     * Oral Health Care
     * ===================================================================*/

    private function oralHealth(?string $month, ?string $year): array
    {
        return DB::table('oral_health_care')->get()
            ->filter(fn ($r) => $this->dateInPeriod($r->date_of_visit, $month, $year))
            ->map(function ($r) {
            return [
                'id' => (string) $r->id,
                'dateOfVisit' => $r->date_of_visit ?? '',
                'familySerialNumber' => $r->family_serial ?? '',
                'fullName' => $r->name ?? '',
                'address' => $r->address ?? '',
                'dateOfBirth' => $r->date_of_birth ?? '',
                'ageMonths' => $r->age_months !== null ? (int) $r->age_months : '',
                'ageYears' => $r->age_years !== null ? (int) $r->age_years : '',
                'sex' => in_array($r->sex, ['M', 'F'], true) ? $r->sex : '',
                'ageGroup' => $r->age_group1st ?? '',
                'infantOralScreening' => (bool) $r->rpoc0_oral_screening,
                'infantRiskAssessment' => (bool) $r->rpoc0_risk_assessment,
                'infantOhi' => (bool) $r->rpoc0_oral_hygiene,
                'infantCounseling' => (bool) $r->rpoc0_counseling,
                'infantFluorideVarnish' => (bool) $r->rpoc0_fluoride_varnish,
                'infantRpocComplete' => (bool) $r->complete_rpoc0,
                'oralScreening1st' => $r->oral_screening1st ?? '',
                'oralScreening2nd' => $r->oral_screening2nd ?? '',
                'riskAssessment1st' => $r->risk_assessment1st ?? '',
                'riskAssessment2nd' => $r->risk_assessment2nd ?? '',
                'oralProphylaxis1st' => $r->oral_prophylaxis1st ?? '',
                'oralProphylaxis2nd' => $r->oral_prophylaxis2nd ?? '',
                'fluorideVarnish1st' => $r->fluoride_varnish1st ?? '',
                'fluorideVarnish2nd' => $r->fluoride_varnish2nd ?? '',
                'counseling1st' => $r->counseling1st ?? '',
                'counseling2nd' => $r->counseling2nd ?? '',
                'rpocVisit1Complete' => (bool) $r->complete_rpoc1st,
                'rpocVisit2Complete' => (bool) $r->complete_rpoc2nd,
                'serviceLocation' => in_array($r->service_location1st, ['A', 'B'], true) ? $r->service_location1st : '',
                'remarks' => $r->remarks ?? '',
            ];
        })->values()->all();
    }

    /* =====================================================================
     * Non-Communicable Disease (PhilPEN / Eye / Cervical Cancer / Mental Health)
     * ===================================================================*/

    private function nonCommunicableDisease(?string $month, ?string $year): array
    {
        return [
            'philpenRiskAssessment' => DB::table('philpen_risk_assessments')->get()
                ->filter(fn ($r) => $this->dateInPeriod($r->date_assessment, $month, $year))
                ->map(fn ($r) => $this->mapPhilpen($r))->values()->all(),
            'eyeScreening' => DB::table('eyes_screenings')->get()
                ->filter(fn ($r) => $this->dateInPeriod($r->date_screening, $month, $year))
                ->map(fn ($r) => $this->mapEyeScreening($r))->values()->all(),
            'cervicalCancer' => DB::table('cervical_cancer_screenings')->get()
                ->filter(fn ($r) => $this->dateInPeriod($r->date_assessment, $month, $year))
                ->map(fn ($r) => $this->mapCervicalCancer($r))->values()->all(),
            'mentalHealth' => DB::table('mental_health_records')->get()
                ->filter(fn ($r) => $this->dateInPeriod($r->dateOfAssessment, $month, $year))
                ->map(fn ($r) => $this->mapMentalHealth($r))->values()->all(),
        ];
    }

    private function mapPhilpen($r): array
    {
        $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep'];
        $monthly = json_decode($r->monthly_meds ?? '[]', true) ?: [];
        $byMonth = collect($monthly)->keyBy(fn ($m) => strtolower($m['month'] ?? ''));

        $row = [
            'dateAssessed' => $this->s($r->date_assessment),
            'familySerial' => $this->s($r->family_serial),
            'name' => $this->s($r->name),
            'address' => $this->s($r->address),
            'dob' => $this->s($r->date_of_birth),
            'ageYears' => $this->s($r->age),
            'ageGroup' => $this->s($r->age_group),
            'sex' => $this->s($r->sex),
            'currentSmoker' => $this->s($r->current_smoker),
            'btiAsk' => $this->s($r->bti_ask),
            'btiAdvise' => $this->s($r->bti_advise),
            'btiAssess' => $this->s($r->bti_assess),
            'btiAssist' => $this->s($r->bti_assist),
            'btiArrange' => $this->s($r->bti_arrange),
            'btiProvided' => $this->s($r->provided_bti),
            'bingeAlcohol' => $this->s($r->binge_alcohol),
            'insufficientActivity' => $this->s($r->insufficient_pa),
            'unhealthyDiet' => $this->s($r->unhealthy_diet),
            'weightStatus' => $this->s($r->bmi_category),
            'htnCompletedDate1' => $this->s($r->screening_date1),
            'htnCompletedDate2' => $this->s($r->screening_date2),
            'htnResult' => $this->s($r->hypertension_result),
            'medsInitialNeeded' => $this->s($r->meds_initial),
            'medsChangeNeeded' => $this->s($r->meds_changed),
            'antihtnTablets' => '',
        ];

        // monthly_meds is expected to be a JSON array of
        // {"month": "jan", "pbf": <int>, "oop": <int>, "both": <bool>} objects.
        foreach ($months as $m) {
            $entry = $byMonth->get($m);
            $label = $m . 'Provision';

            if (! $entry) {
                $row[$label] = '';
                continue;
            }

            $parts = [];
            if (isset($entry['pbf'])) {
                $parts[] = "PBF {$entry['pbf']}";
            }
            if (isset($entry['oop'])) {
                $parts[] = "OOP {$entry['oop']}";
            }
            if (! empty($entry['both'])) {
                $parts[] = 'Both';
            }
            $row[$label] = implode(' / ', $parts);
        }

        return $row;
    }

    private function mapEyeScreening($r): array
    {
        return [
            'dateScreened' => $this->s($r->date_screening),
            'familySerial' => $this->s($r->family_serial),
            'name' => $this->s($r->name),
            'address' => $this->s($r->address),
            'dob' => $this->s($r->date_of_birth),
            'ageYears' => $this->s($r->age),
            'ageGroup' => $this->s($r->age_group),
            'sex' => $this->s($r->sex),
            'screened' => $this->s($r->screened),
            'identifiedDisease' => $this->s($r->eye_disease_code),
            'dateReferred' => $this->s($r->date_referred),
            'remarks' => $this->s($r->remarks),
        ];
    }

    private function mapCervicalCancer($r): array
    {
        return [
            'dateAssessed' => $this->s($r->date_assessment),
            'familySerial' => $this->s($r->family_serial),
            'name' => $this->s($r->client_name),
            'address' => $this->s($r->address),
            'dob' => $this->s($r->date_of_birth),
            'ageYears' => $this->s($r->age),
            'cxScreeningDone' => $this->s($r->cervical_screening_done),
            'cxResult' => $this->s($r->cervical_result),
            'cxLinkedToCare' => $this->s($r->cervical_linked_to_care),
            'brRiskResult' => $this->s($r->breast_risk_assessment),
            'brAgeRiskClass' => $this->s($r->breast_age_risk_class),
            'brExamination' => $this->s($r->breast_exam_type),
            'brResults' => $this->s($r->breast_result),
            'brLinkedToCare' => $this->s($r->breast_linked_to_care),
            'remarks' => $this->s($r->remarks),
        ];
    }

    private function mapMentalHealth($r): array
    {
        return [
            'dateAssessed' => $this->s($r->dateOfAssessment),
            'familySerial' => $this->s($r->familySerialNumber),
            'name' => $this->s($r->name),
            'address' => $this->s($r->address),
            'dob' => $this->s($r->dateOfBirth),
            'ageYears' => $this->s($r->age),
            'ageGroup' => $this->s($r->ageGroup),
            'sex' => $this->s($r->sex),
            'mhgapScreened' => $r->screenedMhgap ? '1' : '0',
        ];
    }

    /* =====================================================================
     * Geriatric Health
     * ===================================================================*/

    private function geriatricHealth(?string $month, ?string $year): array
    {
        return DB::table('geriatric_screening_records')->get()
            ->filter(fn ($r) => $this->dateInPeriod($r->date_of_screening, $month, $year))
            ->map(function ($r) {
            $domains = collect(explode(',', $r->results ?? ''))
                ->map(fn ($d) => trim($d))
                ->filter(fn ($d) => in_array($d, ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'], true))
                ->values()
                ->all();

            return [
                'id' => (string) $r->record_no,
                'dateOfScreening' => $r->date_of_screening ?? '',
                'familySerialNumber' => $r->family_serial_number ?? '',
                'name' => $r->name ?? '',
                'address' => $r->address ?? '',
                'dateOfBirth' => $r->date_of_birth ?? '',
                'age' => $r->age !== null ? (int) $r->age : '',
                'sex' => in_array($r->sex, ['M', 'F'], true) ? $r->sex : '',
                'positiveDomains' => $domains,
                'careplanOrReferred' => (bool) $r->care_plan_provided,
                'receivedPpvAt60' => (bool) $r->ppv_received_at60,
                'ppvDateGiven' => $r->ppv_date_given ?? '',
                'influenzaDateGiven' => $r->influenza_date_given ?? '',
                'remarks' => $r->remarks ?? '',
            ];
        })->values()->all();
    }

    /* =====================================================================
     * Infectious Disease (Filariasis / Schistosomiasis / STH / Leprosy)
     * ===================================================================*/

    private function infectiousDisease(?string $month, ?string $year): array
    {
        return [
            'filariasis' => DB::table('filariasis_registry_table')->get()
                ->filter(fn ($r) => $this->dateInPeriod($r->date_of_registration, $month, $year))
                ->values()
                ->map(fn ($r, $i) => $this->mapFilariasis($r, $i + 1))->all(),
            'schistosomiasis' => DB::table('schistosomiasis_registry')->get()
                ->filter(fn ($r) => $this->dateInPeriod($r->date_of_registration, $month, $year))
                ->values()
                ->map(fn ($r, $i) => $this->mapSchistosomiasis($r, $i + 1))->all(),
            'sth' => DB::table('sth_registry_records')->get()
                ->filter(fn ($r) => $this->dateInPeriod($r->date_of_registration, $month, $year))
                ->values()
                ->map(fn ($r, $i) => $this->mapSth($r, $i + 1))->all(),
            'leprosy' => DB::table('leprosy_registry')->get()
                ->filter(fn ($r) => $this->dateInPeriod($r->date_of_registration, $month, $year))
                ->values()
                ->map(fn ($r, $i) => $this->mapLeprosy($r, $i + 1))->all(),
        ];
    }

    private function mapFilariasis($r, int $no): array
    {
        $result = null;
        if (! empty($r->blood_test_result)) {
            $result = str_contains(strtolower($r->blood_test_result), 'pos') ? 1 : 2;
        }

        return [
            'id' => (string) $r->id,
            'no' => $no,
            'dateOfRegistration' => $r->date_of_registration ?? '',
            'familySerialNumber' => $r->family_serial_number ?? '',
            'patientFullName' => $r->name ?? '',
            'completeAddress' => $r->address ?? '',
            'dateOfBirth' => $r->date_of_birth ?? '',
            'age' => $r->age !== null ? (int) $r->age : 0,
            'ageGroup' => in_array($r->age_group, ['A', 'B', 'C'], true) ? $r->age_group : 'C',
            'sex' => in_array($r->sex, ['M', 'F'], true) ? $r->sex : 'M',
            'bloodTest' => [
                'typeNBE' => (bool) $r->nbe_performed,
                'typeRDT' => (bool) $r->rdt_performed,
                'dateOfTest' => $r->date_nbe_rdt ?? '',
                'result' => $result,
            ],
            'chronicManifestations' => [
                'lymphedemaExamined' => $r->lymphedema_examined_first_time !== null ? (int) $r->lymphedema_examined_first_time : null,
                'lymphedema' => (bool) $r->has_lymphedema,
                'elephantiasisExamined' => $r->elephantiasis_examined_first_time !== null ? (int) $r->elephantiasis_examined_first_time : null,
                'elephantiasis' => (bool) $r->has_elephantiasis,
                'hydroceleExamined' => $r->hydrocele_examined_first_time !== null ? (int) $r->hydrocele_examined_first_time : null,
                'hydrocele' => (bool) $r->has_hydrocele,
            ],
            'drugsGiven' => [
                'albendazoleDate' => $r->albendazole_date_given ?? '',
                'decDate' => $r->dec_date_given ?? '',
                'ivermectinDate' => $r->ivermectin_date_given ?? '',
            ],
            'remarks' => $r->remarks ?? '',
        ];
    }

    private function mapSchistosomiasis($r, int $no): array
    {
        return [
            'id' => (string) $r->id,
            'no' => $no,
            'dateOfRegistration' => $r->date_of_registration ?? '',
            'familySerialNumber' => $r->family_serial_number ?? '',
            'patientFullName' => $r->name ?? '',
            'completeAddress' => $r->address ?? '',
            'residency' => ((int) ($r->residency ?? 1)) === 1 ? 1 : 2,
            'dateOfBirth' => $r->date_of_birth ?? '',
            'age' => $r->age !== null ? (int) $r->age : 0,
            'ageGroup' => in_array($r->age_group, ['A', 'B', 'C', 'D', 'E'], true) ? $r->age_group : 'D',
            'sex' => in_array($r->sex, ['M', 'F'], true) ? $r->sex : 'M',
            'historyOfTravelExposure' => (bool) $r->history_of_exposure,
            'screened' => [
                'done' => (bool) $r->screened,
                'dateScreened' => $r->date_screened ?? '',
            ],
            'suspectedCase' => [
                'withSignsSymptoms' => $r->with_signs_symptoms !== null ? (bool) $r->with_signs_symptoms : null,
                'treated' => $r->clinical_first_treatment_given ? [
                    'done' => true,
                    'dateStarted' => $r->clinical_first_treatment_date ?? '',
                ] : null,
                'retreatment' => $r->clinical_retreatment ? [
                    'done' => true,
                    'date' => $r->clinical_retreatment_date ?? '',
                ] : null,
                'cured' => $r->clinical_cured ? [
                    'done' => true,
                    'date' => $r->clinical_cured_date ?? '',
                ] : null,
            ],
            'confirmedCase' => [
                'diagnosticTest' => $r->diagnostic_test ?? '',
                'dateOfDiagnosis' => $r->date_of_diagnosis ?? '',
                'result' => $r->diagnostic_result === 'positive'
                    ? 'positive'
                    : ($r->diagnostic_result === 'negative' ? 'negative' : null),
                'dateConfirmed' => $r->date_confirmed ?? '',
                'complicated' => (bool) $r->complicated,
                'treated' => $r->confirmed_first_treatment_given ? [
                    'done' => true,
                    'dateStarted' => $r->confirmed_first_treatment_date ?? '',
                ] : null,
                'retreatment' => $r->confirmed_retreatment ? [
                    'done' => true,
                    'date' => $r->confirmed_retreatment_date ?? '',
                ] : null,
                'cured' => $r->confirmed_cured ? [
                    'done' => true,
                    'date' => $r->confirmed_cured_date ?? '',
                ] : null,
            ],
            'dateReferredToHospital' => $r->date_referred_to_hospital ?? '',
            'mdaPraziquantelGiven' => [
                'done' => (bool) $r->mda_given,
                'date' => $r->mda_date_given ?? '',
            ],
            'remarks' => $r->remarks ?? '',
        ];
    }

    private function mapSth($r, int $no): array
    {
        return [
            'id' => (string) $r->id,
            'no' => $no,
            'dateOfRegistration' => $r->date_of_registration ?? '',
            'familySerialNumber' => $r->family_serial_number ?? '',
            'patientFullName' => $r->name ?? '',
            'completeAddress' => $r->address ?? '',
            'residency' => ((int) ($r->residency ?? 1)) === 1 ? 1 : 0,
            'dateOfBirth' => $r->date_of_birth ?? '',
            'age' => $r->age !== null ? (int) $r->age : 0,
            'ageGroup' => in_array($r->age_classification, ['A', 'B', 'C', 'D', 'E'], true) ? $r->age_classification : 'D',
            'sex' => in_array($r->sex, ['M', 'F'], true) ? $r->sex : 'M',
            'screened' => [
                'done' => (bool) $r->screened,
                'dateOfScreening' => $r->date_of_screening ?? '',
            ],
            'screeningResult' => $r->screening_result !== null && $r->screening_result !== ''
                ? (int) $r->screening_result
                : null,
            'dateOfResult' => $r->date_of_result ?? '',
            'treatmentGiven' => $r->treatment_given ? [
                'type' => (int) $r->treatment_given,
                'dateGiven' => $r->treatment_date_given ?? '',
            ] : null,
            'januaryMda' => $r->january_mda_date ? [
                'date' => $r->january_mda_date,
                'venue' => (int) ($r->january_mda_modality ?: 1),
            ] : null,
            'julyMda' => $r->july_mda_date ? [
                'date' => $r->july_mda_date,
                'venue' => (int) ($r->july_mda_modality ?: 1),
            ] : null,
            'remarks' => $r->remarks ?? '',
        ];
    }

    private function mapLeprosy($r, int $no): array
    {
        return [
            'id' => (string) $r->id,
            'no' => $no,
            'dateOfRegistration' => $r->date_of_registration ?? '',
            'fullName' => $r->name ?? '',
            'completeAddress' => $r->address ?? '',
            'dateOfBirth' => $r->date_of_birth ?? '',
            'age' => $r->age !== null ? (int) $r->age : 0,
            'ageGroup' => in_array($r->age_group, ['A', 'B', 'C'], true) ? $r->age_group : 'C',
            'sex' => in_array($r->sex, ['M', 'F'], true) ? $r->sex : 'M',
            'confirmedCase' => [
                'confirmed' => (bool) $r->confirmed_case,
                'dateOfDiagnosis' => $r->date_of_diagnosis ?? '',
            ],
            'caseHistory' => (int) ($r->case_history ?: 0),
            'previousFacility' => $r->previous_facility ?? '',
            'clinicalClassification' => (int) ($r->clinical_classification ?: 1),
            'treatmentStartDate' => $r->treatment_start_date ?? '',
            'monthsTreatedPrior' => $r->months_treated_prior ?? '',
            'reclassified' => $r->reclassified ? [
                'done' => true,
                'date' => $r->date_of_reclassification ?? '',
            ] : null,
            'updatedClassification' => $r->updated_classification ? (int) $r->updated_classification : null,
            'treatmentOutcome' => (int) ($r->treatment_outcome ?: 1),
            'completedFixedMdt' => [
                'done' => (bool) $r->completed_fixed_mdt,
                'dateCompleted' => $r->fixed_mdt_completed_date ?? '',
            ],
            'beyondFixedMdt' => $r->beyond_fixed_mdt ? [
                'done' => true,
                'dateCompleted' => $r->beyond_fixed_mdt_completed_date ?? '',
            ] : null,
            'withGrade2Disability' => (bool) $r->grade2_disability,
            'remarks' => $r->remarks ?? '',
        ];
    }

    /* =====================================================================
     * WASH (Environmental Health)
     * ===================================================================*/

    private function wash(): array
    {
        return DB::table('environmental_health_records')->get()->map(function ($r) {
            $waterSourceLevel = '';
            if ($r->waterLevelI) {
                $waterSourceLevel = 'I';
            } elseif ($r->waterLevelII) {
                $waterSourceLevel = 'II';
            } elseif ($r->waterLevelIII) {
                $waterSourceLevel = 'III';
            }

            $status = strtolower($r->sanitationStatus ?? '');
            $sanitaryToiletType = '';
            $unsanitaryToiletType = '';
            if (str_contains($status, 'unsanitary')) {
                // The schema only stores a 0-3 severity code here, not which
                // frontend sub-type (septic / sewer / VIP) applies, so the
                // sanitary side is intentionally left blank in this branch.
                $unsanitaryToiletType = (string) ($r->unsanitaryToiletType ?? '');
            }
            // Note: when sanitationStatus indicates a sanitary facility, the
            // specific sub-type (septic / sewer / VIP) is not captured by
            // this table, so sanitaryToiletType is left blank rather than
            // guessed.

            $excretaDisposalType = '';
            if ($r->disposalInSitu) {
                $excretaDisposalType = 'insitu';
            } elseif ($r->disposalOffSiteDesludged) {
                $excretaDisposalType = 'offsite-transport';
            } elseif ($r->disposalOffSiteSewer) {
                $excretaDisposalType = 'offsite-sewer';
            }

            return [
                'id' => (string) $r->id,
                'householdHead' => $r->householdHeadName ?? '',
                'waterSourceLevel' => $waterSourceLevel,
                'otherWaterSource' => $r->waterSourceOthers ?? '',
                'locatedInsideDwelling' => (bool) $r->waterLocatedInsideDwelling,
                'availableAtLeast12Hrs' => (bool) $r->waterAvailable12Hours,
                'microDate' => $r->microbiologicalTestDate ?? '',
                'microResultEcoli' => ((int) $r->microbiologicalTestResult) === 1,
                // Not captured by environmental_health_records today.
                'arsenicTestDate' => '',
                'arsenicWithinLimit' => false,
                'smdws' => ((int) $r->safelyManagedDrinkingWater) === 1,
                'sanitaryToiletType' => $sanitaryToiletType,
                'unsanitaryToiletType' => $unsanitaryToiletType,
                'toiletShared' => ((int) $r->toiletShared) === 1,
                'basicSanitationFacility' => ((int) $r->basicSanitationFacility) === 1,
                'excretaDisposalType' => $excretaDisposalType,
                'smss' => ((int) $r->safelyManagedSanitationService) === 1,
                'remarks' => $r->remarks ?? '',
            ];
        })->values()->all();
    }

    /* =====================================================================
     * Helpers
     * ===================================================================*/

    /**
     * Determine whether a free-text date column (these tables store dates
     * as strings like "mm/dd/yy" rather than real DATE columns) falls
     * within the requested month/year. Unparseable or empty values are
     * treated as not matching so they don't silently leak into every
     * filtered view.
     */
    private function dateInPeriod($value, ?string $month, ?string $year): bool
    {
        if (! $month && ! $year) {
            return true;
        }

        if (empty($value)) {
            return false;
        }

        try {
            $date = Carbon::parse($value);
        } catch (\Throwable $e) {
            return false;
        }

        if ($month && $date->format('m') !== str_pad((string) $month, 2, '0', STR_PAD_LEFT)) {
            return false;
        }

        if ($year && $date->format('Y') !== (string) $year) {
            return false;
        }

        return true;
    }

    /**
     * Safely stringify a raw DB value for a TargetClientListTable cell
     * (which expects Record<string, string>).
     */
    private function s($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}