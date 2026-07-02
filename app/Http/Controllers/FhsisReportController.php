<?php

namespace App\Http\Controllers;

use App\Models\FhsisReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema; 
use Illuminate\Support\Facades\Log;    
use Inertia\Inertia;
use Inertia\Response;

// PhpSpreadsheet Imports
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class FhsisReportController extends Controller
{
    /**
     * Dashboard View showing list of metrics
     */
    public function index(): Response
    {
        $reports = FhsisReport::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('dashboard', [
            'reports' => $reports,
        ]);
    }
    

    /**
     * Store incoming community health indicators
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reporting_year' => 'required|string|size:4',
            'reporting_quarter' => 'required|string',
            'total_pregnant_tracked' => 'required|integer|min:0',
            'completed_4_anc_visits' => 'required|integer|min:0|lte:total_pregnant_tracked',
            'fully_immunized_children' => 'required|integer|min:0',
            'infants_exclusive_breastfed' => 'required|integer|min:0',
        ]);

        FhsisReport::create(array_merge($validated, [
            'user_id' => Auth::id(),
            'status' => 'submitted'
        ]));

        return redirect()->route('fhsis.dashboard')->with('success', 'FHSIS Indicator Report submitted successfully.');
    }

    /**
     * Display consolidated general report metrics across all FHSIS infrastructure tables.
     */
    public function generalReport(Request $request)
    {
        $year     = $request->input('year', date('Y'));
        $barangay = $request->input('barangay', 'All');

        // ── Safe defaults (keeps frontend rendering clean when tables are empty) ──
        $demographics              = ['total_profiles' => 0, 'male_count' => 0, 'female_count' => 0];
        $maternal_stats            = ['total_tracked' => 0, 'adolescent_pregnancies' => 0, 'normal_bmi' => 0];
        $child_stats               = ['total_infants' => 0, 'fully_immunized' => 0, 'exclusive_breastfeeding' => 0];
        $fp_stats                  = ['total_clients' => 0, 'new_acceptors' => 0];
        $child_immunization        = ['total_records' => 0, 'fic_count' => 0, 'cic_count' => 0];
        $child_immunization_school = ['total_records' => 0, 'hpv_completed' => 0];
        $child_nutrition           = ['total_records' => 0, 'mam_identified' => 0, 'sam_identified' => 0];
        $child_sick                = ['total_records' => 0, 'diagnosed_measles' => 0, 'treated_pneumonia' => 0];
        $oral_health               = ['total_records' => 0, 'complete_rpoc0' => 0];
        $philpen                   = ['total_records' => 0, 'hypertension_positive' => 0, 'current_smokers' => 0];
        $eyes_screening            = ['total_screened' => 0, 'with_eye_disease' => 0];
        $cervical_cancer           = ['total_records' => 0, 'cervical_done' => 0, 'breast_risk_assessed' => 0];
        $geriatric                 = ['total_records' => 0, 'ppv_received' => 0];
        $filariasis                = ['total_records' => 0, 'with_lymphedema' => 0, 'with_elephantiasis' => 0];
        $leprosy                   = ['total_records' => 0, 'paucibacillary' => 0, 'multibacillary' => 0];
        $rabies                    = ['total_records' => 0, 'completed_pvrv' => 0];
        $schistosomiasis           = ['total_records' => 0, 'confirmed_positive' => 0, 'mda_given' => 0];
        $sth                       = ['total_records' => 0, 'positive_result' => 0, 'mda_jan' => 0, 'mda_jul' => 0];
        $mental_health             = ['total_records' => 0, 'screened_mhgap' => 0];
        $environmental_health      = ['total_records' => 0, 'safely_managed_water' => 0, 'safely_managed_sanitation' => 0];

        // Helper: add a profileId join + barangay filter only when needed.
        // $table  = table being queried, $fk = its foreign key column name.
        $withBarangay = function ($query, string $table, string $fk) use ($barangay) {
            if ($barangay !== 'All') {
                $query->join('household_profiles', "{$table}.{$fk}", '=', 'household_profiles.id')
                      ->where('household_profiles.barangay', $barangay);
            }
            return $query;
        };

        try {
            // ── 1. Core Demographics ──────────────────────────────────────────
            $profileQuery = DB::table('household_profiles');
            if ($barangay !== 'All') {
                $profileQuery->where('barangay', $barangay);
            }
            $demographics['total_profiles'] = (clone $profileQuery)->count();
            $demographics['male_count']     = (clone $profileQuery)->where('sex', 'Male')->count();
            $demographics['female_count']   = (clone $profileQuery)->where('sex', 'Female')->count();

            // ── 2. Maternal Care ──────────────────────────────────────────────
            if (Schema::hasTable('maternal_care_records')) {
                $q = DB::table('maternal_care_records')
                    ->join('household_profiles', 'maternal_care_records.profileId', '=', 'household_profiles.id')
                    ->whereYear('maternal_care_records.created_at', $year);
                if ($barangay !== 'All') {
                    $q->where('household_profiles.barangay', $barangay);
                }
                $maternal_stats['total_tracked']          = (clone $q)->count();
                // Fix: was '< 19' (strict), card label says ≤19
                $maternal_stats['adolescent_pregnancies'] = (clone $q)->where('maternal_care_records.age', '<=', 19)->count();
                $maternal_stats['normal_bmi']             = (clone $q)->where('maternal_care_records.bmiStatus', 'Normal')->count();
            }

            // ── 3. Family Planning ────────────────────────────────────────────
            if (Schema::hasTable('family_planning_records')) {
                $q = DB::table('family_planning_records')
                    ->join('household_profiles', 'family_planning_records.profileId', '=', 'household_profiles.id')
                    ->whereYear('family_planning_records.created_at', $year);
                if ($barangay !== 'All') {
                    $q->where('household_profiles.barangay', $barangay);
                }
                $fp_stats['total_clients'] = (clone $q)->count();
                $fp_stats['new_acceptors'] = (clone $q)->where('family_planning_records.clientType', 'New Acceptor')->count();
            }

            // ── 4. Child Immunization (0–11 months) ──────────────────────────
            // Fix: table is child_immunization_records, NOT the non-existent child_care_records
            if (Schema::hasTable('child_immunization_records')) {
                $q = DB::table('child_immunization_records')
                    ->whereYear('child_immunization_records.created_at', $year);
                if ($barangay !== 'All') {
                    $q->join('household_profiles', 'child_immunization_records.profileId', '=', 'household_profiles.id')
                      ->where('household_profiles.barangay', $barangay);
                }
                $child_immunization['total_records'] = (clone $q)->count();
                $child_immunization['fic_count']     = (clone $q)->whereNotNull('child_immunization_records.ficDate')->count();
                $child_immunization['cic_count']     = (clone $q)->whereNotNull('child_immunization_records.cicDate')->count();

                // Populate the legacy child_stats block from the same table
                $child_stats['total_infants']   = $child_immunization['total_records'];
                $child_stats['fully_immunized'] = $child_immunization['fic_count'];
            }

            // ── 5. Child Immunization – School (SBI / CBI) ───────────────────
            if (Schema::hasTable('child_immunization_school_records')) {
                $q = DB::table('child_immunization_school_records')
                    ->whereYear('child_immunization_school_records.created_at', $year);
                if ($barangay !== 'All') {
                    $q->join('household_profiles', 'child_immunization_school_records.profileId', '=', 'household_profiles.id')
                      ->where('household_profiles.barangay', $barangay);
                }
                $child_immunization_school['total_records'] = (clone $q)->count();
                $child_immunization_school['hpv_completed'] = (clone $q)->where('child_immunization_school_records.hpvCompleted', 1)->count();
            }

            // ── 6. Child Nutrition ────────────────────────────────────────────
            if (Schema::hasTable('child_nutrition_records')) {
                $q = DB::table('child_nutrition_records')
                    ->whereYear('child_nutrition_records.created_at', $year);
                if ($barangay !== 'All') {
                    $q->join('household_profiles', 'child_nutrition_records.profileId', '=', 'household_profiles.id')
                      ->where('household_profiles.barangay', $barangay);
                }
                $child_nutrition['total_records']  = (clone $q)->count();
                $child_nutrition['mam_identified'] = (int) (clone $q)->sum('child_nutrition_records.mamIdentified');
                $child_nutrition['sam_identified'] = (int) (clone $q)->sum('child_nutrition_records.samIdentified');
            }

            // ── 7. Child Sick (IMCI) ──────────────────────────────────────────
            if (Schema::hasTable('child_sick_records')) {
                $q = DB::table('child_sick_records')
                    ->whereYear('created_at', $year);
                $child_sick['total_records']     = (clone $q)->count();
                $child_sick['diagnosed_measles'] = (clone $q)->where('diagnosisMeasles', true)->count();
                // Treated = any pneumonia antibiotic recorded
                $child_sick['treated_pneumonia'] = (clone $q)
                    ->where(function ($sub) {
                        $sub->where('amoxicillinDrops', true)
                            ->orWhere('amoxicillinClavulanate', true)
                            ->orWhere('cefuroxime', true)
                            ->orWhere('pneumoniaOthers', true);
                    })->count();
            }

            // ── 8. Oral Health ────────────────────────────────────────────────
            if (Schema::hasTable('oral_health_care')) {
                $q = DB::table('oral_health_care')
                    ->whereYear('created_at', $year);
                $oral_health['total_records']  = (clone $q)->count();
                $oral_health['complete_rpoc0'] = (clone $q)->where('complete_rpoc0', 1)->count();
            }

            // ── 9. PhilPEN Risk Assessment ────────────────────────────────────
            if (Schema::hasTable('philpen_risk_assessments')) {
                $q = DB::table('philpen_risk_assessments')
                    ->whereYear('created_at', $year);
                if ($barangay !== 'All') {
                    $q->join('household_profiles', 'philpen_risk_assessments.profile_id', '=', 'household_profiles.id')
                      ->where('household_profiles.barangay', $barangay);
                }
                $philpen['total_records']         = (clone $q)->count();
                $philpen['hypertension_positive'] = (clone $q)->where('hypertension_result', 1)->count();
                $philpen['current_smokers']       = (clone $q)->where('current_smoker', 1)->count();
            }

            // ── 10. Eyes Screening ────────────────────────────────────────────
            if (Schema::hasTable('eyes_screenings')) {
                $q = DB::table('eyes_screenings')
                    ->whereYear('created_at', $year);
                if ($barangay !== 'All') {
                    $q->join('household_profiles', 'eyes_screenings.profile_id', '=', 'household_profiles.id')
                      ->where('household_profiles.barangay', $barangay);
                }
                $eyes_screening['total_screened']   = (clone $q)->count();
                $eyes_screening['with_eye_disease'] = (clone $q)
                    ->whereNotNull('eye_disease_code')
                    ->where('eye_disease_code', '!=', '')
                    ->count();
            }

            // ── 11. Cervical Cancer Screening ─────────────────────────────────
            if (Schema::hasTable('cervical_cancer_screenings')) {
                $q = DB::table('cervical_cancer_screenings')
                    ->whereYear('created_at', $year);
                if ($barangay !== 'All') {
                    $q->join('household_profiles', 'cervical_cancer_screenings.profile_id', '=', 'household_profiles.id')
                      ->where('household_profiles.barangay', $barangay);
                }
                $cervical_cancer['total_records']        = (clone $q)->count();
                $cervical_cancer['cervical_done']        = (clone $q)->where('cervical_screening_done', 1)->count();
                $cervical_cancer['breast_risk_assessed'] = (clone $q)->where('breast_risk_assessment', 1)->count();
            }

            // ── 12. Geriatric Screening ───────────────────────────────────────
            if (Schema::hasTable('geriatric_screening_records')) {
                $q = DB::table('geriatric_screening_records')
                    ->whereYear('created_at', $year);
                $geriatric['total_records'] = (clone $q)->count();
                $geriatric['ppv_received']  = (clone $q)->where('ppv_received_at60', true)->count();
            }

            // ── 13. Filariasis Registry ───────────────────────────────────────
            if (Schema::hasTable('filariasis_registry_table')) {
                $q = DB::table('filariasis_registry_table')
                    ->whereYear('created_at', $year);
                $filariasis['total_records']      = (clone $q)->count();
                $filariasis['with_lymphedema']    = (clone $q)->where('has_lymphedema', true)->count();
                $filariasis['with_elephantiasis'] = (clone $q)->where('has_elephantiasis', true)->count();
            }

            // ── 14. Leprosy Registry ──────────────────────────────────────────
            if (Schema::hasTable('leprosy_registry')) {
                $q = DB::table('leprosy_registry')
                    ->whereYear('created_at', $year);
                $leprosy['total_records']  = (clone $q)->count();
                $leprosy['paucibacillary'] = (clone $q)->where('clinical_classification', 'Paucibacillary')->count();
                $leprosy['multibacillary'] = (clone $q)->where('clinical_classification', 'Multibacillary')->count();
            }

            // ── 15. Rabies Records ────────────────────────────────────────────
            if (Schema::hasTable('rabies_records')) {
                $q = DB::table('rabies_records')
                    ->whereYear('created_at', $year);
                $rabies['total_records']  = (clone $q)->count();
                // Completed PVRV = day-28 dose date recorded
                $rabies['completed_pvrv'] = (clone $q)
                    ->whereNotNull('pvrv_day28_date')
                    ->where('pvrv_day28_date', '!=', '')
                    ->count();
            }

            // ── 16. Schistosomiasis Registry ──────────────────────────────────
            if (Schema::hasTable('schistosomiasis_registry')) {
                $q = DB::table('schistosomiasis_registry')
                    ->whereYear('created_at', $year);
                $schistosomiasis['total_records']      = (clone $q)->count();
                // Confirmed = date_confirmed is set
                $schistosomiasis['confirmed_positive'] = (clone $q)->whereNotNull('date_confirmed')->count();
                $schistosomiasis['mda_given']          = (clone $q)
                    ->whereNotNull('mda_given')
                    ->where('mda_given', '!=', '')
                    ->count();
            }

            // ── 17. STH Registry ──────────────────────────────────────────────
            if (Schema::hasTable('sth_registry_records')) {
                $q = DB::table('sth_registry_records')
                    ->whereYear('created_at', $year);
                $sth['total_records']  = (clone $q)->count();
                $sth['positive_result'] = (clone $q)->where('screening_result', 'Positive')->count();
                $sth['mda_jan']        = (clone $q)
                    ->whereNotNull('january_mda_date')
                    ->where('january_mda_date', '!=', '')
                    ->count();
                $sth['mda_jul']        = (clone $q)
                    ->whereNotNull('july_mda_date')
                    ->where('july_mda_date', '!=', '')
                    ->count();
            }

            // ── 18. Mental Health ─────────────────────────────────────────────
            if (Schema::hasTable('mental_health_records')) {
                $q = DB::table('mental_health_records')
                    ->whereYear('created_at', $year);
                $mental_health['total_records']  = (clone $q)->count();
                $mental_health['screened_mhgap'] = (clone $q)->where('screenedMhgap', true)->count();
            }

            // ── 19. Environmental Health ──────────────────────────────────────
            if (Schema::hasTable('environmental_health_records')) {
                $q = DB::table('environmental_health_records')
                    ->whereYear('created_at', $year);
                $environmental_health['total_records']             = (clone $q)->count();
                $environmental_health['safely_managed_water']      = (clone $q)->where('safelyManagedDrinkingWater', 1)->count();
                $environmental_health['safely_managed_sanitation'] = (clone $q)->where('safelyManagedSanitationService', 1)->count();
            }

        } catch (\Exception $e) {
            Log::error('FHSIS General Report Assembly Error: ' . $e->getMessage());
        }

        return Inertia::render('GeneralReport', [
            'filters'                   => ['year' => (string) $year, 'barangay' => $barangay],
            'demographics'              => $demographics,
            'maternal_stats'            => $maternal_stats,
            'child_stats'               => $child_stats,
            'fp_stats'                  => $fp_stats,
            'child_immunization'        => $child_immunization,
            'child_immunization_school' => $child_immunization_school,
            'child_nutrition'           => $child_nutrition,
            'child_sick'                => $child_sick,
            'oral_health'               => $oral_health,
            'philpen'                   => $philpen,
            'eyes_screening'            => $eyes_screening,
            'cervical_cancer'           => $cervical_cancer,
            'geriatric'                 => $geriatric,
            'filariasis'                => $filariasis,
            'leprosy'                   => $leprosy,
            'rabies'                    => $rabies,
            'schistosomiasis'           => $schistosomiasis,
            'sth'                       => $sth,
            'mental_health'             => $mental_health,
            'environmental_health'      => $environmental_health,
        ]);
    }

    /**
     * Consolidated Household Profiling Report Pipeline
     * Directly matches layout configurations found in image_5314ec.png
     */
    public function export(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $barangay = $request->input('barangay', 'All');
        $section = $request->input('section', 'all');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setShowGridlines(true);

        /*
        |--------------------------------------------------------------------------
        | 1. MAIN SHEET HEADER TITLE BLOCK
        |--------------------------------------------------------------------------
        */
        $titleText = 'HOUSEHOLD PROFILING FORM';
        $filenamePrefix = 'HOUSEHOLD_PROFILING_FORM';
        
        if ($section === 'fp') {
            $titleText = 'FAMILY PLANNING INDICATOR REPORT';
            $filenamePrefix = 'FAMILY_PLANNING_REPORT';
        } elseif ($section === 'demographics') {
            $titleText = 'DEMOGRAPHICS SUMMARY REPORT';
            $filenamePrefix = 'DEMOGRAPHICS_REPORT';
        } elseif ($section === 'maternal') {
            $titleText = 'MATERNAL HEALTH INDICATORS REPORT';
            $filenamePrefix = 'MATERNAL_HEALTH_REPORT';
        } elseif ($section === 'child') {
            $titleText = 'CHILD IMMUNIZATION & HEALTH REPORT';
            $filenamePrefix = 'CHILD_HEALTH_REPORT';
        }

        $sheet->mergeCells('A1:AC1');
        $sheet->setCellValue('A1', $titleText);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
        ]);
        $sheet->getRowDimension(1)->setRowHeight(35);

        /*
        |--------------------------------------------------------------------------
        | 2. TOP METADATA CONTEXT MATRIX (Rows 3 - 9)
        |--------------------------------------------------------------------------
        */
        // Left Metadata Segment
        $sheet->setCellValue('A3', 'Municipality/City/District: Baybay City');
        $sheet->setCellValue('A5', 'Province: Leyte');
        $sheet->setCellValue('A7', 'Sitio/Purok: All');
        $sheet->setCellValue('A8', 'Barangay: ' . $barangay);
        $sheet->setCellValue('A9', 'Reporting Year: ' . $year);
        $sheet->getStyle('A3:A9')->getFont()->setBold(true)->setSize(10)->setName('Arial');

        // Middle Metadata Segment
        $sheet->setCellValue('I3', 'Prepared by: Electronic System Automated Export');
        $sheet->setCellValue('I5', 'Reviewed/Verified by: Health Station Supervisor');
        $sheet->setCellValue('I7', 'Report Type Context:');
        $sheet->setCellValue('I8', strtoupper($section) . ' Extract Pipeline');
        $sheet->getStyle('I3:I8')->getFont()->setSize(10)->setName('Arial');

        // Checkbox Placeholders
        $sheet->setCellValue('P3', '[ ] IP Household');
        $sheet->setCellValue('P5', '[ ] Non-IP Household');
        $sheet->setCellValue('S3', '[ ] NHTS 4Ps');
        $sheet->setCellValue('S4', '[ ] NHTS Non-4Ps');
        $sheet->getStyle('P3:S5')->getFont()->setSize(10)->setName('Arial');

        // Environmental Health Data block
        $sheet->mergeCells('V3:Y3')->setCellValue('V3', 'Environmental Health Data Summary');
        $sheet->setCellValue('V4', 'Type of Water Source: System Monitored');
        $sheet->setCellValue('V6', 'Type of Toilet Facility: System Monitored');
        $sheet->getStyle('V3')->getFont()->setBold(true);
        $sheet->getStyle('V3:V6')->getFont()->setSize(9)->setName('Arial');

        // Date Info
        $sheet->mergeCells('AA3:AC3')->setCellValue('AA3', 'Generation Timeline Info');
        $sheet->setCellValue('AA4', 'Date Exported: ' . date('m/d/Y'));
        $sheet->getStyle('AA3')->getFont()->setBold(true);
        $sheet->getStyle('AA3:AA4')->getFont()->setSize(9)->setName('Arial');

        /*
        |--------------------------------------------------------------------------
        | 3. NESTED MULTI-TIER TABLE HEADERS (Rows 11 - 13)
        |--------------------------------------------------------------------------
        */
        $sheet->mergeCells('A11:C12')->setCellValue('A11', "Name of Household Members\n\n(Last Name, First Name, Middle Name)");
        $sheet->mergeCells('D11:D13')->setCellValue('D11', "Relationship to\nHH Head");
        $sheet->mergeCells('E11:E13')->setCellValue('E11', 'Sex');
        $sheet->mergeCells('F11:F13')->setCellValue('F11', "Date of\nBirth\n(mm/dd/yyyy)");
        $sheet->mergeCells('G11:G13')->setCellValue('G11', "Civil Status");
        $sheet->mergeCells('H11:H13')->setCellValue('H11', "Philhealth\nID Number");
        $sheet->mergeCells('I11:I13')->setCellValue('I11', "Membership\nType");
        $sheet->mergeCells('J11:J13')->setCellValue('J11', "Philhealth\nCategory");
        $sheet->mergeCells('K11:K13')->setCellValue('K11', "Medical\nHistory");
        $sheet->mergeCells('L11:L13')->setCellValue('L11', "Last Menstrual\nPeriod (LMP)");
        
        $sheet->mergeCells('M11:O11')->setCellValue('M11', 'Women of Reproductive Age (WRA)');
        $sheet->mergeCells('M12:M13')->setCellValue('M12', "Using FP\nMethod?");
        $sheet->mergeCells('N12:N13')->setCellValue('N12', 'Method Used');
        $sheet->mergeCells('O12:O13')->setCellValue('O12', 'FP Status');

        $sheet->mergeCells('P11:W11')->setCellValue('P11', 'Classification by Age/Health Risk Group');
        $sheet->mergeCells('P12:Q12')->setCellValue('P12', '1st Quarter');
        $sheet->setCellValue('P13', 'Age')->setCellValue('Q13', 'Class');
        $sheet->mergeCells('R12:S12')->setCellValue('R12', '2nd Quarter');
        $sheet->setCellValue('R13', 'Age')->setCellValue('S13', 'Class');
        $sheet->mergeCells('T12:U12')->setCellValue('T12', '3rd Quarter');
        $sheet->setCellValue('T13', 'Age')->setCellValue('U13', 'Class');
        $sheet->mergeCells('V12:W12')->setCellValue('V12', '4th Quarter');
        $sheet->setCellValue('V13', 'Age')->setCellValue('W13', 'Class');

        $sheet->mergeCells('X11:X13')->setCellValue('X11', "Educational\nAttainment");
        $sheet->mergeCells('Y11:Y13')->setCellValue('Y11', 'Religion');
        $sheet->mergeCells('Z11:AC13')->setCellValue('Z11', 'Remarks / Environmental Health Meta Summary');

        $sheet->setCellValue('A13', 'Last Name')->setCellValue('B13', 'First Name')->setCellValue('C13', 'Middle Name');

        // Apply Layout Styles across Header Block
        $sheet->getStyle('A11:AC13')->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'] // Slate fill matching layout schema
            ]
        ]);
        
        $sheet->getRowDimension(11)->setRowHeight(30);
        $sheet->getRowDimension(12)->setRowHeight(25);
        $sheet->getRowDimension(13)->setRowHeight(22);

        /*
        |--------------------------------------------------------------------------
        | 4. DATABASE QUERIES WITH LEFT-JOIN ACTION POOL
        |--------------------------------------------------------------------------
        */
        $profiles = DB::table('household_profiles')
        ->leftJoin('classification_metrics', 'household_profiles.id', '=', 'classification_metrics.profile_id')
            ->select(
                'household_profiles.*',
                'classification_metrics.q1_age',
                'classification_metrics.q1_class',
                'classification_metrics.q2_age',
                'classification_metrics.q2_class',
                'classification_metrics.q3_age',
                'classification_metrics.q3_class',
                'classification_metrics.q4_age',
                'classification_metrics.q4_class'
            )
            // ->when($barangay !== 'All', function($q) use ($barangay) {
            //     return $q->where('household_profiles.barangay', $barangay);
            // })
            // ->whereYear('household_profiles.created_at', $year)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | 5. ROW POPULATION LOOP (Row 14+)
        |--------------------------------------------------------------------------
        */
        $currentRow = 14;

        foreach ($profiles as $profile) {
            $sheet->setCellValue('A' . $currentRow, strtoupper($profile->memberLastName ?? ''));
            $sheet->setCellValue('B' . $currentRow, strtoupper($profile->memberFirstName ?? ''));
            $sheet->setCellValue('C' . $currentRow, strtoupper($profile->memberMiddleName ?? ''));
            $sheet->setCellValue('D' . $currentRow, $profile->relationship ?? '');
            $sheet->setCellValue('E' . $currentRow, $profile->sex ?? '');
            $sheet->setCellValue('F' . $currentRow, $profile->dob ?? '');
            $sheet->setCellValue('G' . $currentRow, $profile->socioStatus ?? '—'); 
            $sheet->setCellValue('H' . $currentRow, $profile->philhealthId ?? '');
            $sheet->setCellValue('I' . $currentRow, $profile->philType ?? '');
            $sheet->setCellValue('J' . $currentRow, $profile->philCategory ?? '');
            
            // Format medical histories safely
            $medHistory = [];
            if (!empty($profile->hpn) && $profile->hpn == 1) { $medHistory[] = 'HPN'; }
            if (!empty($profile->dm) && $profile->dm == 1) { $medHistory[] = 'DM'; }
            if (!empty($profile->tb) && $profile->tb == 1) { $medHistory[] = 'TB'; }
            $sheet->setCellValue('K' . $currentRow, implode(', ', $medHistory));
            
            $sheet->setCellValue('L' . $currentRow, ''); // LMP
            
            // Family Planning Status Metrics Mapping
            $isUsingFp = (!empty($profile->fpMethod) && $profile->fpMethod == 1) ? 'Y' : 'N';
            $sheet->setCellValue('M' . $currentRow, $isUsingFp);
            $sheet->setCellValue('N' . $currentRow, $profile->fpMethodUsed ?? '');
            $sheet->setCellValue('O' . $currentRow, ($isUsingFp === 'Y') ? 'CU' : ''); 

            // Left Joined Age Matrix Data Items
            $sheet->setCellValue('P' . $currentRow, $profile->q1_age ?? '');
            $sheet->setCellValue('Q' . $currentRow, $profile->q1_class ?? '');
            $sheet->setCellValue('R' . $currentRow, $profile->q2_age ?? '');
            $sheet->setCellValue('S' . $currentRow, $profile->q2_class ?? '');
            $sheet->setCellValue('T' . $currentRow, $profile->q3_age ?? '');
            $sheet->setCellValue('U' . $currentRow, $profile->q3_class ?? '');
            $sheet->setCellValue('V' . $currentRow, $profile->q4_age ?? '');
            $sheet->setCellValue('W' . $currentRow, $profile->q4_class ?? '');
            
            $sheet->setCellValue('X' . $currentRow, $profile->education ?? '');
            $sheet->setCellValue('Y' . $currentRow, $profile->religion ?? '');
            
            // Unified Summary Field
            $sheet->mergeCells("Z{$currentRow}:AC{$currentRow}");
            $metaRemarks = "HH: " . ($profile->hhNumber ?? 'N/A') . " | Water: " . ($profile->waterSource ?? 'N/A') . " | Latrine: " . ($profile->toiletType ?? 'N/A');
            $sheet->setCellValue('Z' . $currentRow, $metaRemarks);

            $sheet->getRowDimension($currentRow)->setRowHeight(24);
            $currentRow++;
        }

        // Add blank row template placeholders if payload dataset is empty
        if ($profiles->isEmpty()) {
            $sheet->mergeCells("A{$currentRow}:AC{$currentRow}");
            $sheet->setCellValue('A' . $currentRow, 'No household profiling datasets found matching current filter configuration metrics.');
            $sheet->getStyle('A' . $currentRow)->getFont()->setItalic(true);
            $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($currentRow)->setRowHeight(25);
            $currentRow++;
        }

        /*
        |--------------------------------------------------------------------------
        | 6. SIZING DEFINITIONS AND BORDER STYLING
        |--------------------------------------------------------------------------
        */
        $lastRow = $currentRow - 1; 

        $widths = [
            'A' => 16, 'B' => 16, 'C' => 16, 'D' => 14, 'E' => 8,  'F' => 13, 'G' => 12, 'H' => 16, 
            'I' => 13, 'J' => 14, 'K' => 12, 'L' => 13, 'M' => 10, 'N' => 12, 'O' => 10, 'P' => 8, 
            'Q' => 9,  'R' => 8,  'S' => 9,  'T' => 8,  'U' => 9,  'V' => 8,  'W' => 9,  'X' => 15, 
            'Y' => 16, 'Z' => 12, 'AA' => 12, 'AB' => 12, 'AC' => 12
        ];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $sheet->getStyle("A14:AC{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("A14:W{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A14:AC{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A11:AC{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        /*
        |--------------------------------------------------------------------------
        | 7. OUTBOUND FILE STREAM PIPELINE
        |--------------------------------------------------------------------------
        */
        $finalFilename = "{$filenamePrefix}_{$year}_{$barangay}.xlsx";
        $savePath = storage_path("app/{$finalFilename}");

        $writer = new Xlsx($spreadsheet);
        $writer->save($savePath);

        return response()->download($savePath, $finalFilename)->deleteFileAfterSend(true);
    }

   /**
     * Download Target Client List (TCL) for Family Planning Services
     */
    public function familyPlanningDownload(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $barangay = $request->input('barangay', 'All');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setShowGridlines(true);

        /*
        |--------------------------------------------------------------------------
        | 1. REPORT HEADER TITLE BANNER
        |--------------------------------------------------------------------------
        */
        $sheet->mergeCells('A1:Y1');
        $sheet->setCellValue('A1', "TARGET CLIENT LIST FOR FAMILY PLANNING SERVICES (Reporting Year: {$year} | Barangay: {$barangay})");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true, 
                'size' => 13, 
                'name' => 'Arial', 
                'color' => ['rgb' => 'FFFFFF']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER, 
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F172A']
            ]
        ]);
        $sheet->getRowDimension(1)->setRowHeight(35);

        /*
        |--------------------------------------------------------------------------
        | 2. STRUCTURAL HEADER GRIDS (Rows 2 & 3)
        |--------------------------------------------------------------------------
        */
        $verticalMerges = [
            'A' => "No.",
            'B' => "Date of Registration\n(mm/dd/yy)",
            'C' => "Family Serial Number",
            'D' => "Full Name \n(LastName, First Name, MI)",
            'E' => "Complete Address",
            'F' => "Age (in years)\n\nDate of Birth\n(mm/dd/yyyy)",
            'G' => "Age Group\n\nA - 10-14 yrs\nB - 15-19 yrs\nC - 20-49 yrs",
            'H' => "Type of Client\n\n(NA, CU, OA, etc.)",
            'I' => "Source\n\nPublic / Private",
            'J' => "Previous Method\n\n(Pills, IUD, CON, etc.)",
            'W' => "DROP-OUT\n\nDate (mm/dd/yy)",
            'X' => "Reason Code",
            'Y' => "Remarks / Notes"
        ];

        foreach ($verticalMerges as $col => $title) {
            $sheet->mergeCells("{$col}2:{$col}3");
            $sheet->setCellValue("{$col}2", $title);
        }

        // Horizontal Month Span Matrix across Columns K through V
        $sheet->mergeCells('K2:V2');
        $sheet->setCellValue('K2', 'RECORD OF MONITORING FOLLOW-UP VISITS BY MONTH');

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        foreach ($months as $index => $monthName) {
            $targetCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(11 + $index);
            $sheet->setCellValue("{$targetCol}3", $monthName);
        }

        // Apply Styles to Headers (Rows 2 and 3)
        $sheet->getStyle('A2:Y3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER, 
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F1F5F9']
            ]
        ]);
        $sheet->getRowDimension(2)->setRowHeight(28);
        $sheet->getRowDimension(3)->setRowHeight(20);

        /*
        |--------------------------------------------------------------------------
        | 3. DATA QUERIES PIPELINE WITH SUB-RELATIONSHIPS (USING ELOQUENT MODELS)
        |--------------------------------------------------------------------------
        */
        $records = \App\Models\FamilyPlanningRecord::with([
                'householdProfile',
                'dropOuts' => function ($query) {
                    $query->orderBy('dropOutDate', 'desc');
                },
                'followUps'
            ])
            // ->whereHas('householdProfile', function ($query) use ($barangay) {
            //     if ($barangay !== 'All') {
            //         $query->where('barangay', $barangay);
            //     }
            // })
            // ->where(function ($query) use ($year) {
            //     if (!empty($year)) {
            //         $query->where('registrationDate', 'LIKE', "%{$year}%")
            //               ->orWhereYear('created_at', $year);
            //     }
            // })
            ->get();

        $currentRow = 4;
        $totalRowsToPrint = max($records->count(), 10); // Maintain structural placeholders layout look
        \Illuminate\Support\Facades\Log::info("=== Family Planning Records Sync Dump ===");
        \Illuminate\Support\Facades\Log::info("Total Records Found: " . $records->count());
        \Illuminate\Support\Facades\Log::info("Payload Array Snapshot: ", [
            'data' => $records->toArray()
        ]);

        for ($idx = 1; $idx <= $totalRowsToPrint; $idx++) {
            $record = $records->get($idx - 1); 

            $sheet->setCellValue('A' . $currentRow, $idx);

            if ($record && $record->householdProfile) {
                $profile = $record->householdProfile;

                $regDate = !empty($record->registrationDate) ? date('m/d/Y', strtotime($record->registrationDate)) : '—';
                $sheet->setCellValue('B' . $currentRow, $regDate);
                $sheet->setCellValue('C' . $currentRow, $record->familySerialNumber ?? '—');

                $mi = !empty($profile->memberMiddleName) ? ' ' . strtoupper(substr($profile->memberMiddleName, 0, 1)) . '.' : '';
                $fullName = strtoupper($profile->memberLastName ?? '') . ', ' . strtoupper($profile->memberFirstName ?? '') . $mi;
                $sheet->setCellValue('D' . $currentRow, trim($fullName) ?: '—');

                $addressParts = array_filter([$profile->sitio, $profile->barangay, $profile->municipality]);
                $sheet->setCellValue('E' . $currentRow, !empty($addressParts) ? implode(', ', $addressParts) : '—');

                $ageText = '—';
                $ageGroupCode = '—';
                if (!empty($profile->dob)) {
                    try {
                        $age = \Carbon\Carbon::parse($profile->dob)->age;
                        $formattedDob = date('m/d/Y', strtotime($profile->dob));
                        $ageText = "{$age} yrs\n({$formattedDob})";

                        if ($age >= 10 && $age <= 14) { $ageGroupCode = 'A'; }
                        elseif ($age >= 15 && $age <= 19) { $ageGroupCode = 'B'; }
                        elseif ($age >= 20 && $age <= 49) { $ageGroupCode = 'C'; }
                    } catch (\Exception $e) {
                        $ageText = "—\n({$profile->dob})";
                    }
                }
                $sheet->setCellValue('F' . $currentRow, $ageText);
                $sheet->setCellValue('G' . $currentRow, $record->ageGroupCategory);

                $sheet->setCellValue('H' . $currentRow, $record->clientType ?? '—');
                $sheet->setCellValue('I' . $currentRow, $record->commoditySource ?? '—');
                $sheet->setCellValue('J' . $currentRow, $record->previousMethod ?? '—');

                /*
                |--------------------------------------------------------------------------
                | 4. RELATIONAL MONITORING LOGS MAPPING (EXPLICIT MATRIX RANGE: K TO V)
                |--------------------------------------------------------------------------
                */
                // Create a temporary array matrix to group follow-ups by month index for the current record row
                $monthlyGroupedGrid = [];

                foreach ($record->followUps as $followUp) {
                    $dateToParse = !empty($followUp->actualDate) ? $followUp->actualDate : $followUp->scheduledDate;

                    if (!empty($dateToParse) && $dateToParse !== '—' && strtotime($dateToParse) !== false) {
                        $monthIndex = (int) date('n', strtotime($dateToParse)); // 1 to 12

                        if ($monthIndex >= 1 && $monthIndex <= 12) {
                            // Extract values for the schedule vs actual visit matching
                            $schValue = !empty($followUp->scheduledDate) && $followUp->scheduledDate !== '—'
                                ? 'Sched: ' . date('m/d/y', strtotime($followUp->scheduledDate)) 
                                : '';
                                
                            $actValue = !empty($followUp->actualDate) && $followUp->actualDate !== '—'
                                ? 'Visit: ' . date('m/d/y', strtotime($followUp->actualDate)) 
                                : '';

                            // Collect values per month ensuring duplicate actions don't vanish
                            if (!empty($schValue)) {
                                $monthlyGroupedGrid[$monthIndex]['schedules'][] = $schValue;
                            }
                            if (!empty($actValue)) {
                                $monthlyGroupedGrid[$monthIndex]['visits'][] = $actValue;
                            }
                        }
                    }
                }

                // Render the collected matrix directly into columns K through V
                foreach ($monthlyGroupedGrid as $mIdx => $dataPayload) {
                    $colNumericIndex = 10 + $mIdx; // Jan = 11 (K), Dec = 22 (V)

                    if ($colNumericIndex >= 11 && $colNumericIndex <= 22) {
                        $targetCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNumericIndex);

                        // Extract array elements or default empty
                        $schedLines = $dataPayload['schedules'] ?? [];
                        $visitLines = $dataPayload['visits'] ?? [];

                        // If no visits were recorded but an entry tripped the loop, fallback text layout
                        if (empty($schedLines) && empty($visitLines)) {
                            $schedLines[] = 'Scheduled';
                        }

                        // Combine strings: All Schedules on top lines, followed by all Actual Visits on bottom lines
                        $finalCellText = implode("\n", array_merge($schedLines, $visitLines));

                        // Set value and explicitly enable cell text wrapping
                        $sheet->setCellValue($targetCol . $currentRow, $finalCellText);
                        $sheet->getStyle($targetCol . $currentRow)->getAlignment()->setWrapText(true);
                    }
                }
                $dropOut = $record->dropOuts->first();

                if ($dropOut) {
                    $doDate = !empty($dropOut->dropOutDate) ? date('m/d/Y', strtotime($dropOut->dropOutDate)) : '—';
                    $sheet->setCellValue('W' . $currentRow, $doDate);
                    $sheet->setCellValue('X' . $currentRow, $dropOut->reasonCode ?? '—');
                    $sheet->setCellValue('Y' . $currentRow, $dropOut->remarks ?? '');
                } else {
                    $sheet->setCellValue('W' . $currentRow, '—');
                    $sheet->setCellValue('X' . $currentRow, '—');
                    $sheet->setCellValue('Y' . $currentRow, 'Active');
                }
            } else {
                foreach (range('B', 'Y') as $col) {
                    $sheet->setCellValue($col . $currentRow, '');
                }
            }

            $sheet->getRowDimension($currentRow)->setRowHeight(25);
            $currentRow++;
        }

        /*
        |--------------------------------------------------------------------------
        | 5. DESIGN WIDTHS & LAYOUT CONFIGURATIONS
        |--------------------------------------------------------------------------
        */
        $lastRow = $currentRow - 1;
        $widths = [
            'A' => 6,  'B' => 15, 'C' => 16, 'D' => 25, 'E' => 22, 'F' => 15, 'G' => 10,
            'H' => 12, 'I' => 12, 'J' => 14, 'W' => 14, 'X' => 11, 'Y' => 18
        ];

        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        foreach (range('K', 'V') as $col) {
            $sheet->getColumnDimension($col)->setWidth(9);
        }

        $sheet->getStyle("A4:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("F4:W{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("X4:Y{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("A4:Y{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getStyle("A2:Y{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        /*
        |--------------------------------------------------------------------------
        | 6. WRITE SPREADSHEET DISPATCH STREAM
        |--------------------------------------------------------------------------
        */
        $finalFilename = "TCL_FAMILY_PLANNING_SERVICES_{$year}_{$barangay}.xlsx";
        $savePath = storage_path("app/{$finalFilename}");

        $writer = new Xlsx($spreadsheet);
        $writer->save($savePath);

        return response()->download($savePath, $finalFilename)->deleteFileAfterSend(true);
    }
    
    
    /**
     * Download Target Client List (TCL) for Maternal Care and Services
     * Layout mirrors Maternal_Care.xlsx  — single sheet: TCL_Maternal_8ANC_4PNC
     *
     * Header structure  : rows 1-6 (5 title banners + section labels + 4-tier column headers)
     * Data structure    : 2 rows per patient record (row-1 = dates / #-counts, row-2 = BP readings / d-dates)
     * Total columns     : 90  (A → CL)
     */
    public function maternalCareDownload(Request $request)
    {
        $year     = $request->input('year', date('Y'));
        $barangay = $request->input('barangay', 'All');

        /*
        |----------------------------------------------------------------------
        | 1.  EAGER-LOAD ALL SUB-RECORDS
        |----------------------------------------------------------------------
        */
        $records = \App\Models\MaternalCareRecord::with([
            'householdProfile',
            'prenatal8Anc',
            'prenatalImmunization',
            'prenatalLabScreening',
            'prenatalSupplementation',
            'intrapartum',
            'postpartum',
        ])->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('TCL_Maternal_8ANC_4PNC');
        $sheet->setShowGridlines(true);

        /*
        |----------------------------------------------------------------------
        | 2.  SHARED STYLE CONSTANTS
        |----------------------------------------------------------------------
        */
        $FILL = 'CFE2F3'; // light-blue header fill matching template

        $hBold = [
            'font'      => ['bold' => true, 'size' => 8, 'name' => 'Arial'],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => $FILL],
            ],
        ];
        $hNorm              = $hBold;
        $hNorm['font']['bold'] = false;

        // Helper: merge + set value
        $mc = function (string $range, $value) use ($sheet) {
            $sheet->mergeCells($range);
            $sheet->setCellValue(explode(':', $range)[0], $value);
        };

        // Helper: format date safely
        $fmt = fn($d) => $d ? date('m/d/Y', strtotime($d)) : '';

        /*
        |----------------------------------------------------------------------
        | 3.  ROW 1 — FIVE MAIN TITLE BANNERS
        |----------------------------------------------------------------------
        */
        $title = 'TARGET CLIENT LIST FOR MATERNAL CARE AND SERVICES';
        foreach ([['A1','I1'],['J1','AE1'],['AF1','BL1'],['BM1','BW1'],['BX1','CL1']] as [$f,$t]) {
            $mc("{$f}:{$t}", $title);
        }
        $sheet->getStyle('A1:CL1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 20, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24.6);

        /*
        |----------------------------------------------------------------------
        | 4.  ROW 2 — SECTION LABELS
        |----------------------------------------------------------------------
        */
        $sheet->mergeCells('A2:I2');
        $mc('J2:AE2',  'PRENATAL CARE PART 1');
        $mc('AF2:BL2', 'PRENATAL CARE PART 2');
        $mc('BM2:BW2', 'INTRAPARTUM CARE');
        $mc('BX2:CL2', 'POSTPARTUM CARE');
        $sheet->getStyle('J2:CL2')->applyFromArray($hBold);
        $sheet->getRowDimension(2)->setRowHeight(13.2);

        /*
        |----------------------------------------------------------------------
        | 5.  ROWS 3 – 6: MULTI-TIER COLUMN HEADERS
        |----------------------------------------------------------------------
        */
        // ── Patient columns A–I (merged rows 3-6) ──
        foreach ([
            'A3:A6' => 'No.',
            'B3:B6' => "Date of Registration\n(mm/dd/yy)",
            'C3:C6' => 'Family Serial Number',
            'D3:D6' => "Full Name \n(LastName, FullName, MI)",
            'E3:E6' => 'Complete Address',
            'F3:F6' => "Age\n(in years)",
            'G3:G6' => "Age Group\n\nA - 10-14 years old\nB - 15-19 years old\nC - 20-49 years old",
            'H3:H6' => "Last Mestrual Period (LMP)\n(mm/dd/yy)\n\nGravida Parity\n(G-P)",
            'I3:I6' => "Expected Date of Delivery \n(EDD)\n(mm/dd/yy)",
            'AE3:AE6' => "Remarks/\nActions Taken",
            'BL3:BL6' => "Remarks/\nActions Taken",
            'BW3:BW6' => "Remarks/\nActions Taken",
            'CL3:CL6' => "Remarks/\nActions Taken",
        ] as $rng => $txt) { $mc($rng, $txt); }

        // ── Row 3 section-span headers ──
        $mc('J3:V3',   "Date of Prenatal Check-up (8ANC) and BP measurement\nd: (mm/dd/yy)\nbp: BP reading (systolic/diastolic mm Hg)");
        $mc('W3:Y4',   "Nutritional Assessment\n(Write the BMI for 1st Trimester (1st visit))");
        $mc('Z3:AD3',  'Immunization Status');
        $mc('AF3:AW3', 'Prenatal Supplementation');
        $mc('AY3:BK3', 'Laboratory Screenings');
        $sheet->setCellValue('BM3', 'Delivery  Outcome');
        $sheet->setCellValue('BN3', 'Delivery Type');
        $mc('BO3:BQ3', "Birth Weight \n(with in the first 2 hours of life)");
        $mc('BR3:BS3', 'Place of Delivery');
        $sheet->setCellValue('BT3', 'Birth Attendant');
        $mc('BU3:BV3', 'Date and Time of Delivery');
        $mc('BX3:CF3', "Date of Postnatal Care (4PNC) and BP measurement\nd: (mm/dd/yy)\nbp: BP reading (systolic/diastolic mm Hg)");
        $mc('CG3:CK3', 'Postpartum Supplementation');

        // ── Row 4 trimester / status sub-headers ──
        $sheet->setCellValue('J4', "1st Trimester (Non-negotiable)\nRecommended Timing:\nVisit (SHP) 1: 8-13 weeks");
        $mc('K4:L4',   '2nd Trimester');
        $mc('M4:Q4',   '3rd Trimester');
        $mc('R4:R6',   "Completed 8ANC?\n\n1 - Yes\n0 - No\n");
        $mc('S4:S6',   "*With High/Elevated BP?\n\n1 - Yes\n0 - No");
        $mc('T4:T6',   "With Danger Signs?\n\n1- Yes\n0 - No\n\nif Yes, Identify Danger Signs**\n(atleast 1)");
        $mc('U4:U5',   "Identified with High BP/ Danger Signs and referred?\n\n1 - Yes\n0 - No");
        $mc('V4:V5',   "A - Resident\nB - Trans in\nC - Trans Out before receiving 8ANC");
        $mc('Z4:AD5',  "Date of Tetanus Diphtheria (Td)-containing vaccine given\n(mm/dd/yy)");
        $mc('AF4:AF6', "Received one dose of Deworming tablet?\n(during 2nd Trimester)\n1 - Yes\n0 - No\n\nd: Date (mm/dd/yy)");
        $mc('AG4:AL5', "Iron with Folic Acid (IFA) Supplementation\n\n#: Number of Tablets Given\nd: Date (mm/dd/yy)\n");
        $mc('AM4:AM6', "Completed IFA supplementation?\n\n1 - Yes\n0 - No\n\nif Yes, Date completed (mm/dd/yy)");
        $mc('AN4:AS5', "Multiple Micronutrient (MM) Supplementation\n\n#: Number of Tablets Given\nd: Date (mm/dd/yy)");
        $mc('AT4:AT6', "Completed MM supplementation?\n\n1 - Yes\n0 - No\n\nif Yes, Date completed (mm/dd/yy)");
        $mc('AU4:AW5', "Calcium Carbonate (CC) Supplementation\n\n#: Number of Tablets Given\nd: Date (mm/dd/yy)");
        $mc('AX4:AX6', "Completed CC supplementation?\n\n1 - Yes\n0 - No\n\nif Yes, date completed (mm/dd/yy)");
        $mc('AY4:AZ4', 'CBC/Hgb&Hct Count');
        $mc('BA4:BB4', 'Gestational Diabetes Mellitus');
        $mc('BC4:BD4', 'Hepatitis B');
        $mc('BE4:BF4', 'HIV');
        $mc('BG4:BK4', 'Syphilis');
        $mc('BM4:BM6', "FT - Full Term\nPT - Pre-term\nFD - Fetal Death\nAB - Abortion/\n        Miscarriage");
        $mc('BN4:BN6', "CS \u2013 Cesarean Section\nVD \u2013 Vaginal Delivery\nCVCD - Combined Vaginal-Cesarean Delivery");
        $mc('BO4:BO6', "Sex\nM - Male\nF - Female");
        $mc('BP4:BP6', "Weight\n(Write weight in grams)");
        $mc('BQ4:BQ6', "A - Normal (>2500g)\nB - Low (<2500g)\nC - Unknown");
        $mc('BR4:BS4', 'Health Facility');
        $mc('BT4:BT6', "MD - Doctor\nRN - Nurse\nMW - Midwife\nO - Others, Pls specify:");
        $mc('BU4:BU6', "Date\n(mm/dd/yy)");
        $mc('BV4:BV6', "Time\n(hh:mm)");
        $mc('BX4:BX5', 'within 24 hours after delivery');
        $mc('BY4:BY5', 'on day 3');
        $mc('BZ4:BZ5', 'between 7-14 days');
        $mc('CA4:CA5', '6 weeks after birth ');
        $mc('CB4:CB6', "Completed 4PNC?\n\n1 - Yes\n0 - No\n");
        $mc('CC4:CC6', "*With High/Elevated BP?\n\n1 - Yes\n0 - No");
        $mc('CD4:CD6', "With Danger Signs?\n\n1- Yes\n0 - No\n\nif Yes, Identify Danger Signs**\n(atleast 1)");
        $mc('CE4:CE5', "Identified with High BP/ Danger Signs and referred?\n\n1 - Yes\n0 - No");
        $mc('CF4:CF5', "A - Resident\nB - Trans in\nC - Trans Out before completing 4PNC");
        $mc('CG4:CI5', "Iron with Folic Acid Supplementation\n\n#: Number of Tablets Given\nd: Date (mm/dd/yy)");
        $mc('CJ4:CJ6', "Completed IFA supplementation?\n\n1 - Yes\n0 - No\n\nif Yes, date completed (mm/dd/yy)");
        $mc('CK4:CK6', "Completed 200,000 I.U. of Vitamin A capsule  supplementation?\n(within 1 month after delivery)\n\n1 - Yes\n0 - No\n\nif Yes, date completed (mm/dd/yy)");

        // ── Row 5 recommended timings + BMI + Lab sub-cols ──
        $mc('K5:L5',   "Recommended Timing:\nVisit (SHP) 2: 14-20 weeks\nVisit (SHP) 3: 21-27 weeks");
        $mc('M5:Q5',   "Recommended Timing:\nVisit (SHP) 4: 28-30 weeks\nVisit (SHP) 5: 31-34 weeks\nVisit (SHP) 6: 35 weeks\nVisit (SHP) 7: 36 weeks\nVisit (SHP)  8: 37-40 weeks");
        $mc('W5:W6',   "Low: \n<18.5 kg/m2");
        $mc('X5:X6',   "Normal: \n18.5 - 22.9 kg/m2");
        $mc('Y5:Y6',   "High: \n\u2265 23.0 kg/m2");
        $mc('AY5:AY6', "Date Screened\n(mm/dd/yy)");
        $mc('AZ5:AZ6', "Result:\n\n1 - with anemia\n0 - w/o anemia");
        $mc('BA5:BA6', "Date Screened\n(mm/dd/yy)");
        $mc('BB5:BB6', "Result:\n\n1 - positive\n0 - negative");
        $mc('BC5:BC6', "Date Screened\n(mm/dd/yy)");
        $mc('BD5:BD6', "Result:\n\n1 - Reactive\n0 - Non-reactive");
        $mc('BE5:BE6', "Date Screened\n(mm/dd/yy)");
        $mc('BF5:BF6', "Result:\n\n1 - Reactive\n0 - Non-reactive");
        $mc('BG5:BG6', "Date Screened\n(mm/dd/yy)");
        $mc('BH5:BH6', "Result:\n\n1 - Reactive\n0 - Non-reactive");
        $mc('BI5:BI6', "Date of Confirmatory Test\n(mm/dd/yy)");
        $mc('BJ5:BJ6', "Result:\n\n1 - Positive\n0 - Negative");
        $mc('BK5:BK6', "Treatment:\n\nGiven at least 1 dose of benzathine penicillin 2.4 mU at least 30 days prior to delivery\n\n1 - Yes\n0 - No ");
        $mc('BR5:BR6', "Facility Type\n1 - Public\n2 - Private");
        $mc('BS5:BS6', "Non-Health Facility\n1 - Home\n2 - Others (including emergency transport)");

        // ── Row 6 visit labels ──
        foreach (['J6'=>'Visit 1','K6'=>'Visti 2','L6'=>'Vist 3','M6'=>'Visit 4',
                  'N6'=>'Visit 5','O6'=>'Visit 6','P6'=>'Visit 7','Q6'=>'Visit 8'] as $c=>$v) {
            $sheet->setCellValue($c, $v);
        }
        $sheet->setCellValue('U6', "Date referred:\nd: (mm/dd/yy)");
        $sheet->setCellValue('V6', "Date\nd: (mm/dd/yy)");
        foreach (['AG6'=>"1st visit\n(1st tri)",'AH6'=>"2nd visit\n(2nd tri)",'AI6'=>"3rd visit\n(2nd tri)",
                  'AJ6'=>"4th visit\n(3rd tri)",'AK6'=>"5th visit\n(3rd tri)",'AL6'=>"6th visit\n(3rd tri)"] as $c=>$v) {
            $sheet->setCellValue($c, $v);
        }
        foreach (['AN6'=>"1st visit\n(1st tri)",'AO6'=>"2nd visit\n(2nd tri)",'AP6'=>"3rd visit\n(2nd tri)",
                  'AQ6'=>"4th visit\n(3rd tri)",'AR6'=>"5th visit\n(3rd tri)",'AS6'=>"6th visit\n(3rd tri)"] as $c=>$v) {
            $sheet->setCellValue($c, $v);
        }
        foreach (['AU6'=>"2nd visit\n(2nd tri)",'AV6'=>"3rd visit\n(3rd tri)",'AW6'=>"4th visit\n(3rd tri)"] as $c=>$v) {
            $sheet->setCellValue($c, $v);
        }
        foreach (['BX6'=>'Visit 1','BY6'=>'Visit 2','BZ6'=>'Visit 3','CA6'=>'Visit 4'] as $c=>$v) {
            $sheet->setCellValue($c, $v);
        }
        $sheet->setCellValue('CE6', "Date referred:\nd: (mm/dd/yy)");
        $sheet->setCellValue('CF6', "Date\nd: (mm/dd/yy)");
        foreach (['CG6'=>'1st visit','CH6'=>'2nd visit','CI6'=>'3rd visit'] as $c=>$v) {
            $sheet->setCellValue($c, $v);
        }

        // ── Apply header styles to rows 2-6 then override non-bold cells ──
        $sheet->getStyle('A2:CL6')->applyFromArray($hBold);
        foreach (['J4','K5:Q5','BX4:CA4'] as $range) {
            $sheet->getStyle($range)->getFont()->setBold(false);
        }
        $sheet->getRowDimension(3)->setRowHeight(20.4);
        $sheet->getRowDimension(4)->setRowHeight(20.4);
        $sheet->getRowDimension(5)->setRowHeight(51.0);
        $sheet->getRowDimension(6)->setRowHeight(20.4);

        /*
        |----------------------------------------------------------------------
        | 6.  DATA ROW LOOP  —  2 rows per patient record
        |----------------------------------------------------------------------
        | Columns merged across both rows (same value): A-G, I, R-T, W-Y,
        | Z-AD, AE, AY-BK (labs), BL, BM-BW (intrapartum), CB-CD, CL.
        | Columns with split values (row-1 / row-2):
        |   H (LMP / G-P),  J-Q (d: / bp:),  U (referred / date),
        |   V (class / date),  AF (deworming 1/0 / date),
        |   AG-AL (IFA #: / d:),  AM (completed 1/0 / date),
        |   AN-AS (MM #: / d:),   AT (completed / date),
        |   AU-AW (CC #: / d:),   AX (completed / date),
        |   BX-CA (d: / bp:),     CE (referred / date),
        |   CF (classification / date), CG-CI (IFA #: / d:),
        |   CJ (completed / date), CK (VitA / date)
        |----------------------------------------------------------------------
        */
        $staticMergeCols = [
            1,2,3,4,5,6,7,                          // A-G
            9,                                         // I (EDD)
            18,19,20,                                  // R-T (ANC status)
            23,24,25,                                  // W-Y (BMI)
            26,27,28,29,30,                            // Z-AD (Td1-5)
            31,                                        // AE (prenatal remarks)
            51,52,53,54,55,56,57,58,59,60,61,62,63,   // AY-BK (labs)
            64,                                        // BL (lab remarks)
            65,66,67,68,69,70,71,72,73,74,             // BM-BV (intrapartum)
            75,                                        // BW (intrapartum remarks)
            80,81,82,                                  // CB-CD (PP status)
            90,                                        // CL (PP remarks)
        ];

        $currentRow   = 7;
        $totalRows    = max($records->count(), 10); // minimum 10 placeholder rows

        for ($idx = 1; $idx <= $totalRows; $idx++) {
            $rec = $records->get($idx - 1);
            $r1  = $currentRow;
            $r2  = $currentRow + 1;

            // Merge static columns
            foreach ($staticMergeCols as $col) {
                $ltr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $sheet->mergeCells("{$ltr}{$r1}:{$ltr}{$r2}");
            }

            if ($rec) {
                $anc   = $rec->prenatal8Anc;
                $imm   = $rec->prenatalImmunization;
                $lab   = $rec->prenatalLabScreening;
                $supp  = $rec->prenatalSupplementation;
                $intra = $rec->intrapartum;
                $pp    = $rec->postpartum;

                // ── A-I: Patient Core ──
                $sheet->setCellValue("A{$r1}", $idx);
                $sheet->setCellValue("B{$r1}", $fmt($rec->registrationDate));
                $sheet->setCellValue("C{$r1}", $rec->familySerialNumber ?? '');
                $sheet->setCellValue("D{$r1}", strtoupper($rec->patientName ?? ''));
                $sheet->setCellValue("E{$r1}", $rec->homeAddress ?? '');
                $sheet->setCellValue("F{$r1}", $rec->age ?? '');
                $age = (int)($rec->age ?? 0);
                $grp = $age >= 10 && $age <= 14 ? 'A' : ($age >= 15 && $age <= 19 ? 'B' : ($age >= 20 && $age <= 49 ? 'C' : ''));
                $sheet->setCellValue("G{$r1}", $rec->ageGroup ?? $grp);
                $sheet->setCellValue("H{$r1}", 'LMP: ' . $fmt($rec->ImpDate));
                $sheet->setCellValue("H{$r2}", 'G-P: ' . ($rec->gravidaPara ?? ''));
                $sheet->setCellValue("I{$r1}", $fmt($rec->eddDate));

                // ── J-Q: 8 ANC Visits ──
                $vDates = ['visit1Date','visit2Date','visit3Date','visit4Date','visit5Date','visit6Date','visit7Date','visit8Date'];
                $vBps   = ['visit1Bp',  'visit2Bp',  'visit3Bp',  'visit4Bp',  'visit5Bp',  'visit6Bp',  'visit7Bp',  'visit8Bp'];
                foreach (range(10, 17) as $i => $colNum) {
                    $ltr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum);
                    $d   = $anc ? $fmt($anc->{$vDates[$i]}) : '';
                    $bp  = $anc ? ($anc->{$vBps[$i]} ?? '') : '';
                    $sheet->setCellValue("{$ltr}{$r1}", $d   ? "d: {$d}"  : 'd:');
                    $sheet->setCellValue("{$ltr}{$r2}", $bp  ? "bp: {$bp}" : 'bp: ');
                }

                // ── R-V: ANC Status & Classification ──
                $sheet->setCellValue("R{$r1}", $anc ? ($anc->completed8Anc ?? '') : '');
                $sheet->setCellValue("S{$r1}", $anc ? ($anc->highBp         ?? '') : '');
                $sheet->setCellValue("T{$r1}", $anc ? ($anc->dangerSigns    ?? '') : '');
                $sheet->setCellValue("U{$r1}", $anc ? ($anc->highBpReferred ?? '') : '');
                $sheet->setCellValue("U{$r2}", ($anc && $anc->dateReferred)     ? 'd: '.$fmt($anc->dateReferred)         : 'd:');
                $sheet->setCellValue("V{$r1}", $anc ? ($anc->classificationStatus ?? '') : '');
                $sheet->setCellValue("V{$r2}", ($anc && $anc->classificationDate) ? 'd: '.$fmt($anc->classificationDate) : 'd:');

                // ── W-Y: BMI (write value only in the matching status column) ──
                $bmiSt  = $rec->bmiStatus ?? '';
                $bmiVal = $rec->bmiValue  ?? '';
                $sheet->setCellValue("W{$r1}", $bmiSt === 'Underweight'    ? $bmiVal : '');
                $sheet->setCellValue("X{$r1}", $bmiSt === 'Normal Weight' ? $bmiVal : '');
                $sheet->setCellValue("Y{$r1}", $bmiSt === 'Obese' || $bmiSt === 'Overweight'  ? $bmiVal : '');

                // ── Z-AD: Td Immunization ──
                foreach (['Z'=>'td1Date','AA'=>'td2Date','AB'=>'td3Date','AC'=>'td4Date','AD'=>'td5Date'] as $ltr => $fld) {
                    $sheet->setCellValue("{$ltr}{$r1}", $imm ? $fmt($imm->{$fld}) : '');
                }

                // ── AE: Prenatal Remarks / Danger-Sign Detail ──
                $sheet->setCellValue("AE{$r1}", $anc ? ($anc->dangerSignsDetail ?? '') : '');

                // ── AF: Deworming ──
                $sheet->setCellValue("AF{$r1}", $supp !== null ? ($supp->received_deworming ? '1' : '0') : '');
                $sheet->setCellValue("AF{$r2}", ($supp && $supp->deworming_date) ? 'd: '.$fmt($supp->deworming_date) : 'd:');

                // ── AG-AL: IFA (6 visits) ──
                foreach ([
                    ['AG','ifa_v1_num','ifa_v1_date'],['AH','ifa_v2_num','ifa_v2_date'],
                    ['AI','ifa_v3_num','ifa_v3_date'],['AJ','ifa_v4_num','ifa_v4_date'],
                    ['AK','ifa_v5_num','ifa_v5_date'],['AL','ifa_v6_num','ifa_v6_date'],
                ] as [$ltr,$nF,$dF]) {
                    $sheet->setCellValue("{$ltr}{$r1}", '#: '.($supp ? ($supp->{$nF} ?? '') : ''));
                    $sheet->setCellValue("{$ltr}{$r2}", ($supp && $supp->{$dF}) ? 'd: '.$fmt($supp->{$dF}) : 'd:');
                }

                // ── AM: Completed IFA ──
                $sheet->setCellValue("AM{$r1}", $supp !== null ? ($supp->completed_ifa ? '1' : '0') : '');
                $sheet->setCellValue("AM{$r2}", ($supp && $supp->ifa_completed_date) ? 'd: '.$fmt($supp->ifa_completed_date) : 'd:');

                // ── AN-AS: MM (6 visits) ──
                foreach ([
                    ['AN','mm_v1_num','mm_v1_date'],['AO','mm_v2_num','mm_v2_date'],
                    ['AP','mm_v3_num','mm_v3_date'],['AQ','mm_v4_num','mm_v4_date'],
                    ['AR','mm_v5_num','mm_v5_date'],['AS','mm_v6_num','mm_v6_date'],
                ] as [$ltr,$nF,$dF]) {
                    $sheet->setCellValue("{$ltr}{$r1}", '#: '.($supp ? ($supp->{$nF} ?? '') : ''));
                    $sheet->setCellValue("{$ltr}{$r2}", ($supp && $supp->{$dF}) ? 'd: '.$fmt($supp->{$dF}) : 'd:');
                }

                // ── AT: Completed MM ──
                $sheet->setCellValue("AT{$r1}", $supp !== null ? ($supp->completed_mm ? '1' : '0') : '');
                $sheet->setCellValue("AT{$r2}", ($supp && $supp->mm_completed_date) ? 'd: '.$fmt($supp->mm_completed_date) : 'd:');

                // ── AU-AW: CC (visits 2-4) ──
                foreach ([
                    ['AU','cc_v2_num','cc_v2_date'],
                    ['AV','cc_v3_num','cc_v3_date'],
                    ['AW','cc_v4_num','cc_v4_date'],
                ] as [$ltr,$nF,$dF]) {
                    $sheet->setCellValue("{$ltr}{$r1}", '#: '.($supp ? ($supp->{$nF} ?? '') : ''));
                    $sheet->setCellValue("{$ltr}{$r2}", ($supp && $supp->{$dF}) ? 'd: '.$fmt($supp->{$dF}) : 'd:');
                }

                // ── AX: Completed CC ──
                $sheet->setCellValue("AX{$r1}", $supp !== null ? ($supp->completed_cc ? '1' : '0') : '');
                $sheet->setCellValue("AX{$r2}", ($supp && $supp->cc_completed_date) ? 'd: '.$fmt($supp->cc_completed_date) : 'd:');

                // ── AY-BK: Laboratory Screenings ──
                $sheet->setCellValue("AY{$r1}", $lab ? $fmt($lab->cbcDate)       : '');
                $sheet->setCellValue("AZ{$r1}", $lab ? ($lab->cbcResult    ?? '') : '');
                $sheet->setCellValue("BA{$r1}", $lab ? $fmt($lab->gdmDate)       : '');
                $sheet->setCellValue("BB{$r1}", $lab ? ($lab->gdmResult    ?? '') : '');
                $sheet->setCellValue("BC{$r1}", $lab ? $fmt($lab->hepBDate)      : '');
                $sheet->setCellValue("BD{$r1}", $lab ? ($lab->hepBResult   ?? '') : '');
                $sheet->setCellValue("BE{$r1}", $lab ? $fmt($lab->hivDate)       : '');
                $sheet->setCellValue("BF{$r1}", $lab ? ($lab->hivResult    ?? '') : '');
                $sheet->setCellValue("BG{$r1}", $lab ? $fmt($lab->syphilisDate)  : '');
                $sheet->setCellValue("BH{$r1}", $lab ? ($lab->syphilisResult ?? '') : '');
                // BI, BJ, BK (confirmatory + treatment) — not in current DB schema
                $sheet->setCellValue("BI{$r1}", $lab ? ($lab->syphilisConfirmatoryDate    ?? '') : '');
                $sheet->setCellValue("BJ{$r1}", $lab ? $fmt($lab->syphilisConfirmatoryResult)  : '');
                $sheet->setCellValue("BK{$r1}", $lab ? ($lab->syphilisTreatment ?? '') : '');

                // ── BL: Lab Remarks ──
                $labRemarks = array_filter([
                    $lab ? ($lab->cbcRemarks     ?? '') : '',
                    $lab ? ($lab->gdmRemarks     ?? '') : '',
                    $lab ? ($lab->hepBRemarks    ?? '') : '',
                    $lab ? ($lab->hivRemarks     ?? '') : '',
                    $lab ? ($lab->syphilisRemarks ?? '') : '',
                ]);
                $sheet->setCellValue("BL{$r1}", implode('; ', $labRemarks));

                // ── BM-BW: Intrapartum ──
                $sheet->setCellValue("BM{$r1}", $intra ? ($intra->deliveryOutcome      ?? '') : '');
                $sheet->setCellValue("BN{$r1}", $intra ? ($intra->deliveryType         ?? '') : '');
                $sheet->setCellValue("BO{$r1}", $intra ? ($intra->sex                  ?? '') : '');
                $sheet->setCellValue("BP{$r1}", $intra ? ($intra->birthWeight          ?? '') : '');
                $sheet->setCellValue("BQ{$r1}", $intra ? ($intra->weightClassification ?? '') : '');
                $sheet->setCellValue("BR{$r1}", $intra ? ($intra->placeOfDelivery == 'Public Facility' || $intra->placeOfDelivery == 'Private Facility' ? $intra->placeOfDelivery : '') : '');

                $sheet->setCellValue("BS{$r1}", $intra ? ($intra->placeOfDelivery == 'Home' || $intra->placeOfDelivery == 'Other' ? $intra->placeOfDelivery : '') : '');
                $sheet->setCellValue("BT{$r1}", $intra ? ($intra->attendantAtBirth     ?? '') : '');
                $sheet->setCellValue("BU{$r1}", $intra ? $fmt($intra->deliveryDate)          : '');
                $sheet->setCellValue("BV{$r1}", $intra ? ($intra->deliveryTime         ?? '') : '');
                $sheet->setCellValue("BW{$r1}", $intra ? ($intra->remarks              ?? '') : '');

                // ── BX-CA: 4PNC Visits (d: row-1 / bp: row-2) ──
                foreach ([
                    ['BX','visit24hDate','bpSys24h', 'bpDias24h'],
                    ['BY','visit1wDate', 'bpSys1w',  'bpDias1w'],
                    ['BZ','visit2_4wDate','bpSys2_4w','bpDias2_4w'],
                    ['CA','visit4_6wDate','bpSys4_6w','bpDias4_6w'],
                ] as [$ltr,$dF,$sF,$diF]) {
                    $d  = ($pp && $pp->{$dF}) ? $fmt($pp->{$dF}) : '';
                    $bp = ($pp && $pp->{$sF} && $pp->{$diF}) ? "{$pp->{$sF}}/{$pp->{$diF}}" : '';
                    $sheet->setCellValue("{$ltr}{$r1}", $d  ? "d: {$d}"   : 'd:');
                    $sheet->setCellValue("{$ltr}{$r2}", $bp ? "bp: {$bp}" : 'bp: ');
                }

                // ── CB-CD: Postpartum Status ──
                $sheet->setCellValue("CB{$r1}", $pp ? ($pp->PostpartumClassification ?? '') : '');
                $sheet->setCellValue("CC{$r1}", $pp ? ($pp->highBpGeneral            ?? '') : '');
                $dsItems = [];
                if ($pp) {
                    if (!empty($pp->dsBleeding))  $dsItems[] = 'A-Bleeding';
                    if (!empty($pp->dsVision))    $dsItems[] = 'B-Vision';
                    if (!empty($pp->dsAbdominal)) $dsItems[] = 'C-Abdominal';
                    if (!empty($pp->dsFever))     $dsItems[] = 'D-Fever';
                    if (!empty($pp->dsBreathing)) $dsItems[] = 'E-Breathing';
                }
                $dsText = $pp
                    ? (($pp->dangerSignsGeneral ?? '') . (!empty($dsItems) ? "\n" . implode(', ', $dsItems) : ''))
                    : '';
                $sheet->setCellValue("CD{$r1}", $dsText);

                // ── CE: Referred (row-1) / Referral Date (row-2) ──
                $sheet->setCellValue("CE{$r1}", $pp ? ($pp->referredGeneral     ?? '') : '');
                $sheet->setCellValue("CE{$r2}", ($pp && $pp->referralDateGeneral) ? 'd: '.$fmt($pp->referralDateGeneral) : 'd:');

                // ── CF: Classification (row-1) / Breastfeeding initiation date (row-2) ──
                $sheet->setCellValue("CF{$r1}", $pp ? ($pp->classificationDate ? ($pp->PostpartumClassification ?? '') : '') : '');
                $sheet->setCellValue("CF{$r2}", ($pp && $pp->breastfeedingInitiationDate) ? 'd: '.$fmt($pp->breastfeedingInitiationDate) : 'd:');

                // ── CG-CI: Postpartum IFA (3 visits) ──
                foreach ([
                    ['CG','ironTabs1st','ironDate1st'],
                    ['CH','ironTabs2nd','ironDate2nd'],
                    ['CI','ironTabs3rd','ironDate3rd'],
                ] as [$ltr,$tF,$dF]) {
                    $sheet->setCellValue("{$ltr}{$r1}", '#: '.($pp ? ($pp->{$tF} ?? '') : ''));
                    $sheet->setCellValue("{$ltr}{$r2}", ($pp && $pp->{$dF}) ? 'd: '.$fmt($pp->{$dF}) : 'd:');
                }

                // ── CJ: Completed Postpartum IFA / CK: Vitamin A ──
                $sheet->setCellValue("CJ{$r1}", $pp !== null ? ($pp->completedIfa  ? '1' : '0') : '');
                $sheet->setCellValue("CJ{$r2}", ($pp && $pp->ifaCompletionDate) ? 'd: '.$fmt($pp->ifaCompletionDate)  : 'd:');
                $sheet->setCellValue("CK{$r1}", $pp !== null ? ($pp->completedVitA ? '1' : '0') : '');
                $sheet->setCellValue("CK{$r2}", ($pp && $pp->vitACompletionDate) ? 'd: '.$fmt($pp->vitACompletionDate) : 'd:');

                // ── CL: Postpartum Remarks ──
                $sheet->setCellValue("CL{$r1}", '');

            } else {
                // ── Empty placeholder row (keeps structural look for minimum 10 rows) ──
                $sheet->setCellValue("A{$r1}", $idx);
                $sheet->setCellValue("H{$r1}", 'LMP:');
                $sheet->setCellValue("H{$r2}", 'G-P:');
                foreach (range(10, 17) as $colNum) {
                    $ltr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum);
                    $sheet->setCellValue("{$ltr}{$r1}", 'd:');
                    $sheet->setCellValue("{$ltr}{$r2}", 'bp: ');
                }
                foreach (['U','V'] as $ltr) {
                    $sheet->setCellValue("{$ltr}{$r1}", '');
                    $sheet->setCellValue("{$ltr}{$r2}", 'd:');
                }
                foreach (['AF','AG','AH','AI','AJ','AK','AL','AN','AO','AP','AQ','AR','AS','AU','AV','AW'] as $ltr) {
                    $sheet->setCellValue("{$ltr}{$r1}", '#:');
                    $sheet->setCellValue("{$ltr}{$r2}", 'd:');
                }
                foreach (['AM','AT','AX','CE','CF','CG','CH','CI','CJ','CK'] as $ltr) {
                    $sheet->setCellValue("{$ltr}{$r1}", '');
                    $sheet->setCellValue("{$ltr}{$r2}", 'd:');
                }
                foreach (['BX','BY','BZ','CA'] as $ltr) {
                    $sheet->setCellValue("{$ltr}{$r1}", 'd:');
                    $sheet->setCellValue("{$ltr}{$r2}", 'bp: ');
                }
            }

            $sheet->getRowDimension($r1)->setRowHeight(13.2);
            $sheet->getRowDimension($r2)->setRowHeight(13.2);
            $currentRow += 2;
        }

        /*
        |----------------------------------------------------------------------
        | 7.  FOOTNOTE ROWS (danger-sign and BP legend)
        |----------------------------------------------------------------------
        */
        $lastDataRow = $currentRow - 1;
        $fn1 = $currentRow;
        $fn2 = $currentRow + 1;

        $sheet->mergeCells("A{$fn1}:C{$fn1}");
        $sheet->setCellValue("A{$fn1}", '* High/Elevated BP refers to: Systolic BP ≥ 140 mmHg or Diastolic BP ≥ 90 mmHg  ');
        $sheet->mergeCells("A{$fn2}:C{$fn2}");
        $sheet->setCellValue("A{$fn2}", '** Danger signs: A - new-onset severe headache                             B - visual disturbances                             C - Severe epigastric pain');
        $sheet->getStyle("A{$fn1}:A{$fn2}")->applyFromArray(['font' => ['size' => 8, 'name' => 'Arial', 'italic' => true]]);
        $sheet->getRowDimension($fn1)->setRowHeight(11.25);
        $sheet->getRowDimension($fn2)->setRowHeight(13.2);

        /*
        |----------------------------------------------------------------------
        | 8.  BORDERS + DATA ROW ALIGNMENT
        |----------------------------------------------------------------------
        */
        $sheet->getStyle("A1:CL{$lastDataRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $sheet->getStyle("A7:CL{$lastDataRow}")->applyFromArray([
            'font'      => ['size' => 8, 'name' => 'Arial'],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
        ]);

        // Left-align long free-text columns
        foreach (["D7:E{$lastDataRow}","AE7:AE{$lastDataRow}","BL7:BL{$lastDataRow}",
                  "BW7:BW{$lastDataRow}","CL7:CL{$lastDataRow}"] as $range) {
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        /*
        |----------------------------------------------------------------------
        | 9.  COLUMN WIDTHS (matched from template)
        |----------------------------------------------------------------------
        */
        $colWidths = [
            'A'  => 5.22,  'B'  => 8.78,  'C'  => 9.22,  'D'  => 37.44, 'E'  => 28.78,
            'F'  => 6.44,  'H'  => 10.78, 'J'  => 12.33, 'R'  => 10.78, 'S'  => 12.89,
            'W'  => 8.22,  'Z'  => 10.78, 'AE' => 30.66, 'AG' => 10.00, 'AN' => 10.11,
            'AU' => 10.78, 'AY' => 9.89,  'AZ' => 10.00, 'BA' => 9.22,  'BB' => 8.00,
            'BC' => 12.11, 'BD' => 11.44, 'BE' => 13.33, 'BF' => 13.00, 'BG' => 9.33,
            'BH' => 10.44, 'BI' => 9.78,  'BK' => 20.00, 'BL' => 31.66, 'BP' => 13.11,
            'BR' => 9.00,  'BT' => 10.78, 'BU' => 7.78,  'BV' => 10.78, 'BW' => 30.22,
            'BX' => 12.00, 'CB' => 9.00,  'CC' => 13.00, 'CG' => 12.00, 'CJ' => 13.66,
            'CK' => 18.78, 'CL' => 31.66,
        ];
        foreach ($colWidths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        /*
        |----------------------------------------------------------------------
        | 10. WRITE + STREAM FILE
        |----------------------------------------------------------------------
        */
        $filename = "TCL_MATERNAL_CARE_{$year}_{$barangay}.xlsx";
        $savePath = storage_path("app/{$filename}");

        (new Xlsx($spreadsheet))->save($savePath);

        return response()->download($savePath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Download Target Client List (TCL) for School & Community Based Immunization
     * Layout mirrors Child_Immunication_School.xlsx — sheet: TCL_Child_School_immu
     *
     * Header structure : rows 1–4 (title + 3-tier column headers)
     * Data structure   : 1 row per record
     * Total columns    : P (16)
     */
    public function childImmunicationSchoolDownload(Request $request)
    {
        $year     = $request->input('year', date('Y'));
        $barangay = $request->input('barangay', 'All');

        $records = DB::table('child_immunization_school_records')
            ->when($barangay !== 'All', fn($q) => $q->where('address', 'like', "%{$barangay}%"))
            ->whereYear('created_at', $year)
            ->orderBy('created_at')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('TCL_Child_School_immu');
        $sheet->setShowGridlines(true);

        $FILL_H  = 'CFE2F3';
        $FILL_CB = 'E2EFDA'; // light green for CBI section

        $hStyle = [
            'font'      => ['bold' => true, 'size' => 8, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
            'fill'      => ['fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $FILL_H]],
        ];

        $mc = fn(string $range, $val) => $sheet->mergeCells($range)
            && $sheet->setCellValue(explode(':', $range)[0], $val);

        /*
        |--------------------------------------------------------------------------
        | 1. TITLE ROW
        |--------------------------------------------------------------------------
        */
        $mc('A1:P1', "TARGET CLIENT LIST FOR SCHOOL & COMMUNITY BASED IMMUNIZATION");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        /*
        |--------------------------------------------------------------------------
        | 2. COLUMN HEADERS (rows 2–4, 3-tier)
        |--------------------------------------------------------------------------
        | A   No.
        | B   Date of Registration
        | C   Family Serial Number
        | D   Name of Child
        | E   Date of Birth
        | F   Sex
        | G   Age (in years)
        | H   Complete Address
        | I   Grade Level
        | J   Td vaccine (SBI)
        | K   MR vaccine (SBI)
        | L   HPV 1st dose (SBI)
        | M   CBI – HPV 1st dose (Female aged 9)
        | N   CBI – HPV 2nd dose
        | O   Completed 2 HPV doses?
        | P   Remarks
        |--------------------------------------------------------------------------
        */
        // Row 2: top-level span headers
        foreach (['A2:A4','B2:B4','C2:C4','D2:D4','E2:E4','F2:F4','G2:G4','H2:H4','I2:I4'] as $r) {
            $sheet->mergeCells($r);
        }
        $sheet->setCellValue('A2', 'No.');
        $sheet->setCellValue('B2', "Date of Registration\n(mm/dd/yy)");
        $sheet->setCellValue('C2', 'Family Serial Number');
        $sheet->setCellValue('D2', "Name of Child\n(LastName, FullName, MI)");
        $sheet->setCellValue('E2', "Date of Birth\n(mm/dd/yy)");
        $sheet->setCellValue('F2', "Sex\n\nM - Male\nF- Female");
        $sheet->setCellValue('G2', "Age\n(in years)");
        $sheet->setCellValue('H2', 'Complete Address');
        $sheet->setCellValue('I2', "Grade Level\n\nA - Grade 1\nB - Grade 4\nC - Grade 7\nD - Not Enrolled");

        // SBI columns J-L (merged rows 2-4 individually)
        foreach (['J2:J4','K2:K4','L2:L4'] as $r) { $sheet->mergeCells($r); }
        $sheet->setCellValue('J2', "Tetanus diphtheria toxoid (Td) vaccine\n(mm/dd/yy)");
        $sheet->setCellValue('K2', "Measles Rubella (MR) vaccine\n(mm/dd/yy)");
        $sheet->setCellValue('L2', "(SBI)\n\nHuman Papillomavirus (HPV) vaccine\n1st dose\n(mm/dd/yy)");

        // CBI section span header
        $mc('M2:O2', 'Community Based Immunization (CBI)');
        $sheet->setCellValue('M3', "Female aged 9 years old vaccinated\nwith the first dose of HPV\n(mm/dd/yy)");
        $sheet->setCellValue('N3', "Human Papillomavirus (HPV) vaccine\n2nd dose\n(mm/dd/yy)");
        $sheet->setCellValue('O3', "Completed 2 HPV doses?\n\n1 - Yes\n0 - No\n\nif Yes, date completed (mm/dd/yy)");
        foreach (['M3:M4','N3:N4','O3:O4'] as $r) { $sheet->mergeCells($r); }

        // Remarks
        foreach (['P2:P4'] as $r) { $sheet->mergeCells($r); }
        $sheet->setCellValue('P2', "Remarks/\nActions Taken");

        $sheet->getStyle('A2:P4')->applyFromArray($hStyle);
        // Highlight CBI section distinctly
        $sheet->getStyle('M2:O4')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB($FILL_CB);

        $sheet->getRowDimension(2)->setRowHeight(40);
        $sheet->getRowDimension(3)->setRowHeight(40);
        $sheet->getRowDimension(4)->setRowHeight(15);

        /*
        |--------------------------------------------------------------------------
        | 3. DATA ROWS
        |--------------------------------------------------------------------------
        */
        $fmt   = fn($d) => (!empty($d) && strtotime($d)) ? date('m/d/Y', strtotime($d)) : '';
        $bool1 = fn($v) => $v ? '1' : '0';

        $currentRow  = 5;
        $totalRows   = max($records->count(), 10);

        for ($idx = 1; $idx <= $totalRows; $idx++) {
            $rec = $records->get($idx - 1);

            $sheet->setCellValue("A{$currentRow}", $idx);

            if ($rec) {
                $sheet->setCellValue("B{$currentRow}", $fmt($rec->registrationDate));
                $sheet->setCellValue("C{$currentRow}", $rec->familySerialNumber ?? '');
                $sheet->setCellValue("D{$currentRow}", strtoupper($rec->childName ?? ''));
                $sheet->setCellValue("E{$currentRow}", $fmt($rec->dateOfBirth));
                $sheet->setCellValue("F{$currentRow}", $rec->sex ?? '');
                $sheet->setCellValue("G{$currentRow}", $rec->ageYears ?? '');
                $sheet->setCellValue("H{$currentRow}", $rec->address ?? '');
                $sheet->setCellValue("I{$currentRow}", $rec->gradeLevel ?? '');
                $sheet->setCellValue("J{$currentRow}", $fmt($rec->tdDate));
                $sheet->setCellValue("K{$currentRow}", $fmt($rec->mrDate));
                $sheet->setCellValue("L{$currentRow}", $fmt($rec->hpv1SbiDate));
                $sheet->setCellValue("M{$currentRow}", $fmt($rec->hpv1CbiDate));
                $sheet->setCellValue("N{$currentRow}", $fmt($rec->hpv2CbiDate));
                // O: completed flag + completion date
                $hpvDone = $rec->hpvCompleted ? '1' : '0';
                $hpvDate = $rec->hpvCompleted && !empty($rec->hpvCompletedDate)
                    ? "\nd: " . $fmt($rec->hpvCompletedDate) : '';
                $sheet->setCellValue("O{$currentRow}", $hpvDone . $hpvDate);
                $sheet->setCellValue("P{$currentRow}", $rec->remarks ?? '');
            }

            $sheet->getRowDimension($currentRow)->setRowHeight(20);
            $currentRow++;
        }

        /*
        |--------------------------------------------------------------------------
        | 4. BORDERS, WIDTHS, ALIGNMENT
        |--------------------------------------------------------------------------
        */
        $lastRow = $currentRow - 1;

        $sheet->getStyle("A2:P{$lastRow}")->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle("A5:P{$lastRow}")->applyFromArray([
            'font'      => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
        ]);
        $sheet->getStyle("D5:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("H5:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("P5:P{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        foreach ([
            'A'=>5, 'B'=>13, 'C'=>14, 'D'=>28, 'E'=>12, 'F'=>9, 'G'=>8,
            'H'=>25, 'I'=>14, 'J'=>14, 'K'=>13, 'L'=>15, 'M'=>18, 'N'=>15, 'O'=>18, 'P'=>22,
        ] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. STREAM FILE
        |--------------------------------------------------------------------------
        */
        $filename = "TCL_CHILD_SCHOOL_IMMUNIZATION_{$year}_{$barangay}.xlsx";
        $savePath = storage_path("app/{$filename}");
        (new Xlsx($spreadsheet))->save($savePath);

        return response()->download($savePath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Download Target Client List (TCL) for Child Immunization (0–11 months)
     * Layout mirrors Child_Immunication.xlsx — sheet: TCL_Child_immu
     *
     * Header structure : rows 1–5 (title + 4-tier column headers)
     * Data structure   : 2 rows per patient (row-1 = Age / row-2 = Date)
     * Total columns    : AJ (36)
     */
    public function childImmunicationDownload(Request $request)
    {
        $year     = $request->input('year', date('Y'));
        $barangay = $request->input('barangay', 'All');

        $records = DB::table('child_immunization_records')
            ->when($barangay !== 'All', fn($q) => $q->where('address', 'like', "%{$barangay}%"))
            ->whereYear('created_at', $year)
            ->orderBy('created_at')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('TCL_Child_immu');
        $sheet->setShowGridlines(true);

        $FILL_H = 'CFE2F3';

        $hStyle = [
            'font'      => ['bold' => true, 'size' => 8, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
            'fill'      => ['fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $FILL_H]],
        ];

        $mc  = fn(string $range, $val) => $sheet->mergeCells($range)
            && $sheet->setCellValue(explode(':', $range)[0], $val);
        $fmt = fn($d) => (!empty($d) && strtotime($d)) ? date('m/d/Y', strtotime($d)) : '';

        /*
        |--------------------------------------------------------------------------
        | 1. TITLE ROW
        |--------------------------------------------------------------------------
        */
        $mc('A1:AJ1', 'TARGET CLIENT LIST FOR CHILD IMMUNIZATION');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        /*
        |--------------------------------------------------------------------------
        | 2. COLUMN HEADERS (rows 2–5)
        |
        | A  No.                    merged 2-5
        | B  Date of Registration   merged 2-5
        | C  Family Serial Number   merged 2-5
        | D  Name of Child          merged 2-5
        | E  Date of Birth          merged 2-5
        | F  Age (in months)        merged 2-5
        | G  Sex                    merged 2-5
        | H  Mother Name            merged 2-5
        | I  Complete Address       merged 2-5
        | J  CPAB – Td2             merged 2-5 (sub)
        | K  CPAB – Td3-5           merged 2-5 (sub)
        |    ── Immunization span (L2:AH2) ──
        | L  BCG within 24h
        | M  BCG late
        | N  Hepa B within 24h
        | O  Hepa B late
        | P  DPT 1st
        | Q  DPT 2nd
        | R  DPT 3rd
        | S  OPV 1st
        | T  OPV 2nd
        | U  OPV 3rd
        | V  IPV 1st
        | W  IPV 2nd
        | X  PCV 1st
        | Y  PCV 2nd
        | Z  PCV 3rd
        | AA MMR 1st
        | AB MMR 2nd
        |    ── FIC span (AC2:AE2) ──
        | AC □BCG
        | AD □DPT3
        | AE Date
        |    (row 2 also shows □OPV3, □MMR2 in the same block)
        |    ── CIC span (AF2:AH2) ──
        | AF □BCG
        | AG □DPT3
        | AH Date
        | AI Remarks   merged 2-5
        |--------------------------------------------------------------------------
        */
        // Patient core columns: merged rows 2-5
        foreach ([
            'A2:A5','B2:B5','C2:C5','D2:D5','E2:E5',
            'F2:F5','G2:G5','H2:H5','I2:I5',
        ] as $r) { $sheet->mergeCells($r); }

        $sheet->setCellValue('A2', 'No.');
        $sheet->setCellValue('B2', "Date of Registration\n(mm/dd/yy)");
        $sheet->setCellValue('C2', 'Family Serial Number');
        $sheet->setCellValue('D2', "Name of Child\n(LastName, FullName, MI)");
        $sheet->setCellValue('E2', "Date of Birth\n(mm/dd/yy)");
        $sheet->setCellValue('F2', "Age\n(in months)");
        $sheet->setCellValue('G2', "Sex\n\nM - Male\nF- Female");
        $sheet->setCellValue('H2', "Complete Name of Mother\n(LastName, FullName, MI)");
        $sheet->setCellValue('I2', 'Complete Address');

        // CPAB sub-columns (J-K) — merged rows 2-5
        $mc('J2:K2', "Children protected at Birth\n(CPAB)\nPlace a ✔ (check)");
        $mc('J3:J5', "Td2 given to the mother a month\nprior to delivery\n(for first-time pregnancies)");
        $mc('K3:K5', "Td3 to Td5 (or Td1 to Td5) given\nto the mother anytime prior\nto delivery");

        // Immunization span
        $mc('L2:AB2', 'Immunization');

        // BCG sub-span
        $mc('L3:M3', "BCG\n(mm/dd/yy)");
        $mc('L4:L5', "within 24 hours");
        $mc('M4:M5', "more than 24 hours\nto 11 months 29 days");

        // Hepa B sub-span
        $mc('N3:O3', "Hepa B\n(mm/dd/yy)");
        $mc('N4:N5', "within 24 hours\nafter birth");
        $mc('O4:O5', "more than 24 hours\nup to 14 days");

        // DPT
        $mc('P3:R3', 'DPT-HiB-HepB');
        $mc('P4:P5', "1st dose\n1 ½ mos");
        $mc('Q4:Q5', "2nd dose\n2 ½ mos");
        $mc('R4:R5', "3rd dose\n3 ½ mos");

        // OPV
        $mc('S3:U3', 'OPV');
        $mc('S4:S5', "1st dose\n1 ½ mos");
        $mc('T4:T5', "2nd dose\n2 ½ mos");
        $mc('U4:U5', "3rd dose\n3 ½ mos");

        // IPV
        $mc('V3:W3', 'IPV');
        $mc('V4:V5', "1st dose\n3 ½ mos");
        $mc('W4:W5', "2nd dose\n9 mos");

        // PCV
        $mc('X3:Z3', 'PCV');
        $mc('X4:X5', "1st dose\n1 ½ mos");
        $mc('Y4:Y5', "2nd dose\n2 ½ mos");
        $mc('Z4:Z5', "3rd dose\n3 ½ mos");

        // MMR
        $mc('AA3:AB3', "MMR\n\nNote: The minimum interval from\nMMR1 and MMR2 is at least 4 weeks\nbut MMR2 should not be given at <12 months");
        $mc('AA4:AA5', "1st dose\n9 mos");
        $mc('AB4:AB5', "2nd dose\n12 mos");

        // FIC span
        $mc('AC2:AE2', "FIC (0-11 months of previous year)\n\n1 dose BCG\n3 doses DPT-HiB-HepB\n3 doses OPV\n2 doses MMR");
        $mc('AC3:AC5', "□ BCG\n□ OPV3");
        $mc('AD3:AD5', "□ DPT3\n□ MMR2");
        $mc('AE3:AE5', "Date\n(mm/dd/yy)");

        // CIC span
        $mc('AF2:AH2', "CIC (0-11 months of previous year)\n– FIC of the previous year\n\n1 dose BCG\n3 doses DPT-HiB-HepB\n3 doses OPV\n2 doses MMR");
        $mc('AF3:AF5', "□ BCG\n□ OPV3");
        $mc('AG3:AG5', "□ DPT3\n□ MMR2");
        $mc('AH3:AH5', "Date\n(mm/dd/yy)");

        // Remarks
        $mc('AI2:AI5', "Remarks/\nActions Taken");

        $sheet->getStyle('A2:AI5')->applyFromArray($hStyle);

        // Row heights for header
        foreach ([2=>35, 3=>50, 4=>25, 5=>25] as $r => $h) {
            $sheet->getRowDimension($r)->setRowHeight($h);
        }

        // Row-label sub-headers (A: / d:) inside the immunization columns
        // These appear in the data rows themselves (row-1 = A:, row-2 = d:)
        // so we note this in row 4 sub-header already.
        // add one more sub-row just above data showing "A: (age)" / "d: (date)"
        $sheet->getRowDimension(5)->setRowHeight(22);

        /*
        |--------------------------------------------------------------------------
        | 3. DATA ROWS (2 rows per record)
        |--------------------------------------------------------------------------
        */
        // vaccine columns: [colLetter, ageField, dateField]
        $vaccines = [
            'L' => ['bcgWithin24hAge',  'bcgWithin24hDate'],
            'M' => ['bcgLateAge',        'bcgLateDate'],
            'N' => ['hepaBWithin24hAge', 'hepaBWithin24hDate'],
            'O' => ['hepaBLateAge',      'hepaBLateDate'],
            'P' => ['dpt1Age',           'dpt1Date'],
            'Q' => ['dpt2Age',           'dpt2Date'],
            'R' => ['dpt3Age',           'dpt3Date'],
            'S' => ['opv1Age',           'opv1Date'],
            'T' => ['opv2Age',           'opv2Date'],
            'U' => ['opv3Age',           'opv3Date'],
            'V' => ['ipv1Age',           'ipv1Date'],
            'W' => ['ipv2Age',           'ipv2Date'],
            'X' => ['pcv1Age',           'pcv1Date'],
            'Y' => ['pcv2Age',           'pcv2Date'],
            'Z' => ['pcv3Age',           'pcv3Date'],
            'AA'=> ['mmr1Age',           'mmr1Date'],
            'AB'=> ['mmr2Age',           'mmr2Date'],
        ];

        // Static columns merged across both rows
        $staticMergeCols = ['A','B','C','D','E','F','G','H','I','J','K','AI'];

        $currentRow = 6;
        $totalRows  = max($records->count(), 10);

        for ($idx = 1; $idx <= $totalRows; $idx++) {
            $rec = $records->get($idx - 1);
            $r1  = $currentRow;
            $r2  = $currentRow + 1;

            foreach ($staticMergeCols as $col) {
                $sheet->mergeCells("{$col}{$r1}:{$col}{$r2}");
            }

            $sheet->setCellValue("A{$r1}", $idx);

            if ($rec) {
                $sheet->setCellValue("B{$r1}", $fmt($rec->registrationDate));
                $sheet->setCellValue("C{$r1}", $rec->familySerialNumber ?? '');
                $sheet->setCellValue("D{$r1}", strtoupper($rec->childName ?? ''));
                $sheet->setCellValue("E{$r1}", $fmt($rec->dateOfBirth));
                $sheet->setCellValue("F{$r1}", $rec->ageMonths ?? '');
                $sheet->setCellValue("G{$r1}", $rec->sex ?? '');
                $sheet->setCellValue("H{$r1}", strtoupper($rec->motherName ?? ''));
                $sheet->setCellValue("I{$r1}", $rec->address ?? '');
                $sheet->setCellValue("J{$r1}", $rec->td2Mother ? '✔' : '');
                $sheet->setCellValue("K{$r1}", $rec->td3To5Mother ? '✔' : '');

                // Vaccine age (row-1) / date (row-2)
                foreach ($vaccines as $col => [$ageF, $dateF]) {
                    $sheet->setCellValue("{$col}{$r1}", !empty($rec->{$ageF})  ? "A: {$rec->{$ageF}}" : 'A:');
                    $sheet->setCellValue("{$col}{$r2}", !empty($rec->{$dateF}) ? "d: " . $fmt($rec->{$dateF}) : 'd:');
                }

                // FIC
                $ficBcg  = $rec->ficBcg  ? '□✔BCG'  : '□ BCG';
                $ficOpv3 = $rec->ficOpv3 ? '□✔OPV3' : '□ OPV3';
                $ficDpt3 = $rec->ficDpt3 ? '□✔DPT3' : '□ DPT3';
                $ficMmr2 = $rec->ficMmr2 ? '□✔MMR2' : '□ MMR2';
                $sheet->mergeCells("AC{$r1}:AC{$r2}");
                $sheet->mergeCells("AD{$r1}:AD{$r2}");
                $sheet->mergeCells("AE{$r1}:AE{$r2}");
                $sheet->setCellValue("AC{$r1}", "{$ficBcg}\n{$ficOpv3}");
                $sheet->setCellValue("AD{$r1}", "{$ficDpt3}\n{$ficMmr2}");
                $sheet->setCellValue("AE{$r1}", $rec->ficDate ? $fmt($rec->ficDate) : '');

                // CIC
                $cicBcg  = $rec->cicBcg  ? '□✔BCG'  : '□ BCG';
                $cicOpv3 = $rec->cicOpv3 ? '□✔OPV3' : '□ OPV3';
                $cicDpt3 = $rec->cicDpt3 ? '□✔DPT3' : '□ DPT3';
                $cicMmr2 = $rec->cicMmr2 ? '□✔MMR2' : '□ MMR2';
                $sheet->mergeCells("AF{$r1}:AF{$r2}");
                $sheet->mergeCells("AG{$r1}:AG{$r2}");
                $sheet->mergeCells("AH{$r1}:AH{$r2}");
                $sheet->setCellValue("AF{$r1}", "{$cicBcg}\n{$cicOpv3}");
                $sheet->setCellValue("AG{$r1}", "{$cicDpt3}\n{$cicMmr2}");
                $sheet->setCellValue("AH{$r1}", $rec->cicDate ? $fmt($rec->cicDate) : '');

                $sheet->setCellValue("AI{$r1}", $rec->remarks ?? '');
            } else {
                // Empty placeholder — keep A: / d: labels in vaccine columns
                foreach ($vaccines as $col => $_) {
                    $sheet->setCellValue("{$col}{$r1}", 'A:');
                    $sheet->setCellValue("{$col}{$r2}", 'd:');
                }
                foreach (['AC','AD','AE','AF','AG','AH'] as $col) {
                    $sheet->mergeCells("{$col}{$r1}:{$col}{$r2}");
                }
            }

            $sheet->getRowDimension($r1)->setRowHeight(13);
            $sheet->getRowDimension($r2)->setRowHeight(13);
            $currentRow += 2;
        }

        /*
        |--------------------------------------------------------------------------
        | 4. BORDERS, WIDTHS, ALIGNMENT
        |--------------------------------------------------------------------------
        */
        $lastRow = $currentRow - 1;

        $sheet->getStyle("A2:AI{$lastRow}")->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle("A6:AI{$lastRow}")->applyFromArray([
            'font'      => ['size' => 8, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
        ]);
        $sheet->getStyle("D6:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("H6:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("I6:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("AI6:AI{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $widths = [
            'A'=>5, 'B'=>12, 'C'=>13, 'D'=>28, 'E'=>11, 'F'=>8, 'G'=>7, 'H'=>25, 'I'=>22,
            'J'=>12, 'K'=>14,
            'L'=>10,'M'=>10,'N'=>10,'O'=>10,'P'=>9,'Q'=>9,'R'=>9,
            'S'=>9,'T'=>9,'U'=>9,'V'=>9,'W'=>9,'X'=>9,'Y'=>9,'Z'=>9,
            'AA'=>9,'AB'=>9,
            'AC'=>11,'AD'=>11,'AE'=>11,'AF'=>11,'AG'=>11,'AH'=>11,
            'AI'=>22,
        ];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. STREAM FILE
        |--------------------------------------------------------------------------
        */
        $filename = "TCL_CHILD_IMMUNIZATION_{$year}_{$barangay}.xlsx";
        $savePath = storage_path("app/{$filename}");
        (new Xlsx($spreadsheet))->save($savePath);

        return response()->download($savePath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Download Target Client List (TCL) for Management of Sick Children (IMCI)
     * Layout mirrors Child_Management_of_Sick.xlsx — sheet: TCL_Child_MCI
     *
     * Header structure : rows 1–12 (title banner + legend block rows 1–8 + 4-tier column headers rows 9–12)
     * Data structure   : 1 row per record
     * Total columns    : S (19)
     */
    public function childManagementSickDownload(Request $request)
    {
        $year     = $request->input('year', date('Y'));
        $barangay = $request->input('barangay', 'All');

        $records = DB::table('child_sick_records')
            ->whereYear('created_at', $year)
            ->orderBy('created_at')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('TCL_Child_MCI');
        $sheet->setShowGridlines(true);

        $FILL_H    = 'CFE2F3';
        $FILL_DIAG = 'FFF2CC'; // yellow for legend block

        $hStyle = [
            'font'      => ['bold' => true, 'size' => 8, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
            'fill'      => ['fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $FILL_H]],
        ];

        $mc  = fn(string $range, $val) => $sheet->mergeCells($range)
            && $sheet->setCellValue(explode(':', $range)[0], $val);
        $fmt = fn($d) => (!empty($d) && strtotime($d)) ? date('m/d/Y', strtotime($d)) : '';

        /*
        |--------------------------------------------------------------------------
        | 1. TITLE & LEGEND ROWS (1–8)
        |--------------------------------------------------------------------------
        */
        $mc('A1:S1', 'TARGET CLIENT LIST FOR MANAGEMENT OF SICK');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(25);

        $mc('A2:S2', "*Sick Infants/Children - refers to those infants/children diagnose with measles and/or diarrhea\n** Recommended Vitamin A Supplementation Given to Sick Infants/Children");
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['size' => 8, 'name' => 'Arial', 'italic' => true],
            'alignment' => ['wrapText' => true],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $FILL_DIAG]],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(22);

        // Legend matrix rows 3–7
        $mc('A3:G3', 'Diagnosis');
        $mc('H3:J3', 'Preparation/Capsule');
        $mc('K3:S3', 'Vitamin A Dosage and Schedule of Administration');
        $sheet->getStyle('A3:S3')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 8, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill'      => ['fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $FILL_DIAG]],
        ]);

        $mc('A4:G5', 'M - Measles cases');
        $sheet->setCellValue('H4', '100,000 IU for infants 6-11 months old');
        $sheet->setCellValue('H5', '200,000 IU for children 12-59 months old');
        $mc('K4:S5', 'Give one capsule upon diagnosis regardless of when the last dose of vitamin A capsule (VAC) was given. Give another capsule after 24 hours');

        $mc('A6:G7', 'P - Persistent diarrhea');
        $sheet->setCellValue('H6', '100,000 IU for infants 6-11 months old');
        $sheet->setCellValue('H7', '200,000 IU for children 12-59 months old');
        $mc('K6:S7', 'Give one capsule upon diagnosis, except when the child was given VAC less than 4 weeks before diagnosis.');

        $sheet->getStyle('A4:S7')->applyFromArray([
            'font'      => ['size' => 8, 'name' => 'Arial'],
            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $FILL_DIAG]],
        ]);

        foreach (range(3, 7) as $r) { $sheet->getRowDimension($r)->setRowHeight(16); }

        // Blank spacer row 8
        $sheet->getRowDimension(8)->setRowHeight(6);

        /*
        |--------------------------------------------------------------------------
        | 2. COLUMN HEADERS (rows 9–12)
        |
        | A  No.                    merged 9-12
        | B  Date of Registration   merged 9-12
        | C  Family Serial Number   merged 9-12
        | D  Name of Child          merged 9-12
        | E  Date of Birth          merged 9-12
        | F  Age (in months)        merged 9-12
        | G  Sex                    merged 9-12
        | H  Mother Name            merged 9-12
        | I  Complete Address       merged 9-12
        | J  Vitamin A – 6-11 mo    (merged 9-12, under "Date Given" sub-span)
        | K  Vitamin A – 12-59 mo   (merged 9-12)
        | L  Diagnosis/Findings     (merged 9-12)
        | M  Diarrhea Date Given    (merged 9-12, under "Acute Diarrhea" span)
        | N  ORS only               (row-11-12)
        | O  ORS and Zinc           (row-11-12, under "ORS and Zinc")
        | P  Pneumonia Date Given   (merged 9-12, under "Pneumonia" span)
        |    (the template has date given as a merged header for amox drops col)
        | Q  Amox drops/suspension  (row-11-12)
        | R  Amox-clavulanate       (row-11-12)
        |  … actually template uses cols up to S (19 cols total)
        | S  Others, pls specify    (merged 9-12 in template for last Pneumonia col)
        | T  Remarks                (merged 9-12)  — we use col S for remarks
        |--------------------------------------------------------------------------
        */
        // Patient core – merged 9-12
        foreach (['A9:A12','B9:B12','C9:C12','D9:D12','E9:E12',
                  'F9:F12','G9:G12','H9:H12','I9:I12'] as $r) {
            $sheet->mergeCells($r);
        }
        $sheet->setCellValue('A9',  'No.');
        $sheet->setCellValue('B9',  "Date of Registration\n(mm/dd/yy)");
        $sheet->setCellValue('C9',  'Family Serial Number');
        $sheet->setCellValue('D9',  "Name of Child\n(LastName, FullName, MI)");
        $sheet->setCellValue('E9',  "Date of Birth\n(mm/dd/yy)");
        $sheet->setCellValue('F9',  "Age\n(in months)");
        $sheet->setCellValue('G9',  "Sex\n\nM - Male\nF - Female");
        $sheet->setCellValue('H9',  "Complete Name of Mother\n(LastName, FullName, MI)");
        $sheet->setCellValue('I9',  'Complete Address');

        // Vitamin A section span (J-K)
        $mc('J9:L9', '*Sick Infants/Children given\nVitamin A Supplementation**');
        $mc('J10:K10', "Date Given\n(mm/dd/yy)");
        $mc('J11:J12', "6-11 months\n100,000 IU");
        $mc('K11:K12', "12-59 months\n200,000 IU");
        $mc('L10:L12', "Diagnosis/\nFindings\n\n1 - Measles\n2 - Persistent Diarrhea");

        // Acute Diarrhea section span (M-N)
        $mc('M9:N9', 'Acute Diarrhea Cases Seen and Given Treatment');
        $mc('M10:N10', "Date Given\n(mm/dd/yy)");
        $mc('M11:M12', 'ORS only');
        $mc('N11:N12', 'ORS and Zinc drops/syrup');

        // Pneumonia section span (O-R)
        $mc('O9:R9', 'Pneumonia Cases Seen and Given Treatment');
        $mc('O10:R10', "Date Given Treatment\n(mm/dd/yy)\n\nAmoxicillin drops/ suspension");
        $mc('O11:O12', 'Amoxicillin drops or suspension');
        $mc('P11:P12', 'Amoxicillin-clavulanate suspension');
        $mc('Q11:Q12', 'Cefuroxime suspension');
        $mc('R11:R12', 'Others, pls specify');

        // Remarks
        $mc('S9:S12', "Remarks/\nActions Taken");

        $sheet->getStyle('A9:S12')->applyFromArray($hStyle);
        foreach ([9=>30, 10=>25, 11=>22, 12=>15] as $r => $h) {
            $sheet->getRowDimension($r)->setRowHeight($h);
        }

        /*
        |--------------------------------------------------------------------------
        | 3. DATA ROWS
        |--------------------------------------------------------------------------
        */
        $currentRow = 13;
        $totalRows  = max($records->count(), 10);

        for ($idx = 1; $idx <= $totalRows; $idx++) {
            $rec = $records->get($idx - 1);

            $sheet->setCellValue("A{$currentRow}", $idx);

            if ($rec) {
                $sheet->setCellValue("B{$currentRow}", $fmt($rec->dateRegistration));
                $sheet->setCellValue("C{$currentRow}", $rec->familySerialNumber ?? '');
                $sheet->setCellValue("D{$currentRow}", strtoupper($rec->childName ?? ''));
                $sheet->setCellValue("E{$currentRow}", $fmt($rec->dateOfBirth));
                $sheet->setCellValue("F{$currentRow}", $rec->ageMonths ?? '');
                $sheet->setCellValue("G{$currentRow}", $rec->sex ?? '');
                $sheet->setCellValue("H{$currentRow}", strtoupper($rec->motherName ?? ''));
                $sheet->setCellValue("I{$currentRow}", $rec->address ?? '');

                // Vitamin A
                $sheet->setCellValue("J{$currentRow}", $rec->vitaminA100IU ? $fmt($rec->vitaminADateGiven) : '');
                $sheet->setCellValue("K{$currentRow}", $rec->vitaminA200IU ? $fmt($rec->vitaminADateGiven) : '');

                // Diagnosis
                $diag = [];
                if ($rec->diagnosisMeasles)           { $diag[] = '1'; }
                if ($rec->diagnosisPersistentDiarrhea) { $diag[] = '2'; }
                $sheet->setCellValue("L{$currentRow}", implode(', ', $diag));

                // Acute Diarrhea
                $sheet->setCellValue("M{$currentRow}", $rec->diagnosisPersistentDiarrhea && !empty($rec->diarrheaDateGiven)
                    ? $fmt($rec->diarrheaDateGiven) : '');
                $sheet->setCellValue("N{$currentRow}", $rec->orsOnly        ? '✔' : '');
                $sheet->setCellValue("O{$currentRow}", $rec->orsAndZinc     ? '✔' : '');

                // Pneumonia
                $pneumDate = !empty($rec->pneumoniaDateGiven) ? $fmt($rec->pneumoniaDateGiven) : '';
                $sheet->setCellValue("P{$currentRow}", $pneumDate);
                $sheet->setCellValue("Q{$currentRow}", $rec->amoxicillinDrops      ? '✔' : '');
                $sheet->setCellValue("R{$currentRow}", $rec->amoxicillinClavulanate ? '✔' : '');
                // Cefuroxime and Others map to the "Others" column with note
                $othersVal = '';
                if ($rec->cefuroxime)        { $othersVal .= 'Cefuroxime '; }
                if ($rec->pneumoniaOthers)   { $othersVal .= ($rec->pneumoniaOthersSpec ?? 'Others'); }
                $sheet->setCellValue("S{$currentRow}", trim($othersVal) ?: ($rec->remarks ?? ''));
            }

            $sheet->getRowDimension($currentRow)->setRowHeight(20);
            $currentRow++;
        }

        /*
        |--------------------------------------------------------------------------
        | 4. BORDERS, WIDTHS, ALIGNMENT
        |--------------------------------------------------------------------------
        */
        $lastRow = $currentRow - 1;

        $sheet->getStyle("A3:S{$lastRow}")->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle("A13:S{$lastRow}")->applyFromArray([
            'font'      => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
        ]);
        $sheet->getStyle("D13:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("H13:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("I13:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("S13:S{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        foreach ([
            'A'=>5,'B'=>12,'C'=>13,'D'=>26,'E'=>11,'F'=>7,'G'=>7,
            'H'=>23,'I'=>20,'J'=>11,'K'=>11,'L'=>13,
            'M'=>11,'N'=>12,'O'=>12,'P'=>12,'Q'=>14,'R'=>16,'S'=>20,
        ] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. STREAM FILE
        |--------------------------------------------------------------------------
        */
        $filename = "TCL_CHILD_MANAGEMENT_SICK_{$year}_{$barangay}.xlsx";
        $savePath = storage_path("app/{$filename}");
        (new Xlsx($spreadsheet))->save($savePath);

        return response()->download($savePath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Download Target Client List (TCL) for Child Nutrition
     * Layout mirrors Child_Nutrition.xlsx — sheet: TCL_Child_Nutri
     *
     * Header structure : rows 1–4 (title + 3-tier column headers)
     * Data structure   : 2 rows per record (row-1 = main values / row-2 = sub-values for MNP/LNS)
     * Total columns    : AV (48)
     */
    public function childNutritionDownload(Request $request)
    {
        $year     = $request->input('year', date('Y'));
        $barangay = $request->input('barangay', 'All');

        $records = DB::table('child_nutrition_records')
            ->when($barangay !== 'All', fn($q) => $q->where('address', 'like', "%{$barangay}%"))
            ->whereYear('created_at', $year)
            ->orderBy('created_at')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('TCL_Child_Nutri');
        $sheet->setShowGridlines(true);

        $FILL_H    = 'CFE2F3';
        $FILL_NB   = 'E2EFDA'; // Newborn section
        $FILL_SUPP = 'FFF2CC'; // Supplementation section
        $FILL_SFP  = 'FCE4D6'; // SFP/OTC section

        $hStyle = [
            'font'      => ['bold' => true, 'size' => 7, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
            'fill'      => ['fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $FILL_H]],
        ];

        $fillStyle = fn(string $rgb) => [
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                       'startColor' => ['rgb' => $rgb]],
        ];

        $mc  = fn(string $range, $val) => $sheet->mergeCells($range)
            && $sheet->setCellValue(explode(':', $range)[0], $val);
        $fmt = fn($d) => (!empty($d) && strtotime($d)) ? date('m/d/Y', strtotime($d)) : '';

        /*
        |--------------------------------------------------------------------------
        | 1. TITLE ROW
        |--------------------------------------------------------------------------
        */
        $mc('A1:AV1', 'TARGET CLIENT LIST FOR CHILD NUTRITION');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        /*
        |--------------------------------------------------------------------------
        | 2. COLUMN HEADERS (rows 2–4)
        |
        | A  No.                        merged 2-4
        | B  Date of Registration       merged 2-4
        | C  Family Serial Number       merged 2-4
        | D  Name of Child              merged 2-4
        | E  Date of Birth              merged 2-4
        | F  Age (in months)            merged 2-4
        | G  Sex                        merged 2-4
        | H  Mother Name                merged 2-4
        | I  Complete Address           merged 2-4
        |    ── Newborn (J-N) ──
        | J  Length at Birth            merged 2-4
        | K  Weight at Birth            merged 2-4
        | L  Birth Weight Status        merged 2-4
        | M  Breastfeeding Initiated    merged 2-4
        | N  Place of Delivery          merged 2-4
        |    ── Iron Supplementation (O-R) ──
        | O  Iron 1 month               merged 2-4
        | P  Iron 2 months              merged 2-4
        | Q  Iron 3 months              merged 2-4
        | R  Iron Completed             merged 2-4
        |    ── Vitamin A (S-Z) ──
        | S  Vit A 6-11mo               merged 2-4
        | T  Vit A 12-59 Y1D1           merged 2-4
        | U  Vit A 12-59 Y1D2           merged 2-4
        | V  Vit A 12-59 Y2D1           merged 2-4
        | W  Vit A 12-59 Y2D2           merged 2-4
        | X  Vit A 12-59 Y3D1           merged 2-4
        | Y  Vit A 12-59 Y3D2           merged 2-4
        | Z  Vit A 12-59 Y4D1           merged 2-4
        | AA Vit A 12-59 Y4D2           merged 2-4
        |    ── MNP (AB-AE) ──
        | AB MNP 6-11 mo                merged 2-4
        | AC MNP 6-11 Remarks           merged 2-4
        | AD MNP 12-23 mo               merged 2-4
        | AE MNP 12-23 Remarks          merged 2-4
        |    ── LNS-SQ (AF-AI) ──
        | AF LNS 6-11 mo                merged 2-4
        | AG LNS 6-11 Remarks           merged 2-4
        | AH LNS 12-23 mo               merged 2-4
        | AI LNS 12-23 Remarks          merged 2-4
        |    ── MAM (AJ-AO) ──
        | AJ MAM Identified             merged 2-4
        | AK MAM Enrolled               merged 2-4
        | AL MAM Cured                  merged 2-4
        | AM MAM Non-cured              merged 2-4
        | AN MAM Defaulted              merged 2-4
        | AO MAM Died                   merged 2-4
        |    ── SAM (AP-AU) ──
        | AP SAM Identified             merged 2-4
        | AQ SAM Admitted               merged 2-4
        | AR SAM Cured                  merged 2-4
        | AS SAM Non-cured              merged 2-4
        | AT SAM Defaulted              merged 2-4
        | AU SAM Died                   merged 2-4
        | AV Remarks                    merged 2-4
        |--------------------------------------------------------------------------
        */
        // Patient core (A-I) merged 2-4
        foreach (['A2:A4','B2:B4','C2:C4','D2:D4','E2:E4',
                  'F2:F4','G2:G4','H2:H4','I2:I4'] as $r) {
            $sheet->mergeCells($r);
        }
        $sheet->setCellValue('A2', 'No.');
        $sheet->setCellValue('B2', "Date of Registration\n(mm/dd/yy)");
        $sheet->setCellValue('C2', 'Family Serial Number');
        $sheet->setCellValue('D2', "Name of Child\n(LastName, FullName, MI)");
        $sheet->setCellValue('E2', "Date of Birth\n(mm/dd/yy)");
        $sheet->setCellValue('F2', "Age\n(in months)");
        $sheet->setCellValue('G2', "Sex\n\nM - Male\nF- Female");
        $sheet->setCellValue('H2', "Complete Name of Mother\n(LastName, FullName, MI)");
        $sheet->setCellValue('I2', 'Complete Address');

        // Newborn section (J-N)
        $mc('J2:N2', 'Newborn (0-28 days old)');
        foreach (['J3:J4','K3:K4','L3:L4','M3:M4','N3:N4'] as $r) { $sheet->mergeCells($r); }
        $sheet->setCellValue('J3', "Length\nat Birth\n(cm)");
        $sheet->setCellValue('K3', "Weight\nat birth\n(kg)");
        $sheet->setCellValue('L3', "Status of Birth Weight\n\nL: low: <2,500gms\nN: normal: ≥2,500gms\nU: unknown");
        $sheet->setCellValue('M3', "Initiated breastfeeding\nwithin 1 hour after birth\n(mm/dd/yy)");
        $sheet->setCellValue('N3', "Place of Delivery\nfor Initiated\nBreastfeeding");
        $sheet->getStyle('J2:N4')->applyFromArray($hStyle);
        $sheet->getStyle('J2:N4')->applyFromArray($fillStyle($FILL_NB));

        // Iron supplementation (O-R)
        $mc('O2:R2', "Low birth weight given Iron\n(mm/dd/yy)");
        foreach (['O3:O4','P3:P4','Q3:Q4','R3:R4'] as $r) { $sheet->mergeCells($r); }
        $sheet->setCellValue('O3', "1 month");
        $sheet->setCellValue('P3', "2 months");
        $sheet->setCellValue('Q3', "3 months");
        $sheet->setCellValue('R3', "Completed Iron\nsupplementation?\n\n1 - Yes\n0 - No\n\nif Yes, date completed\n(mm/dd/yy)");

        // Vitamin A (S-AA)
        $mc('S2:AA2', "Vitamin A\nNote: with 6 months interval\n(mm/dd/yy)");
        foreach (['S3:S4','T3:T4','U3:U4','V3:V4','W3:W4','X3:X4','Y3:Y4','Z3:Z4','AA3:AA4'] as $r) {
            $sheet->mergeCells($r);
        }
        $sheet->setCellValue('S3',  "6-11 months\n100,000 IU");
        $sheet->setCellValue('T3',  "12-59 months\n200,000 IU\n1st dose");
        $sheet->setCellValue('U3',  "12-59 months\n200,000 IU\n2nd dose");
        $sheet->setCellValue('V3',  "12-59 months\n200,000 IU\n1st dose");
        $sheet->setCellValue('W3',  "12-59 months\n200,000 IU\n2nd dose");
        $sheet->setCellValue('X3',  "12-59 months\n200,000 IU\n1st dose");
        $sheet->setCellValue('Y3',  "12-59 months\n200,000 IU\n2nd dose");
        $sheet->setCellValue('Z3',  "12-59 months\n200,000 IU\n1st dose");
        $sheet->setCellValue('AA3', "12-59 months\n200,000 IU\n2nd dose");

        // Vitamin A Year labels in row 4
        $sheet->setCellValue('S4',  '');
        $sheet->setCellValue('T4',  'Year:');
        $sheet->setCellValue('U4',  '');
        $sheet->setCellValue('V4',  'Year:');
        $sheet->setCellValue('W4',  '');
        $sheet->setCellValue('X4',  'Year:');
        $sheet->setCellValue('Y4',  '');
        $sheet->setCellValue('Z4',  'Year:');
        $sheet->setCellValue('AA4', '');

        $sheet->getStyle('S2:AA4')->applyFromArray($hStyle);
        $sheet->getStyle('S2:AA4')->applyFromArray($fillStyle($FILL_SUPP));

        // MNP (AB-AE)
        $mc('AB2:AE2', "MNP\n(mm/dd/yy)");
        foreach (['AB3:AB4','AC3:AC4','AD3:AD4','AE3:AE4'] as $r) { $sheet->mergeCells($r); }
        $sheet->setCellValue('AB3', "6-11 months\n90 sachets over\na period of 6 months\nDate provided:\nDate completed:");
        $sheet->setCellValue('AC3', 'Remarks');
        $sheet->setCellValue('AD3', "12-23 months\n90 sachets every 6 months\nfor a total of 180 sachets/yr\nDate provided:\nDate completed:");
        $sheet->setCellValue('AE3', 'Remarks');
        $sheet->getStyle('AB2:AE4')->applyFromArray($hStyle);
        $sheet->getStyle('AB2:AE4')->applyFromArray($fillStyle($FILL_SUPP));

        // LNS-SQ (AF-AI)
        $mc('AF2:AI2', "LNS-SQ\n(mm/dd/yy)");
        foreach (['AF3:AF4','AG3:AG4','AH3:AH4','AI3:AI4'] as $r) { $sheet->mergeCells($r); }
        $sheet->setCellValue('AF3', "6-11 months\n1 sachet per day\nfor 120 days\nDate provided:\nDate completed:");
        $sheet->setCellValue('AG3', 'Remarks');
        $sheet->setCellValue('AH3', "12-23 months\n1 sachet per day\nfor 120 days\nDate provided:\nDate completed:");
        $sheet->setCellValue('AI3', 'Remarks');
        $sheet->getStyle('AF2:AI4')->applyFromArray($hStyle);
        $sheet->getStyle('AF2:AI4')->applyFromArray($fillStyle($FILL_SUPP));

        // MAM (AJ-AO)
        $mc('AJ2:AO2', 'Supplementary Feeding Program (SFP) — MAM');
        foreach (['AJ3:AJ4','AK3:AK4','AL3:AL4','AM3:AM4','AN3:AN4','AO3:AO4'] as $r) {
            $sheet->mergeCells($r);
        }
        $sheet->setCellValue('AJ3', "Identified\n\n1 - Yes\n0 - No");
        $sheet->setCellValue('AK3', "Enrolled to SFP\n\n1 - Yes\n0 - No");
        $sheet->setCellValue('AL3', "Cured\n\n1 - Yes\n0 - No");
        $sheet->setCellValue('AM3', "Non-cured\n\n1 - Yes\n0 - No");
        $sheet->setCellValue('AN3', "Defaulted\n\n1 - Yes\n0 - No");
        $sheet->setCellValue('AO3', "Died\n\n1 - Yes\n0 - No");
        $sheet->getStyle('AJ2:AO4')->applyFromArray($hStyle);
        $sheet->getStyle('AJ2:AO4')->applyFromArray($fillStyle($FILL_SFP));

        // SAM (AP-AU)
        $mc('AP2:AU2', 'Outpatient Therapeutic Care (OTC) — SAM');
        foreach (['AP3:AP4','AQ3:AQ4','AR3:AR4','AS3:AS4','AT3:AT4','AU3:AU4'] as $r) {
            $sheet->mergeCells($r);
        }
        $sheet->setCellValue('AP3', "Identified\n\n1 - Yes\n0 - No");
        $sheet->setCellValue('AQ3', "Without complication\nadmitted to OTC\n\n1 - Yes\n0 - No");
        $sheet->setCellValue('AR3', "Cured\n\n1 - Yes\n0 - No");
        $sheet->setCellValue('AS3', "Non-cured\n\n1 - Yes\n0 - No");
        $sheet->setCellValue('AT3', "Defaulted\n\n1 - Yes\n0 - No");
        $sheet->setCellValue('AU3', "Died\n\n1 - Yes\n0 - No");
        $sheet->getStyle('AP2:AU4')->applyFromArray($hStyle);
        $sheet->getStyle('AP2:AU4')->applyFromArray($fillStyle($FILL_SFP));

        // Remarks
        $mc('AV2:AV4', "Remarks/\nActions Taken");

        // Apply base header style to all patient-core and remarks columns
        $sheet->getStyle('A2:I4')->applyFromArray($hStyle);
        $sheet->getStyle('O2:R4')->applyFromArray($hStyle);
        $sheet->getStyle('AV2:AV4')->applyFromArray($hStyle);

        foreach ([2=>30, 3=>55, 4=>18] as $r => $h) {
            $sheet->getRowDimension($r)->setRowHeight($h);
        }

        /*
        |--------------------------------------------------------------------------
        | 3. DATA ROWS (2 rows per record)
        |--------------------------------------------------------------------------
        */
        $bool1 = fn($v) => ($v ? '1' : '0');

        // Static columns merged across both rows
        $staticMergeCols = [
            'A','B','C','D','E','F','G','H','I',
            'J','K','L','M','N',
            'O','P','Q','R',
            'S','T','U','V','W','X','Y','Z','AA',
            'AJ','AK','AL','AM','AN','AO',
            'AP','AQ','AR','AS','AT','AU',
            'AV',
        ];

        $currentRow = 5;
        $totalRows  = max($records->count(), 10);

        for ($idx = 1; $idx <= $totalRows; $idx++) {
            $rec = $records->get($idx - 1);
            $r1  = $currentRow;
            $r2  = $currentRow + 1;

            foreach ($staticMergeCols as $col) {
                $sheet->mergeCells("{$col}{$r1}:{$col}{$r2}");
            }

            $sheet->setCellValue("A{$r1}", $idx);

            if ($rec) {
                // Patient core
                $sheet->setCellValue("B{$r1}", $fmt($rec->dateRegistration));
                $sheet->setCellValue("C{$r1}", $rec->familySerialNumber ?? '');
                $sheet->setCellValue("D{$r1}", strtoupper($rec->childName ?? ''));
                $sheet->setCellValue("E{$r1}", $fmt($rec->dateOfBirth));
                $sheet->setCellValue("F{$r1}", $rec->ageMonths ?? '');
                $sheet->setCellValue("G{$r1}", $rec->sex ?? '');
                $sheet->setCellValue("H{$r1}", strtoupper($rec->motherName ?? ''));
                $sheet->setCellValue("I{$r1}", $rec->address ?? '');

                // Newborn
                $sheet->setCellValue("J{$r1}", $rec->lengthAtBirth ?? '');
                $sheet->setCellValue("K{$r1}", $rec->weightAtBirth ?? '');
                $sheet->setCellValue("L{$r1}", $rec->birthWeightStatus ?? '');
                $sheet->setCellValue("M{$r1}", $fmt($rec->breastfeedingDate));
                $sheet->setCellValue("N{$r1}", $rec->placeOfDelivery ?? '');

                // Iron
                $sheet->setCellValue("O{$r1}", $fmt($rec->iron1Month));
                $sheet->setCellValue("P{$r1}", $fmt($rec->iron2Months));
                $sheet->setCellValue("Q{$r1}", $fmt($rec->iron3Months));
                $ironComp = $rec->ironCompleted
                    ? '1' . (!empty($rec->ironCompletedDate) ? "\nd: " . $fmt($rec->ironCompletedDate) : '')
                    : '0';
                $sheet->setCellValue("R{$r1}", $ironComp);

                // Vitamin A
                $sheet->setCellValue("S{$r1}",  $fmt($rec->vitaA6to11));
                $sheet->setCellValue("T{$r1}",  $fmt($rec->vitaA200Y1D1));
                $sheet->setCellValue("U{$r1}",  $fmt($rec->vitaA200Y1D2));
                $sheet->setCellValue("V{$r1}",  $fmt($rec->vitaA200Y2D1));
                $sheet->setCellValue("W{$r1}",  $fmt($rec->vitaA200Y2D2));
                $sheet->setCellValue("X{$r1}",  $fmt($rec->vitaA200Y3D1));
                $sheet->setCellValue("Y{$r1}",  $fmt($rec->vitaA200Y3D2));
                $sheet->setCellValue("Z{$r1}",  $fmt($rec->vitaA200Y4D1));
                $sheet->setCellValue("AA{$r1}", $fmt($rec->vitaA200Y4D2));

                // MNP (row-1 = dates, row-2 = completion dates)
                $mnp6Txt  = (!empty($rec->mnp6to11Provided) ? "Provided: {$fmt($rec->mnp6to11Provided)}" : '');
                $mnp6Txt .= (!empty($rec->mnp6to11Completed) ? "\nCompleted: {$fmt($rec->mnp6to11Completed)}" : '');
                $sheet->setCellValue("AB{$r1}", $mnp6Txt);
                $sheet->setCellValue("AC{$r1}", $rec->mnp6to11Remarks ?? '');

                $mnp12Txt  = (!empty($rec->mnp12to23Provided) ? "Provided: {$fmt($rec->mnp12to23Provided)}" : '');
                $mnp12Txt .= (!empty($rec->mnp12to23Completed) ? "\nCompleted: {$fmt($rec->mnp12to23Completed)}" : '');
                $sheet->setCellValue("AD{$r1}", $mnp12Txt);
                $sheet->setCellValue("AE{$r1}", $rec->mnp12to23Remarks ?? '');

                // LNS-SQ
                $lns6Txt  = (!empty($rec->lns6to11Provided) ? "Provided: {$fmt($rec->lns6to11Provided)}" : '');
                $lns6Txt .= (!empty($rec->lns6to11Completed) ? "\nCompleted: {$fmt($rec->lns6to11Completed)}" : '');
                $sheet->setCellValue("AF{$r1}", $lns6Txt);
                $sheet->setCellValue("AG{$r1}", $rec->lns6to11Remarks ?? '');

                $lns12Txt  = (!empty($rec->lns12to23Provided) ? "Provided: {$fmt($rec->lns12to23Provided)}" : '');
                $lns12Txt .= (!empty($rec->lns12to23Completed) ? "\nCompleted: {$fmt($rec->lns12to23Completed)}" : '');
                $sheet->setCellValue("AH{$r1}", $lns12Txt);
                $sheet->setCellValue("AI{$r1}", $rec->lns12to23Remarks ?? '');

                // MAM
                $sheet->setCellValue("AJ{$r1}", $bool1($rec->mamIdentified));
                $sheet->setCellValue("AK{$r1}", $bool1($rec->mamEnrolled));
                $sheet->setCellValue("AL{$r1}", $bool1($rec->mamCured));
                $sheet->setCellValue("AM{$r1}", $bool1($rec->mamNonCured));
                $sheet->setCellValue("AN{$r1}", $bool1($rec->mamDefaulted));
                $sheet->setCellValue("AO{$r1}", $bool1($rec->mamDied));

                // SAM
                $sheet->setCellValue("AP{$r1}", $bool1($rec->samIdentified));
                $sheet->setCellValue("AQ{$r1}", $bool1($rec->samAdmitted));
                $sheet->setCellValue("AR{$r1}", $bool1($rec->samCured));
                $sheet->setCellValue("AS{$r1}", $bool1($rec->samNonCured));
                $sheet->setCellValue("AT{$r1}", $bool1($rec->samDefaulted));
                $sheet->setCellValue("AU{$r1}", $bool1($rec->samDied));

                $sheet->setCellValue("AV{$r1}", $rec->remarks ?? '');

                // MNP/LNS columns are NOT static merges (they have two-row content already combined above)
                // Merge the sub-value columns across both rows
                foreach (['AB','AC','AD','AE','AF','AG','AH','AI'] as $col) {
                    $sheet->mergeCells("{$col}{$r1}:{$col}{$r2}");
                }
            } else {
                // Empty placeholder row
                foreach (['AB','AC','AD','AE','AF','AG','AH','AI'] as $col) {
                    $sheet->mergeCells("{$col}{$r1}:{$col}{$r2}");
                }
            }

            $sheet->getRowDimension($r1)->setRowHeight(15);
            $sheet->getRowDimension($r2)->setRowHeight(15);
            $currentRow += 2;
        }

        /*
        |--------------------------------------------------------------------------
        | 4. BORDERS, WIDTHS, ALIGNMENT
        |--------------------------------------------------------------------------
        */
        $lastRow = $currentRow - 1;

        $sheet->getStyle("A2:AV{$lastRow}")->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle("A5:AV{$lastRow}")->applyFromArray([
            'font'      => ['size' => 8, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
        ]);
        $sheet->getStyle("D5:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("H5:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("I5:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("N5:N{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("AC5:AC{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("AE5:AE{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("AG5:AG{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("AI5:AI{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("AV5:AV{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $widths = [
            'A'=>5, 'B'=>12,'C'=>13,'D'=>26,'E'=>11,'F'=>7,'G'=>7,'H'=>22,'I'=>20,
            'J'=>8,'K'=>8,'L'=>12,'M'=>13,'N'=>16,
            'O'=>10,'P'=>10,'Q'=>10,'R'=>14,
            'S'=>10,'T'=>10,'U'=>10,'V'=>10,'W'=>10,'X'=>10,'Y'=>10,'Z'=>10,'AA'=>10,
            'AB'=>18,'AC'=>12,'AD'=>18,'AE'=>12,
            'AF'=>18,'AG'=>12,'AH'=>18,'AI'=>12,
            'AJ'=>10,'AK'=>10,'AL'=>10,'AM'=>12,'AN'=>10,'AO'=>8,
            'AP'=>10,'AQ'=>13,'AR'=>10,'AS'=>12,'AT'=>10,'AU'=>8,
            'AV'=>22,
        ];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. STREAM FILE
        |--------------------------------------------------------------------------
        */
        $filename = "TCL_CHILD_NUTRITION_{$year}_{$barangay}.xlsx";
        $savePath = storage_path("app/{$filename}");
        (new Xlsx($spreadsheet))->save($savePath);

        return response()->download($savePath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Download Filariasis Registry
     * Layout mirrors Filariasis_Registry.xlsx — sheet: Filariasis_Registry
     *
     * Header structure : rows 1 (title), 2 (main column headers), 3 (Blood Test sub-span),
     *                    4 (Blood Test sub-columns + chronic manifestations + drugs)
     * Data structure   : 2 rows per record (row-1 = main values / row-2 = drug dates)
     * Total columns    : W (23)
     */
    public function filariasisRegistryDownload(Request $request)
    {
        $year     = $request->input('year', date('Y'));
        $barangay = $request->input('barangay', 'All');

        $records = DB::table('filariasis_registry_table')
            ->when($barangay !== 'All', fn($q) => $q->where('address', 'like', "%{$barangay}%"))
            ->whereYear('created_at', $year)
            ->orderBy('created_at')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Filariasis_Registry');
        $sheet->setShowGridlines(true);

        $FILL_H = 'CFE2F3';

        $hStyle = [
            'font'      => ['bold' => true, 'size' => 8, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
            'fill'      => ['fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $FILL_H]],
        ];

        $mc  = fn(string $range, $val) => $sheet->mergeCells($range)
            && $sheet->setCellValue(explode(':', $range)[0], $val);
        $fmt = fn($d) => (!empty($d) && strtotime($d)) ? date('m/d/Y', strtotime($d)) : '';

        /*
        |--------------------------------------------------------------------------
        | 1. TITLE ROW
        |--------------------------------------------------------------------------
        */
        $mc('A1:W1', 'Filariasis Registry');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        /*
        |--------------------------------------------------------------------------
        | 2. COLUMN HEADERS (rows 2–4)
        |
        | A  No.                               merged 2-4
        | B  Date of Registration              merged 2-4
        | C  Family Serial Number              merged 2-4
        | D  Patient Full Name                 merged 2-4
        | E  Complete Address                  merged 2-4
        | F  Date of Birth                     merged 2-4
        | G  Age (in years)                    merged 2-4
        | H  Age Group                         merged 2-4
        | I  Sex                               merged 2-4
        |    ── Blood Test Result (J2) ──
        | J  Blood Test Result (span)          row 2
        |    ── Type of Test (J3:L3) ──
        | J  NBE                               row 4
        | K  RDT                               row 4
        | L  Date of NBE/RDT                   row 4
        | M  Result                            row 4
        |    ── Chronic Manifestations (N2) ──
        | N  Lymphedema (examined first time)  row 4
        | O  Lymphedema                        row 4
        | P  Elephantiasis (examined)          row 4
        | Q  Elephantiasis                     row 4
        | R  Hydrocele (examined)              row 4
        | S  Hydrocele (Male only)             row 4
        |    ── Drugs Given (T2) ──
        | T  Albendazole Date Given            row 4
        | U  DEC Date Given                    row 4
        | V  Ivermectin Date Given             row 4
        | W  Remarks                           merged 2-4
        |--------------------------------------------------------------------------
        */
        foreach (['A2:A4','B2:B4','C2:C4','D2:D4','E2:E4',
                  'F2:F4','G2:G4','H2:H4','I2:I4','W2:W4'] as $r) {
            $sheet->mergeCells($r);
        }
        $sheet->setCellValue('A2', 'No.');
        $sheet->setCellValue('B2', "Date of Registration\n(mm/dd/yy)");
        $sheet->setCellValue('C2', 'Family Serial Number');
        $sheet->setCellValue('D2', "Patient Full Name\n(LastName, FullName, MI)");
        $sheet->setCellValue('E2', 'Complete Address');
        $sheet->setCellValue('F2', "Date of Birth\n(mm/dd/yy)");
        $sheet->setCellValue('G2', "Age\n(in years)");
        $sheet->setCellValue('H2', "Age Group\n\nA - 2-4 years old\nB - 5-14 years old\nC - 15 years old and above");
        $sheet->setCellValue('I2', "Sex\n\nM - Male\nF- Female");
        $sheet->setCellValue('W2', "Remarks");

        // Blood Test Result span (row 2, cols J-M)
        $mc('J2:M2', 'Blood Test Result');
        // Type of Test sub-span (row 3, cols J-L)
        $mc('J3:L3', "Type of Test\n(Place a check)");
        $mc('M3:M4', "Result\n\n1 - positive\n2 - negative");
        // Blood Test leaf headers (row 4)
        $mc('J4:J4', "\nNocturnal Blood Examination (NBE)\n");
        $mc('K4:K4', "\nRapid Diagnostic Test (RDT)\n");
        $sheet->setCellValue('L4', 'Date of NBE/RDT');

        // Chronic Manifestations span (row 2, cols N-S)
        $mc('N2:S2', "With chronic manifestations\n(Place a check)");
        $sheet->setCellValue('N3', "Examined for the first time of Lymphedema\n\n1 - Yes\n2 - No");
        $mc('N3:N4', "Examined for the first time of Lymphedema\n\n1 - Yes\n2 - No");
        $mc('O3:O4', 'Lymphedema');
        $mc('P3:P4', "Examined for the first time of Elephantiasis\n\n1 - Yes\n2 - No");
        $mc('Q3:Q4', 'Elephantiasis');
        $mc('R3:R4', "Examined for the first time of Hydrocele\n\n1 - Yes\n2 - No");
        $mc('S3:S4', 'Hydrocele (Male only)');

        // Drugs Given span (row 2, cols T-V)
        $mc('T2:V2', "Drugs Given\n(Place a check)\n\nFor 2-4 years old: DEC and Albendazole\nFor 5-14 and 15 years old and above: Ivermectin, DEC and Albendazole");
        $mc('T3:T4', "Albendazole\n\nDate Given\n(mm/dd/yy)");
        $mc('U3:U4', "Diethylcarbamazine Citrate (DEC)\n\nDate Given\n(mm/dd/yy)");
        $mc('V3:V4', "Ivermectin\n\nDate Given\n(mm/dd/yy)");

        $sheet->getStyle('A2:W4')->applyFromArray($hStyle);
        foreach ([2=>30, 3=>40, 4=>35] as $r => $h) {
            $sheet->getRowDimension($r)->setRowHeight($h);
        }

        /*
        |--------------------------------------------------------------------------
        | 3. DATA ROWS (2 rows per record: row-1 = main values, row-2 = drug dates)
        |--------------------------------------------------------------------------
        */
        $staticMergeCols = ['A','B','C','D','E','F','G','H','I',
                             'J','K','L','M','N','O','P','Q','R','S','W'];

        $currentRow = 5;
        $totalRows  = max($records->count(), 10);

        for ($idx = 1; $idx <= $totalRows; $idx++) {
            $rec = $records->get($idx - 1);
            $r1  = $currentRow;
            $r2  = $currentRow + 1;

            foreach ($staticMergeCols as $col) {
                $sheet->mergeCells("{$col}{$r1}:{$col}{$r2}");
            }

            $sheet->setCellValue("A{$r1}", $idx);

            if ($rec) {
                $sheet->setCellValue("B{$r1}", $fmt($rec->date_of_registration));
                $sheet->setCellValue("C{$r1}", $rec->family_serial_number ?? '');
                $sheet->setCellValue("D{$r1}", strtoupper($rec->name ?? ''));
                $sheet->setCellValue("E{$r1}", $rec->address ?? '');
                $sheet->setCellValue("F{$r1}", $fmt($rec->date_of_birth));
                $sheet->setCellValue("G{$r1}", $rec->age ?? '');
                $sheet->setCellValue("H{$r1}", $rec->age_group ?? '');
                $sheet->setCellValue("I{$r1}", $rec->sex ?? '');

                // Blood Test
                $sheet->setCellValue("J{$r1}", $rec->nbe_performed ? '✔' : '');
                $sheet->setCellValue("K{$r1}", $rec->rdt_performed ? '✔' : '');
                $sheet->setCellValue("L{$r1}", $fmt($rec->date_nbe_rdt));
                $sheet->setCellValue("M{$r1}", $rec->blood_test_result ?? '');

                // Chronic Manifestations
                $sheet->setCellValue("N{$r1}", $rec->lymphedema_examined_first_time ?? '');
                $sheet->setCellValue("O{$r1}", $rec->has_lymphedema ? '✔' : '');
                $sheet->setCellValue("P{$r1}", $rec->elephantiasis_examined_first_time ?? '');
                $sheet->setCellValue("Q{$r1}", $rec->has_elephantiasis ? '✔' : '');
                $sheet->setCellValue("R{$r1}", $rec->hydrocele_examined_first_time ?? '');
                $sheet->setCellValue("S{$r1}", $rec->has_hydrocele ? '✔' : '');

                // Drugs Given — row-1 = check mark presence, row-2 = dates
                $sheet->setCellValue("T{$r1}", !empty($rec->albendazole_date_given) ? '✔' : '');
                $sheet->setCellValue("U{$r1}", !empty($rec->dec_date_given)         ? '✔' : '');
                $sheet->setCellValue("V{$r1}", !empty($rec->ivermectin_date_given)  ? '✔' : '');

                $sheet->setCellValue("T{$r2}", !empty($rec->albendazole_date_given) ? 'd: '.$fmt($rec->albendazole_date_given) : 'd: ');
                $sheet->setCellValue("U{$r2}", !empty($rec->dec_date_given)         ? 'd: '.$fmt($rec->dec_date_given)         : 'd: ');
                $sheet->setCellValue("V{$r2}", !empty($rec->ivermectin_date_given)  ? 'd: '.$fmt($rec->ivermectin_date_given)  : 'd: ');

                $sheet->setCellValue("W{$r1}", $rec->remarks ?? '');
            } else {
                $sheet->setCellValue("T{$r2}", 'd: ');
                $sheet->setCellValue("U{$r2}", 'd: ');
                $sheet->setCellValue("V{$r2}", 'd: ');
            }

            $sheet->getRowDimension($r1)->setRowHeight(15);
            $sheet->getRowDimension($r2)->setRowHeight(13);
            $currentRow += 2;
        }

        /*
        |--------------------------------------------------------------------------
        | 4. FOOTNOTE
        |--------------------------------------------------------------------------
        */
        $fn = $currentRow;
        $sheet->mergeCells("A{$fn}:W{$fn}");
        $sheet->setCellValue("A{$fn}", 'Lymphedema – fluid collection in the extremities and/or breast resulting to swelling due to improper functioning of the lymph system  |  Elephantiasis – hardening and thickening of the skin  |  Hydrocele - scrotal swelling caused by death and blockage of adult filarial worms');
        $sheet->getStyle("A{$fn}")->applyFromArray(['font' => ['size' => 7, 'name' => 'Arial', 'italic' => true]]);
        $sheet->getRowDimension($fn)->setRowHeight(13);

        /*
        |--------------------------------------------------------------------------
        | 5. BORDERS, WIDTHS, ALIGNMENT
        |--------------------------------------------------------------------------
        */
        $lastRow = $currentRow - 1;
        $sheet->getStyle("A2:W{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A5:W{$lastRow}")->applyFromArray([
            'font'      => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
        ]);
        $sheet->getStyle("D5:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("E5:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("W5:W{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        foreach ([
            'A'=>5,'B'=>13,'C'=>13,'D'=>26,'E'=>20,'F'=>11,'G'=>7,'H'=>18,'I'=>8,
            'J'=>12,'K'=>12,'L'=>12,'M'=>11,'N'=>14,'O'=>11,'P'=>14,'Q'=>11,'R'=>14,'S'=>11,
            'T'=>13,'U'=>15,'V'=>13,'W'=>22,
        ] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        /*
        |--------------------------------------------------------------------------
        | 6. STREAM FILE
        |--------------------------------------------------------------------------
        */
        $filename = "FILARIASIS_REGISTRY_{$year}_{$barangay}.xlsx";
        $savePath = storage_path("app/{$filename}");
        (new Xlsx($spreadsheet))->save($savePath);

        return response()->download($savePath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Download Leprosy Registry
     * Layout mirrors Leprosy_Registry.xlsx — sheet: Leprosy_Registry
     *
     * Header structure : rows 1 (title), 2 (all column headers, single tier)
     * Data structure   : 2 rows per record (row-1 = main values / row-2 = date sub-values)
     * Total columns    : U (21)
     */
    public function leprosyRegistryDownload(Request $request)
    {
        $year     = $request->input('year', date('Y'));
        $barangay = $request->input('barangay', 'All');

        $records = DB::table('leprosy_registry')
            ->when($barangay !== 'All', fn($q) => $q->where('address', 'like', "%{$barangay}%"))
            ->whereYear('created_at', $year)
            ->orderBy('created_at')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Leprosy_Registry');
        $sheet->setShowGridlines(true);

        $FILL_H = 'CFE2F3';

        $hStyle = [
            'font'      => ['bold' => true, 'size' => 8, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
            'fill'      => ['fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $FILL_H]],
        ];

        $mc  = fn(string $range, $val) => $sheet->mergeCells($range)
            && $sheet->setCellValue(explode(':', $range)[0], $val);
        $fmt = fn($d) => (!empty($d) && strtotime($d)) ? date('m/d/Y', strtotime($d)) : '';

        /*
        |--------------------------------------------------------------------------
        | 1. TITLE ROW
        |--------------------------------------------------------------------------
        */
        $mc('A1:U1', 'Leprosy Registry');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        /*
        |--------------------------------------------------------------------------
        | 2. COLUMN HEADERS (row 2, single tier — all merged rows 2-3 for 2-row layout)
        |
        | A  No.
        | B  Date of Registration
        | C  Full Name
        | D  Complete Address
        | E  Date of Birth
        | F  Age (in years)
        | G  Age Group
        | H  Sex
        | I  Confirmed Case + Date of Diagnosis
        | J  Case History
        | K  Previous Facility
        | L  Clinical Classification
        | M  Treatment Start Date
        | N  Months Treated Prior
        | O  Reclassified + Date of Reclassification
        | P  Updated Classification
        | Q  Treatment Outcome / Status
        | R  Completed Fixed MDT + Date Completed
        | S  Beyond Fixed MDT + Date Completed
        | T  With Grade 2 Disability
        | U  Remarks
        |--------------------------------------------------------------------------
        */
        foreach (['A2:A3','B2:B3','C2:C3','D2:D3','E2:E3','F2:F3','G2:G3',
                  'H2:H3','I2:I3','J2:J3','K2:K3','L2:L3','M2:M3','N2:N3',
                  'O2:O3','P2:P3','Q2:Q3','R2:R3','S2:S3','T2:T3','U2:U3'] as $r) {
            $sheet->mergeCells($r);
        }

        $sheet->setCellValue('A2', 'No.');
        $sheet->setCellValue('B2', "Date of Registration\n(mm/dd/yy)");
        $sheet->setCellValue('C2', "Full Name\n(LastName, FullName, MI)");
        $sheet->setCellValue('D2', 'Complete Address');
        $sheet->setCellValue('E2', "Date of Birth\n(mm/dd/yy)");
        $sheet->setCellValue('F2', "Age\n(in years)");
        $sheet->setCellValue('G2', "Age Group\n\nA - 0-14 years old\nB - 15-18 years old\nC - 19 years old and above");
        $sheet->setCellValue('H2', "Sex\n\nM - Male\nF - Female");
        $sheet->setCellValue('I2', "Confirmed Case\n\n1 - Yes\n0 - No\n\n\nDate of Diagnosis\n(mm/dd/yy)");
        $sheet->setCellValue('J2', "Case History\n\n0 - New (No history)\n1 - Relapse\n2 - Defaulter\n3 - Transfer-in");
        $sheet->setCellValue('K2', 'Previous Facility (if Transfer in)');
        $sheet->setCellValue('L2', "Clinical Classification\n(at diagnosis)\n\n1 - Paucibacillary (PB)\n2 - Multibacillary (MB)");
        $sheet->setCellValue('M2', "Treatment Start Date (Original)\n(mm/dd/yy)");
        $sheet->setCellValue('N2', 'Months Treated Prior (if Transfer-in)');
        $sheet->setCellValue('O2', "Reclassified\n\n1 - Yes\n0 - No\n\nDate of Reclassification\n(mm/dd/yy)");
        $sheet->setCellValue('P2', "Updated Classification (if reclassified)\n\n1 - PB\n2 - MB");
        $sheet->setCellValue('Q2', "Treatment Outcome / Status\n\n1 - Ongoing Treatment\n2 - Completed Treatment\n3 - Defaulted\n4 - Transferred Out\n5 - Died");
        $sheet->setCellValue('R2', "Completed Fixed MDT\n\n1 - Yes\n0 - No\n\nDate Completed Treatment\nd: (mm/dd/yy)");
        $sheet->setCellValue('S2', "Beyond the Fixed MDT Treatment?\n\n1 - Yes\n0 - No\n\nDate Completed Treatment\nd: (mm/dd/yy)");
        $sheet->setCellValue('T2', "With Grade 2 Disability\n\n1 - Yes\n0 - No");
        $sheet->setCellValue('U2', 'Remarks');

        $sheet->getStyle('A2:U3')->applyFromArray($hStyle);
        $sheet->getRowDimension(2)->setRowHeight(80);
        $sheet->getRowDimension(3)->setRowHeight(0); // hidden sub-row (merged)

        /*
        |--------------------------------------------------------------------------
        | 3. DATA ROWS (2 rows per record: row-1 = values, row-2 = date sub-values)
        |--------------------------------------------------------------------------
        */
        // Static columns merged across both rows
        $staticMerge = ['A','B','C','D','E','F','G','H','J','K','L','M','N','P','Q','T','U'];

        $currentRow = 4;
        $totalRows  = max($records->count(), 10);

        for ($idx = 1; $idx <= $totalRows; $idx++) {
            $rec = $records->get($idx - 1);
            $r1  = $currentRow;
            $r2  = $currentRow + 1;

            foreach ($staticMerge as $col) {
                $sheet->mergeCells("{$col}{$r1}:{$col}{$r2}");
            }

            $sheet->setCellValue("A{$r1}", $idx);

            if ($rec) {
                $sheet->setCellValue("B{$r1}", $fmt($rec->date_of_registration));
                $sheet->setCellValue("C{$r1}", strtoupper($rec->name ?? ''));
                $sheet->setCellValue("D{$r1}", $rec->address ?? '');
                $sheet->setCellValue("E{$r1}", $fmt($rec->date_of_birth));
                $sheet->setCellValue("F{$r1}", $rec->age ?? '');
                $sheet->setCellValue("G{$r1}", $rec->age_group ?? '');
                $sheet->setCellValue("H{$r1}", $rec->sex ?? '');

                // I: Confirmed Case (row-1) / Date of Diagnosis (row-2)
                $sheet->setCellValue("I{$r1}", $rec->confirmed_case ?? '');
                $sheet->setCellValue("I{$r2}", !empty($rec->date_of_diagnosis) ? 'd: '.$fmt($rec->date_of_diagnosis) : 'd:');

                $sheet->setCellValue("J{$r1}", $rec->case_history ?? '');
                $sheet->setCellValue("K{$r1}", $rec->previous_facility ?? '');
                $sheet->setCellValue("L{$r1}", $rec->clinical_classification ?? '');
                $sheet->setCellValue("M{$r1}", $fmt($rec->treatment_start_date));
                $sheet->setCellValue("N{$r1}", $rec->months_treated_prior ?? '');

                // O: Reclassified (row-1) / Date of Reclassification (row-2)
                $sheet->setCellValue("O{$r1}", $rec->reclassified ?? '');
                $sheet->setCellValue("O{$r2}", !empty($rec->date_of_reclassification) ? 'd: '.$fmt($rec->date_of_reclassification) : 'd:');

                $sheet->setCellValue("P{$r1}", $rec->updated_classification ?? '');
                $sheet->setCellValue("Q{$r1}", $rec->treatment_outcome ?? '');

                // R: Completed Fixed MDT (row-1) / Date (row-2)
                $sheet->setCellValue("R{$r1}", $rec->completed_fixed_mdt ?? '');
                $sheet->setCellValue("R{$r2}", !empty($rec->fixed_mdt_completed_date) ? 'd: '.$fmt($rec->fixed_mdt_completed_date) : 'd:');

                // S: Beyond Fixed MDT (row-1) / Date (row-2)
                $sheet->setCellValue("S{$r1}", $rec->beyond_fixed_mdt ?? '');
                $sheet->setCellValue("S{$r2}", !empty($rec->beyond_fixed_mdt_completed_date) ? 'd: '.$fmt($rec->beyond_fixed_mdt_completed_date) : 'd:');

                $sheet->setCellValue("T{$r1}", $rec->grade2_disability ?? '');
                $sheet->setCellValue("U{$r1}", $rec->remarks ?? '');
            } else {
                foreach (['I','O','R','S'] as $col) {
                    $sheet->setCellValue("{$col}{$r2}", 'd:');
                }
            }

            $sheet->getRowDimension($r1)->setRowHeight(15);
            $sheet->getRowDimension($r2)->setRowHeight(13);
            $currentRow += 2;
        }

        /*
        |--------------------------------------------------------------------------
        | 4. BORDERS, WIDTHS, ALIGNMENT
        |--------------------------------------------------------------------------
        */
        $lastRow = $currentRow - 1;
        $sheet->getStyle("A2:U{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A4:U{$lastRow}")->applyFromArray([
            'font'      => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
        ]);
        $sheet->getStyle("C4:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("D4:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("K4:K{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("U4:U{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        foreach ([
            'A'=>5,'B'=>13,'C'=>26,'D'=>22,'E'=>11,'F'=>7,'G'=>16,'H'=>8,
            'I'=>16,'J'=>16,'K'=>18,'L'=>16,'M'=>13,'N'=>13,
            'O'=>16,'P'=>14,'Q'=>18,'R'=>16,'S'=>16,'T'=>13,'U'=>22,
        ] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. STREAM FILE
        |--------------------------------------------------------------------------
        */
        $filename = "LEPROSY_REGISTRY_{$year}_{$barangay}.xlsx";
        $savePath = storage_path("app/{$filename}");
        (new Xlsx($spreadsheet))->save($savePath);

        return response()->download($savePath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Download Schistosomiasis Registry
     * Layout mirrors Schistosomiasis_Registry.xlsx — sheet: Schistosomiasis_Registry
     *
     * Header structure : rows 1 (title), 2 (main + section spans), 3 (sub-column headers)
     * Data structure   : 2 rows per record (row-1 = values / row-2 = date sub-values)
     * Total columns    : Z (26)
     */
    public function schistosomiasisRegistryDownload(Request $request)
    {
        $year     = $request->input('year', date('Y'));
        $barangay = $request->input('barangay', 'All');

        $records = DB::table('schistosomiasis_registry')
            ->when($barangay !== 'All', fn($q) => $q->where('address', 'like', "%{$barangay}%"))
            ->whereYear('created_at', $year)
            ->orderBy('created_at')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Schistosomiasis_Registry');
        $sheet->setShowGridlines(true);

        $FILL_H    = 'CFE2F3';
        $FILL_SUSP = 'FFF2CC'; // yellow for suspected/clinical section
        $FILL_CONF = 'E2EFDA'; // green for confirmed section

        $hStyle = [
            'font'      => ['bold' => true, 'size' => 8, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
            'fill'      => ['fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $FILL_H]],
        ];

        $mc  = fn(string $range, $val) => $sheet->mergeCells($range)
            && $sheet->setCellValue(explode(':', $range)[0], $val);
        $fmt = fn($d) => (!empty($d) && strtotime($d)) ? date('m/d/Y', strtotime($d)) : '';

        /*
        |--------------------------------------------------------------------------
        | 1. TITLE ROW
        |--------------------------------------------------------------------------
        */
        $mc('A1:Z1', 'Schistosomiasis Registry');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        /*
        |--------------------------------------------------------------------------
        | 2. ROW 2 — MAIN SECTION SPANS
        |
        | A-K  Patient info (A:A merged 2-3, rest merged 2-3)
        | L     Screened (merged 2-3)
        | M-P   Suspected/Clinical Cases span (M2:P2)
        | Q-W   Confirmed Cases span (Q2:W2)
        | X     Date Referred (merged 2-3)
        | Y     MDA (merged 2-3)
        | Z     Remarks (merged 2-3)
        |--------------------------------------------------------------------------
        */
        foreach (['A2:A3','B2:B3','C2:C3','D2:D3','E2:E3','F2:F3','G2:G3',
                  'H2:H3','I2:I3','J2:J3','K2:K3','L2:L3','X2:X3','Z2:Z3'] as $r) {
            $sheet->mergeCells($r);
        }

        $sheet->setCellValue('A2', 'No.');
        $sheet->setCellValue('B2', "Date of Registration\n(mm/dd/yy)");
        $sheet->setCellValue('C2', 'Family Serial Number');
        $sheet->setCellValue('D2', "Patient Full Name\n(LastName, FullName, MI)");
        $sheet->setCellValue('E2', 'Complete Address');
        $sheet->setCellValue('F2', "1 - Resident\n2 - Non-Resident");
        $sheet->setCellValue('G2', "Date of Birth\n(mm/dd/yy)");
        $sheet->setCellValue('H2', "Age\n(in years)");
        $sheet->setCellValue('I2', "Age Group\n\nA - 1-4 years old\nB - 5-14 years old\nC - 15-19 years old\nD - 20-59 years old\nE - 60 years old and above");
        $sheet->setCellValue('J2', "Sex\n\nM - Male\nF- Female");
        $sheet->setCellValue('K2', "With History of Travel to a Schistosomiasis endemic area; OR with a history of exposure to an infested area\n\n1 - Yes\n0 - No");
        $sheet->setCellValue('L2', "\nScreened for Schistosomiasis\n1 - Yes\n0 - No\n\n\nDate Screened\nd: (mm/dd/yy)");
        $sheet->setCellValue('X2', "Date Referred to Hospital Facility for Confirmed Complicated Cases\n\n(mm/dd/yy)");
        $sheet->setCellValue('Z2', 'Remarks');

        // Section spans
        $mc('M2:P2', 'Suspected/Clinical Cases');
        $mc('Q2:W2', 'Confirmed Schistosomiasis Case');
        // Y: MDA merged 2-3
        $mc('Y2:Y3', "Schistosomiasis MDA\n\nGiven 1 dose of Praziquantel during MDA?\n\n1 - Yes\n0 - No\n\nDate (mm/dd/yy)");

        $sheet->getStyle('A2:Z2')->applyFromArray($hStyle);
        // Override section fill colors
        $sheet->getStyle('M2:P2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($FILL_SUSP);
        $sheet->getStyle('Q2:W2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($FILL_CONF);

        /*
        |--------------------------------------------------------------------------
        | 3. ROW 3 — SUB-COLUMN HEADERS
        |--------------------------------------------------------------------------
        */
        $sheet->setCellValue('M3', "With signs & symptoms?\n\n1 - Yes\n0 - No\n\nIf yes (refer at * for signs & symptoms)");
        $sheet->setCellValue('N3', "Clinical/Suspected Cases Treated\n(1st treatment)\n\n1 - Yes\n0 - No\n\nDate 1st Treatment Started\n(mm/dd/yy)");
        $sheet->setCellValue('O3', "\nRetreatment?\n\n1 - Yes\n0 - No\n\nRetreatment Date\n(mm/dd/yy)");
        $sheet->setCellValue('P3', "Clinical/Suspected Cases Cured\n\n1 - Yes\n0 - No\n\nDate clinical/confirmed cases Cured\n(mm/dd/yy)");
        $sheet->setCellValue('Q3', "Diagnostic Test\n(Kato-Katz/Kato Thick/Rectal Biopsy)\n\nDate of Diagnosis\n\n(mm/dd/yy)");
        $sheet->setCellValue('R3', "Result of Diagnostic Test\n\n1 - Positive\n0 - Negative");
        $sheet->setCellValue('S3', "Date Confirmed\n\n(mm/dd/yy)");
        $sheet->setCellValue('T3', "Confirmed Complicated/Non-Complicated\n\n1 - Complicated\n0 - Non-Complicated");
        $sheet->setCellValue('U3', "Confirmed Cases Treated (1st treatment)\n\n1 - Yes\n0 - No\n\nDate 1st Treatment Started\n(mm/dd/yy)");
        $sheet->setCellValue('V3', "\nRetreatment?\n\n1 - Yes\n0 - No\n\nRetreatment Date\n(mm/dd/yy)");
        $sheet->setCellValue('W3', "Confirmed Cases Cured\n\n1 - Yes\n0 - No\n\nDate Confirmed Cases Cured\n(mm/dd/yy)");

        $sheet->getStyle('A3:Z3')->applyFromArray($hStyle);
        $sheet->getStyle('M3:P3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($FILL_SUSP);
        $sheet->getStyle('Q3:W3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($FILL_CONF);

        foreach ([2=>30, 3=>75] as $r => $h) {
            $sheet->getRowDimension($r)->setRowHeight($h);
        }

        /*
        |--------------------------------------------------------------------------
        | 4. DATA ROWS (2 rows per record)
        |--------------------------------------------------------------------------
        */
        // Static columns merged across both rows
        $staticMerge = ['A','B','C','D','E','F','G','H','I','J','K','R','S','T','X','Z'];

        $currentRow = 4;
        $totalRows  = max($records->count(), 10);

        for ($idx = 1; $idx <= $totalRows; $idx++) {
            $rec = $records->get($idx - 1);
            $r1  = $currentRow;
            $r2  = $currentRow + 1;

            foreach ($staticMerge as $col) {
                $sheet->mergeCells("{$col}{$r1}:{$col}{$r2}");
            }

            $sheet->setCellValue("A{$r1}", $idx);

            if ($rec) {
                $sheet->setCellValue("B{$r1}", $fmt($rec->date_of_registration));
                $sheet->setCellValue("C{$r1}", $rec->family_serial_number ?? '');
                $sheet->setCellValue("D{$r1}", strtoupper($rec->name ?? ''));
                $sheet->setCellValue("E{$r1}", $rec->address ?? '');
                $sheet->setCellValue("F{$r1}", $rec->residency ?? '');
                $sheet->setCellValue("G{$r1}", $fmt($rec->date_of_birth));
                $sheet->setCellValue("H{$r1}", $rec->age ?? '');
                $sheet->setCellValue("I{$r1}", $rec->age_group ?? '');
                $sheet->setCellValue("J{$r1}", $rec->sex ?? '');
                $sheet->setCellValue("K{$r1}", $rec->history_of_exposure ?? '');

                // L: Screened (row-1) / Date Screened (row-2)
                $sheet->setCellValue("L{$r1}", $rec->screened ?? '');
                $sheet->setCellValue("L{$r2}", !empty($rec->date_screened) ? 'd: '.$fmt($rec->date_screened) : 'd: ');

                // M: Signs & Symptoms (static merged)
                $sheet->mergeCells("M{$r1}:M{$r2}");
                $signsJson = $rec->signs_symptoms ?? '[]';
                $signsArr  = is_string($signsJson) ? (json_decode($signsJson, true) ?? []) : [];
                $signsText = $rec->with_signs_symptoms ?? '';
                if (!empty($signsArr)) {
                    $signsText .= "\n" . implode(', ', $signsArr);
                }
                $sheet->setCellValue("M{$r1}", $signsText);

                // N: Clinical 1st Treatment (row-1 flag / row-2 date)
                $sheet->setCellValue("N{$r1}", $rec->clinical_first_treatment_given ?? '');
                $sheet->setCellValue("N{$r2}", !empty($rec->clinical_first_treatment_date) ? 'd: '.$fmt($rec->clinical_first_treatment_date) : 'd: ');

                // O: Clinical Retreatment (row-1 / row-2 date)
                $sheet->setCellValue("O{$r1}", $rec->clinical_retreatment ?? '');
                $sheet->setCellValue("O{$r2}", !empty($rec->clinical_retreatment_date) ? 'd: '.$fmt($rec->clinical_retreatment_date) : 'd: ');

                // P: Clinical Cured (row-1 / row-2 date)
                $sheet->setCellValue("P{$r1}", $rec->clinical_cured ?? '');
                $sheet->setCellValue("P{$r2}", !empty($rec->clinical_cured_date) ? 'd: '.$fmt($rec->clinical_cured_date) : 'd: ');

                // Q: Diagnostic Test (row-1 test name / row-2 date of diagnosis)
                $sheet->setCellValue("Q{$r1}", $rec->diagnostic_test ?? '');
                $sheet->setCellValue("Q{$r2}", !empty($rec->date_of_diagnosis) ? 'd: '.$fmt($rec->date_of_diagnosis) : 'd: ');

                $sheet->setCellValue("R{$r1}", $rec->diagnostic_result ?? '');
                $sheet->setCellValue("S{$r1}", $fmt($rec->date_confirmed));
                $sheet->setCellValue("T{$r1}", $rec->complicated ?? '');

                // U: Confirmed 1st Treatment (row-1 / row-2 date)
                $sheet->setCellValue("U{$r1}", $rec->confirmed_first_treatment_given ?? '');
                $sheet->setCellValue("U{$r2}", !empty($rec->confirmed_first_treatment_date) ? 'd: '.$fmt($rec->confirmed_first_treatment_date) : 'd: ');

                // V: Confirmed Retreatment (row-1 / row-2 date)
                $sheet->setCellValue("V{$r1}", $rec->confirmed_retreatment ?? '');
                $sheet->setCellValue("V{$r2}", !empty($rec->confirmed_retreatment_date) ? 'd: '.$fmt($rec->confirmed_retreatment_date) : 'd: ');

                // W: Confirmed Cured (row-1 / row-2 date)
                $sheet->setCellValue("W{$r1}", $rec->confirmed_cured ?? '');
                $sheet->setCellValue("W{$r2}", !empty($rec->confirmed_cured_date) ? 'd: '.$fmt($rec->confirmed_cured_date) : 'd: ');

                $sheet->setCellValue("X{$r1}", $fmt($rec->date_referred_to_hospital));

                // Y: MDA (row-1 flag / row-2 date)
                $sheet->setCellValue("Y{$r1}", $rec->mda_given ?? '');
                $sheet->setCellValue("Y{$r2}", !empty($rec->mda_date_given) ? 'd: '.$fmt($rec->mda_date_given) : 'd: ');

                $sheet->setCellValue("Z{$r1}", $rec->remarks ?? '');
            } else {
                foreach (['L','N','O','P','Q','U','V','W','Y'] as $col) {
                    $sheet->setCellValue("{$col}{$r2}", 'd: ');
                }
                $sheet->mergeCells("M{$r1}:M{$r2}");
            }

            $sheet->getRowDimension($r1)->setRowHeight(15);
            $sheet->getRowDimension($r2)->setRowHeight(13);
            $currentRow += 2;
        }

        /*
        |--------------------------------------------------------------------------
        | 5. FOOTNOTE
        |--------------------------------------------------------------------------
        */
        $fn  = $currentRow;
        $fn2 = $currentRow + 1;
        $fn3 = $currentRow + 2;
        $fn4 = $currentRow + 3;
        $sheet->mergeCells("A{$fn}:E{$fn}");
        $sheet->setCellValue("A{$fn}", 'Resident: Refers to the place where the person habitually, permanently resides.');
        $sheet->mergeCells("A{$fn2}:E{$fn2}");
        $sheet->setCellValue("A{$fn2}", 'Non-Resident: has a temporary or less permanent connection.');
        $sheet->mergeCells("G{$fn}:Z{$fn3}");
        $sheet->setCellValue("G{$fn}", "* For Signs & Symptoms:\nA - Abdominal pain\nB - Diarrhea\nC - Blood in the stool\nD - Others; Specify");
        $sheet->getStyle("A{$fn}:Z{$fn4}")->applyFromArray(['font' => ['size' => 7, 'name' => 'Arial', 'italic' => true], 'alignment' => ['wrapText' => true]]);
        foreach (range($fn, $fn4) as $r) { $sheet->getRowDimension($r)->setRowHeight(12); }

        /*
        |--------------------------------------------------------------------------
        | 6. BORDERS, WIDTHS, ALIGNMENT
        |--------------------------------------------------------------------------
        */
        $lastRow = $currentRow - 1;
        $sheet->getStyle("A2:Z{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A4:Z{$lastRow}")->applyFromArray([
            'font'      => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
        ]);
        $sheet->getStyle("D4:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("E4:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("Z4:Z{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        foreach ([
            'A'=>5,'B'=>13,'C'=>13,'D'=>26,'E'=>20,'F'=>10,'G'=>11,'H'=>7,'I'=>16,'J'=>8,
            'K'=>16,'L'=>14,'M'=>14,'N'=>16,'O'=>14,'P'=>14,
            'Q'=>14,'R'=>12,'S'=>12,'T'=>14,'U'=>16,'V'=>14,'W'=>14,
            'X'=>14,'Y'=>14,'Z'=>22,
        ] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        /*
        |--------------------------------------------------------------------------
        | 7. STREAM FILE
        |--------------------------------------------------------------------------
        */
        $filename = "SCHISTOSOMIASIS_REGISTRY_{$year}_{$barangay}.xlsx";
        $savePath = storage_path("app/{$filename}");
        (new Xlsx($spreadsheet))->save($savePath);

        return response()->download($savePath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Download Soil-Transmitted Helminthiasis Registry
     * Layout mirrors SoilTransmitted_Helminthiasis_Registry.xlsx — sheet: STH_Registry
     *
     * Header structure : rows 1 (title), 2 (main headers), 3 (note), 4 (MDA sub-headers)
     * Data structure   : 2 rows per record (row-1 = values / row-2 = date sub-values)
     * Total columns    : S (19)
     */
    public function soilTransmittedHelminthiasisRegistryDownload(Request $request)
    {
        $year     = $request->input('year', date('Y'));
        $barangay = $request->input('barangay', 'All');

        $records = DB::table('sth_registry_records')
            ->when($barangay !== 'All', fn($q) => $q->where('address', 'like', "%{$barangay}%"))
            ->whereYear('created_at', $year)
            ->orderBy('created_at')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('STH_Registry');
        $sheet->setShowGridlines(true);

        $FILL_H    = 'CFE2F3';
        $FILL_NOTE = 'FFF2CC';

        $hStyle = [
            'font'      => ['bold' => true, 'size' => 8, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
            'fill'      => ['fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $FILL_H]],
        ];

        $mc  = fn(string $range, $val) => $sheet->mergeCells($range)
            && $sheet->setCellValue(explode(':', $range)[0], $val);
        $fmt = fn($d) => (!empty($d) && strtotime($d)) ? date('m/d/Y', strtotime($d)) : '';

        /*
        |--------------------------------------------------------------------------
        | 1. TITLE ROW
        |--------------------------------------------------------------------------
        */
        $mc('A1:S1', 'Soil-Transmitted Helminthiasis Registry');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        /*
        |--------------------------------------------------------------------------
        | 2. COLUMN HEADERS (rows 2–4)
        |
        | A  No.                        merged 2-4
        | B  Date of Registration       merged 2-4
        | C  Family Serial Number       merged 2-4
        | D  Patient Full Name          merged 2-4
        | E  Complete Address           merged 2-4
        | F  Resident / Non-Resident    merged 2-4
        | G  Date of Birth              merged 2-4
        | H  Age (in years)             merged 2-4
        | I  Age Classification         merged 2-4
        | J  Sex                        merged 2-4
        | K  Screened for STH + Date    merged 2-4
        | L  Screening Result           merged 2-4
        | M  Date of Result             merged 2-4
        | N  Treatment Given + Date     merged 2-4
        |    ── Deworming MDA (O2) ──
        | O  Given Deworming Tablet     span (note in row 3)
        | O  January MDA date           row 4
        | P  January MDA modality       row 4
        | Q  July MDA date              row 4
        | R  July MDA modality          row 4
        | S  Remarks                    merged 2-4
        |--------------------------------------------------------------------------
        */
        foreach (['A2:A4','B2:B4','C2:C4','D2:D4','E2:E4','F2:F4',
                  'G2:G4','H2:H4','I2:I4','J2:J4',
                  'K2:K4','L2:L4','M2:M4','N2:N4','S2:S4'] as $r) {
            $sheet->mergeCells($r);
        }

        $sheet->setCellValue('A2', 'No.');
        $sheet->setCellValue('B2', "Date of Registration\n(mm/dd/yy)");
        $sheet->setCellValue('C2', 'Family Serial Number');
        $sheet->setCellValue('D2', "Patient Full Name\n(LastName, FullName, MI)");
        $sheet->setCellValue('E2', 'Complete Address');
        $sheet->setCellValue('F2', "Resident - 1\nNon-Resident - 0");
        $sheet->setCellValue('G2', "Date of Birth\n(mm/dd/yy)");
        $sheet->setCellValue('H2', "Age\n(in years)");
        $sheet->setCellValue('I2', "Age Classification\n\nA - 1-4 yrs old\nB - 5-14 yrs old\nC - 15-19 yrs old\nD - 20-59 yrs old\nE - 60 yrs old and above");
        $sheet->setCellValue('J2', "Sex\n\nM - Male\nF - Female");
        $sheet->setCellValue('K2', "Screened for STH\n(Kato-Katz/Kato Thick/Fecalysis)\n\n1 - Yes\n0 - No\n\nDate of Screening\n(mm/dd/yy)");
        $sheet->setCellValue('L2', "Screening Result\n\n0 - Negative\n1 - Suspected\n2 - Positive");
        $sheet->setCellValue('M2', 'Date of Result');
        $sheet->setCellValue('N2', "Treatment Given\n\n0 - None\n1 - Albendazole\n2 - Mebendazole\n\n\nDate Given\n(mm/dd/yy)");
        $sheet->setCellValue('S2', 'Remarks');

        // Deworming MDA header (row 2 span + note row 3 + MDA sub-cols row 4)
        $mc('O2:R2', 'Given Deworming Tablet\nfor ages 1-4, 5-14, 15-19 only');
        $mc('O3:R3', "Note: Deworming activities are conducted every January with catch-up in February and March. The reporting for the January MDA shall close by the end of March.\nDeworming activities are conducted every July with catch-up in August and September. The reporting for the July MDA shall close by end of September.");
        $sheet->setCellValue('O4', "January MDA\n(mm/dd/yy)");
        $sheet->setCellValue('P4', "1 - School-based\n2 - Community based");
        $sheet->setCellValue('Q4', "July MDA\n(mm/dd/yy)");
        $sheet->setCellValue('R4', "1 - School-based\n2 - Community based");

        $sheet->getStyle('A2:S4')->applyFromArray($hStyle);
        $sheet->getStyle('O3:R3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB($FILL_NOTE);
        $sheet->getStyle('O3:R3')->getFont()->setBold(false)->setItalic(true);

        foreach ([2=>45, 3=>45, 4=>25] as $r => $h) {
            $sheet->getRowDimension($r)->setRowHeight($h);
        }

        /*
        |--------------------------------------------------------------------------
        | 3. DATA ROWS (2 rows per record)
        |--------------------------------------------------------------------------
        */
        $staticMerge = ['A','B','C','D','E','F','G','H','I','J','L','M','P','R','S'];

        $currentRow = 5;
        $totalRows  = max($records->count(), 10);

        for ($idx = 1; $idx <= $totalRows; $idx++) {
            $rec = $records->get($idx - 1);
            $r1  = $currentRow;
            $r2  = $currentRow + 1;

            foreach ($staticMerge as $col) {
                $sheet->mergeCells("{$col}{$r1}:{$col}{$r2}");
            }

            $sheet->setCellValue("A{$r1}", $idx);

            if ($rec) {
                $sheet->setCellValue("B{$r1}", $fmt($rec->date_of_registration));
                $sheet->setCellValue("C{$r1}", $rec->family_serial_number ?? '');
                $sheet->setCellValue("D{$r1}", strtoupper($rec->name ?? ''));
                $sheet->setCellValue("E{$r1}", $rec->address ?? '');
                $sheet->setCellValue("F{$r1}", $rec->residency ?? '');
                $sheet->setCellValue("G{$r1}", $fmt($rec->date_of_birth));
                $sheet->setCellValue("H{$r1}", $rec->age ?? '');
                $sheet->setCellValue("I{$r1}", $rec->age_classification ?? '');
                $sheet->setCellValue("J{$r1}", $rec->sex ?? '');

                // K: Screened (row-1) / Date (row-2)
                $sheet->setCellValue("K{$r1}", $rec->screened ?? '');
                $sheet->setCellValue("K{$r2}", !empty($rec->date_of_screening) ? 'd: '.$fmt($rec->date_of_screening) : 'd: ');

                $sheet->setCellValue("L{$r1}", $rec->screening_result ?? '');
                $sheet->setCellValue("M{$r1}", $fmt($rec->date_of_result));

                // N: Treatment (row-1) / Date (row-2)
                $sheet->setCellValue("N{$r1}", $rec->treatment_given ?? '');
                $sheet->setCellValue("N{$r2}", !empty($rec->treatment_date_given) ? 'd: '.$fmt($rec->treatment_date_given) : 'd: ');

                // O: January MDA date (row-1) / modality (row-2... but P is merged static for modality)
                $sheet->setCellValue("O{$r1}", !empty($rec->january_mda_date) ? 'd: '.$fmt($rec->january_mda_date) : 'd: ');
                $sheet->setCellValue("P{$r1}", $rec->january_mda_modality ?? '');

                $sheet->setCellValue("Q{$r1}", !empty($rec->july_mda_date) ? 'd: '.$fmt($rec->july_mda_date) : 'd: ');
                $sheet->setCellValue("R{$r1}", $rec->july_mda_modality ?? '');

                $sheet->setCellValue("S{$r1}", $rec->remarks ?? '');
            } else {
                $sheet->setCellValue("K{$r2}", 'd: ');
                $sheet->setCellValue("N{$r2}", 'd: ');
                $sheet->setCellValue("O{$r1}", 'd: ');
                $sheet->setCellValue("Q{$r1}", 'd: ');
            }

            $sheet->getRowDimension($r1)->setRowHeight(15);
            $sheet->getRowDimension($r2)->setRowHeight(13);
            $currentRow += 2;
        }

        /*
        |--------------------------------------------------------------------------
        | 4. BORDERS, WIDTHS, ALIGNMENT
        |--------------------------------------------------------------------------
        */
        $lastRow = $currentRow - 1;
        $sheet->getStyle("A2:S{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A5:S{$lastRow}")->applyFromArray([
            'font'      => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
        ]);
        $sheet->getStyle("D5:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("E5:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("S5:S{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        foreach ([
            'A'=>5,'B'=>13,'C'=>13,'D'=>26,'E'=>20,'F'=>10,'G'=>11,'H'=>7,
            'I'=>18,'J'=>8,'K'=>14,'L'=>14,'M'=>12,'N'=>14,
            'O'=>13,'P'=>13,'Q'=>13,'R'=>13,'S'=>22,
        ] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. STREAM FILE
        |--------------------------------------------------------------------------
        */
        $filename = "STH_REGISTRY_{$year}_{$barangay}.xlsx";
        $savePath = storage_path("app/{$filename}");
        (new Xlsx($spreadsheet))->save($savePath);

        return response()->download($savePath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Download Target Client List for Mental Health
     * Layout mirrors Mental_Health.xlsx — sheet: TCL_MH
     *
     * Header structure : row 1 (title), rows 2-3 (column headers, merged)
     * Data structure   : 1 row per record
     * Total columns    : J (10)
     */
    public function mentalHealthDownload(Request $request)
    {
        $year     = $request->input('year', date('Y'));
        $barangay = $request->input('barangay', 'All');

        $records = DB::table('mental_health_records')
            ->when($barangay !== 'All', fn($q) => $q->where('address', 'like', "%{$barangay}%"))
            ->whereYear('created_at', $year)
            ->orderBy('created_at')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('TCL_MH');
        $sheet->setShowGridlines(true);

        $FILL_H = 'CFE2F3';

        $hStyle = [
            'font'      => ['bold' => true, 'size' => 8, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
            'fill'      => ['fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $FILL_H]],
        ];

        $mc  = fn(string $range, $val) => $sheet->mergeCells($range)
            && $sheet->setCellValue(explode(':', $range)[0], $val);
        $fmt = fn($d) => (!empty($d) && strtotime($d)) ? date('m/d/Y', strtotime($d)) : '';

        /*
        |--------------------------------------------------------------------------
        | 1. TITLE ROW
        |--------------------------------------------------------------------------
        */
        $mc('A1:J1', 'Target Client List for Mental Health');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        /*
        |--------------------------------------------------------------------------
        | 2. COLUMN HEADERS (rows 2-3, merged)
        |
        | A  No.
        | B  Date of Assessment
        | C  Family Serial Number
        | D  Name
        | E  Complete Address
        | F  Date of Birth
        | G  Age (in years)
        | H  Age Group
        | I  Sex
        | J  Screened using mhGAP
        |--------------------------------------------------------------------------
        */
        foreach (['A2:A3','B2:B3','C2:C3','D2:D3','E2:E3',
                  'F2:F3','G2:G3','H2:H3','I2:I3','J2:J3'] as $r) {
            $sheet->mergeCells($r);
        }

        $sheet->setCellValue('A2', 'No.');
        $sheet->setCellValue('B2', "Date of Assessment\n(mm/dd/yy)");
        $sheet->setCellValue('C2', 'Family Serial Number');
        $sheet->setCellValue('D2', "Name\n(LastName, FullName, MI)");
        $sheet->setCellValue('E2', 'Complete Address');
        $sheet->setCellValue('F2', "Date of Birth\n(mm/dd/yy)");
        $sheet->setCellValue('G2', "Age\n(in years)");
        $sheet->setCellValue('H2', "Age Group\n\nA: 0-9 yo\nB: 10-19 yo\nC: 20-59 yo\nD: 60 yo and above");
        $sheet->setCellValue('I2', "Sex\n\nM - Male\nF - Female");
        $sheet->setCellValue('J2', "Screened using the Mental Health Gap Action Programme (mhGAP)\n\n1 - Yes\n0 - No");

        $sheet->getStyle('A2:J3')->applyFromArray($hStyle);
        $sheet->getRowDimension(2)->setRowHeight(55);
        $sheet->getRowDimension(3)->setRowHeight(0);

        /*
        |--------------------------------------------------------------------------
        | 3. DATA ROWS (1 row per record)
        |--------------------------------------------------------------------------
        */
        $currentRow = 4;
        $totalRows  = max($records->count(), 10);

        for ($idx = 1; $idx <= $totalRows; $idx++) {
            $rec = $records->get($idx - 1);

            $sheet->setCellValue("A{$currentRow}", $idx);

            if ($rec) {
                $sheet->setCellValue("B{$currentRow}", $fmt($rec->dateOfAssessment));
                $sheet->setCellValue("C{$currentRow}", $rec->familySerialNumber ?? '');
                $sheet->setCellValue("D{$currentRow}", strtoupper($rec->name ?? ''));
                $sheet->setCellValue("E{$currentRow}", $rec->address ?? '');
                $sheet->setCellValue("F{$currentRow}", $fmt($rec->dateOfBirth));
                $sheet->setCellValue("G{$currentRow}", $rec->age ?? '');
                $sheet->setCellValue("H{$currentRow}", $rec->ageGroup ?? '');
                $sheet->setCellValue("I{$currentRow}", $rec->sex ?? '');
                $sheet->setCellValue("J{$currentRow}", $rec->screenedMhgap ? '1' : '0');
            }

            $sheet->getRowDimension($currentRow)->setRowHeight(20);
            $currentRow++;
        }

        /*
        |--------------------------------------------------------------------------
        | 4. BORDERS, WIDTHS, ALIGNMENT
        |--------------------------------------------------------------------------
        */
        $lastRow = $currentRow - 1;
        $sheet->getStyle("A2:J{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A4:J{$lastRow}")->applyFromArray([
            'font'      => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
        ]);
        $sheet->getStyle("D4:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("E4:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        foreach ([
            'A'=>5,'B'=>14,'C'=>14,'D'=>28,'E'=>22,'F'=>11,'G'=>8,'H'=>16,'I'=>8,'J'=>22,
        ] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. STREAM FILE
        |--------------------------------------------------------------------------
        */
        $filename = "TCL_MENTAL_HEALTH_{$year}_{$barangay}.xlsx";
        $savePath = storage_path("app/{$filename}");
        (new Xlsx($spreadsheet))->save($savePath);

        return response()->download($savePath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Download Masterlist for Environmental Health and Sanitation
     * Layout mirrors Masterlist_for_Environmental_Health.xlsx — sheet: Masterlist_ENVI
     *
     * Header structure : row 1 (title), row 2 (two main section spans + Remarks),
     *                    row 3 (sub-section spans), rows 4-5 (leaf column headers)
     * Data structure   : 1 row per record
     * Total columns    : Y (25)
     */
    public function environmentalHealthDownload(Request $request)
    {
        $year     = $request->input('year', date('Y'));
        $barangay = $request->input('barangay', 'All');

        $records = DB::table('environmental_health_records')
            ->whereYear('created_at', $year)
            ->orderBy('created_at')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Masterlist_ENVI');
        $sheet->setShowGridlines(true);

        $FILL_WATER  = 'DAEEF3'; // light blue for water section
        $FILL_SANIT  = 'E2EFDA'; // light green for sanitation section
        $FILL_H      = 'CFE2F3'; // general header

        $hStyle = [
            'font'      => ['bold' => true, 'size' => 7, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
        ];

        $mc  = fn(string $range, $val) => $sheet->mergeCells($range)
            && $sheet->setCellValue(explode(':', $range)[0], $val);
        $fmt = fn($d) => (!empty($d) && strtotime($d)) ? date('m/d/Y', strtotime($d)) : '';
        $chk = fn($v) => $v ? '✔' : '';

        /*
        |--------------------------------------------------------------------------
        | 1. TITLE ROW
        |--------------------------------------------------------------------------
        */
        $mc('A1:Y1', 'Masterlist for Environmental Health and Sanitation');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(25);

        /*
        |--------------------------------------------------------------------------
        | 2. ROW 2 — TWO MAIN SECTION SPANS
        |
        | A2:M2  Masterlist for Access to Basic Safe Water Supply
        | N2:W2  Access to Basic Sanitation Facility and Use of Safely Managed Sanitation Services
        | X2     Remarks (merged 2-5)
        |--------------------------------------------------------------------------
        */
        $mc('A2:M2', 'Masterlist for Access to Basic Safe Water Supply');
        $mc('N2:W2', 'Access to Basic Sanitation Facility and Use of Safely Managed Sanitation Services');
        $mc('X2:X5', 'Remarks');

        $sheet->getStyle('A2:M2')->applyFromArray(array_merge($hStyle, ['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $FILL_WATER]]]));
        $sheet->getStyle('N2:W2')->applyFromArray(array_merge($hStyle, ['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $FILL_SANIT]]]));
        $sheet->getStyle('X2')->applyFromArray(array_merge($hStyle, ['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $FILL_H]]]));
        $sheet->getRowDimension(2)->setRowHeight(22);

        /*
        |--------------------------------------------------------------------------
        | 3. ROW 3 — SUB-SECTION SPANS
        |
        | A3:A5  No.
        | B3:B5  Name of Household Head
        | C3:H3  To be accomplished during HH visit  (water types + others + location + availability)
        | I3:L3  Validation/Random Sampling/Testing
        | M3:M5  Status (SMDWS)
        | N3:P3  Type of Sanitary Toilet Facility
        | Q3:Q5  Type of Unsanitary Toilet
        | R3:R5  Toilet shared
        | S3:S5  Basic Sanitation Facility
        | T3:V3  Disposal/Treatment of Excreta/Sewage
        | W3:W5  STATUS (SMSS)
        |--------------------------------------------------------------------------
        */
        $mc('A3:A5', 'No.');
        $mc('B3:B5', "Name of Household Head\n(LastName, FullName, MI)");
        $mc('C3:H3', 'To be accomplished during the visit to the Households (HHs)');
        $mc('I3:L3', 'Validation/Random Sampling/Testing');
        $mc('M3:M5', "Safely Managed Drinking-Water Service (SMDWS)\n\n(Applicable only if Type of Water Source is either Level I or Level III, located inside the dwelling or within its premises, available at least 12 hours per day, and free of fecal contamination)\n\n1 - Yes  0 - No");
        $mc('N3:P3', "Type of Sanitary Toilet Facility\n(place a check)");
        $mc('Q3:Q5', "Type of Unsanitary Toilet\n\n3 - Water sealed connected to open drain\n2 - Overhung Latrine\n1 - Open Pit Latrine\n0 - Without Toilet");
        $mc('R3:R5', "Toilet is shared w/ other households in a separate dwelling\n\n(Applicable only if Status of Sanitary Toilet Facility is CHECK)\n\n1 - Yes  0 - No");
        $mc('S3:S5', "Basic Sanitation Facility\n\nIf with any sanitary toilet facility and toilet is not shared\n\n1 - Yes  0 - No");
        $mc('T3:V3', "Disposal/Treatment of Excreta/Sewage\n(mm/dd/yy)\n(Applicable only if Sanitary Toilet Facility is CHECK)");
        $mc('W3:W5', "Safely Managed Sanitation Service (SMSS)\n\n(Applicable only if YES in BSF and with any of the disposal/treatment of excreta/sewage)\n\n1 - Yes  0 - No");

        $sheet->getStyle('A3:W3')->applyFromArray($hStyle);
        $sheet->getStyle('A3:M3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($FILL_WATER);
        $sheet->getStyle('N3:W3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($FILL_SANIT);
        $sheet->getRowDimension(3)->setRowHeight(45);

        /*
        |--------------------------------------------------------------------------
        | 4. ROW 4 — LEAF SUB-HEADERS
        |--------------------------------------------------------------------------
        */
        $mc('C4:E4', "Type of Improved Water Source (WS)\n(place a check)");
        $mc('F4:F5', "Others, specify\n(A water supply facility or source subject to re-contamination)");
        $mc('G4:G5', "Located inside the dwelling or within its premises [yard/land]\n(please check)");
        $mc('H4:H5', "Available at least 12 hours per day\n(please check)");
        $mc('I4:J4', 'Microbiological Testing');
        $mc('K4:L4', "Physico-Chemical Test for Arsenic\n(optional)");
        $mc('N4:N5', 'Pour/flush type connected to septic tank');
        $mc('O4:O5', 'Pour/flush toilet connected to community sewer OR to sewerage system');
        $mc('P4:P5', 'Ventilated Pit (VIP) Latrine or Composting Toilet');
        $mc('T4:T5', "Sewage/excreta is either stored in a containment tank and treated (in situ) and application of sanitation by-products for reuse/disposal");
        $mc('U4:U5', "Stored in a containment tank desludged, transported, treated and disposed off-site and application of sanitation by-products for reuse/disposal");
        $mc('V4:V5', "Stored in a containment tank or conveyed through a sewer/sewerage system and treated off-site and application of sanitation by-products for reuse/disposal");

        $sheet->getStyle('A4:W4')->applyFromArray($hStyle);
        $sheet->getStyle('A4:M4')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($FILL_WATER);
        $sheet->getStyle('N4:W4')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($FILL_SANIT);
        $sheet->getRowDimension(4)->setRowHeight(50);

        /*
        |--------------------------------------------------------------------------
        | 5. ROW 5 — LEAF DETAIL HEADERS
        |--------------------------------------------------------------------------
        */
        $sheet->setCellValue('C5', "Level I\n(point source)");
        $sheet->setCellValue('D5', "Level II\n(communal faucet system or stand posts)");
        $sheet->setCellValue('E5', "Level III\n(waterworks system or individual house connection)");
        $sheet->setCellValue('I5', "Date Validation Done\n(mm/dd/yy)");
        $sheet->setCellValue('J5', "Results\n\n1 - presence of E.coli\n0 - absence of E. coli");
        $sheet->setCellValue('K5', "Date Testing Done\n(mm/dd/yy)");
        $sheet->setCellValue('L5', "Results\n1 - within allowable PNSDW limit for Arsenic\n0 - above the allowable PNSDW limit");

        $sheet->getStyle('A5:W5')->applyFromArray($hStyle);
        $sheet->getStyle('A5:M5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($FILL_WATER);
        $sheet->getStyle('N5:W5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($FILL_SANIT);
        $sheet->getRowDimension(5)->setRowHeight(40);

        /*
        |--------------------------------------------------------------------------
        | 6. DATA ROWS (1 row per record)
        |--------------------------------------------------------------------------
        */
        $currentRow = 6;
        $totalRows  = max($records->count(), 10);

        for ($idx = 1; $idx <= $totalRows; $idx++) {
            $rec = $records->get($idx - 1);

            $sheet->setCellValue("A{$currentRow}", $idx);

            if ($rec) {
                $sheet->setCellValue("B{$currentRow}", strtoupper($rec->householdHeadName ?? ''));

                // Water Source Levels (Level I = waterLevelI, II = waterLevelII, III = waterLevelIII)
                $sheet->setCellValue("C{$currentRow}", $chk($rec->waterLevelI));
                $sheet->setCellValue("D{$currentRow}", $chk($rec->waterLevelII));
                $sheet->setCellValue("E{$currentRow}", $chk($rec->waterLevelIII));
                $sheet->setCellValue("F{$currentRow}", $rec->waterSourceOthers ?? '');
                $sheet->setCellValue("G{$currentRow}", $chk($rec->waterLocatedInsideDwelling));
                $sheet->setCellValue("H{$currentRow}", $chk($rec->waterAvailable12Hours));

                // Microbiological Testing
                $sheet->setCellValue("I{$currentRow}", $fmt($rec->microbiologicalTestDate));
                $mbResult = (int)($rec->microbiologicalTestResult ?? -1);
                $sheet->setCellValue("J{$currentRow}", $mbResult === 1 ? '1' : ($mbResult === 0 ? '0' : ''));

                // Physico-Chemical (no direct DB fields for arsenic test — mapped to waterSafetyPlanOperational as proxy)
                $sheet->setCellValue("K{$currentRow}", '');
                $sheet->setCellValue("L{$currentRow}", '');

                // SMDWS
                $sheet->setCellValue("M{$currentRow}", $rec->safelyManagedDrinkingWater ? '1' : '0');

                // Sanitary Toilet Type (sanitationStatus determines which box is checked)
                $status = $rec->sanitationStatus ?? '';
                $sheet->setCellValue("N{$currentRow}", str_contains($status, 'Functional') ? '✔' : ''); // septic tank
                $sheet->setCellValue("O{$currentRow}", '');   // community sewer
                $sheet->setCellValue("P{$currentRow}", '');   // VIP latrine

                // Unsanitary toilet type
                $sheet->setCellValue("Q{$currentRow}", $rec->unsanitaryToiletType ?? '');

                // Shared toilet
                $sheet->setCellValue("R{$currentRow}", $rec->toiletShared ? '1' : '0');

                // Basic sanitation facility
                $sheet->setCellValue("S{$currentRow}", $rec->basicSanitationFacility ? '1' : '0');

                // Disposal/Treatment — date + three method flags
                $dispDate = $fmt($rec->disposalDate);
                $sheet->setCellValue("T{$currentRow}", $rec->disposalInSitu          ? ($dispDate ?: '✔') : '');
                $sheet->setCellValue("U{$currentRow}", $rec->disposalOffSiteDesludged ? ($dispDate ?: '✔') : '');
                $sheet->setCellValue("V{$currentRow}", $rec->disposalOffSiteSewer     ? ($dispDate ?: '✔') : '');

                // SMSS
                $sheet->setCellValue("W{$currentRow}", $rec->safelyManagedSanitationService ? '1' : '0');

                $sheet->setCellValue("X{$currentRow}", $rec->remarks ?? '');
            }

            $sheet->getRowDimension($currentRow)->setRowHeight(22);
            $currentRow++;
        }

        /*
        |--------------------------------------------------------------------------
        | 7. BORDERS, WIDTHS, ALIGNMENT
        |--------------------------------------------------------------------------
        */
        $lastRow = $currentRow - 1;
        $sheet->getStyle("A2:X{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A6:X{$lastRow}")->applyFromArray([
            'font'      => ['size' => 8, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
        ]);
        $sheet->getStyle("B6:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("F6:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("X6:X{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        foreach ([
            'A'=>5,'B'=>28,'C'=>9,'D'=>9,'E'=>9,'F'=>14,'G'=>12,'H'=>12,
            'I'=>13,'J'=>13,'K'=>13,'L'=>16,'M'=>16,
            'N'=>12,'O'=>12,'P'=>12,'Q'=>14,'R'=>13,'S'=>13,
            'T'=>16,'U'=>16,'V'=>16,'W'=>14,'X'=>22,
        ] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        /*
        |--------------------------------------------------------------------------
        | 8. STREAM FILE
        |--------------------------------------------------------------------------
        */
        $filename = "MASTERLIST_ENVIRONMENTAL_HEALTH_{$year}_{$barangay}.xlsx";
        $savePath = storage_path("app/{$filename}");
        (new Xlsx($spreadsheet))->save($savePath);

        return response()->download($savePath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Download Target Client List (TCL) for Eye Ailment/s Screening
     * Layout mirrors Eyes_Screening.xlsx — sheet: TCL_VA
     *
     * Header structure : row 1 (title), rows 2–3 (column headers, merged)
     * Data structure   : 1 row per record
     * Total columns    : M (13)
     */
    public function eyesScreeningDownload(Request $request)
    {
        $year     = $request->input('year', date('Y'));
        $barangay = $request->input('barangay', 'All');

        $records = DB::table('eyes_screenings')
            ->when($barangay !== 'All', fn($q) => $q->where('address', 'like', "%{$barangay}%"))
            ->whereYear('created_at', $year)
            ->orderBy('created_at')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('TCL_VA');
        $sheet->setShowGridlines(true);

        $FILL_H = 'CFE2F3';

        $hStyle = [
            'font'      => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
            'fill'      => ['fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $FILL_H]],
        ];

        $mc  = fn(string $range, $val) => $sheet->mergeCells($range)
            && $sheet->setCellValue(explode(':', $range)[0], $val);
        $fmt = fn($d) => (!empty($d) && strtotime($d)) ? date('m/d/Y', strtotime($d)) : '';

        /*
        |--------------------------------------------------------------------------
        | 1. TITLE ROW
        |--------------------------------------------------------------------------
        */
        $mc('A1:M1', 'Target Client List for Eye Ailment/s Screening');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 22, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        /*
        |--------------------------------------------------------------------------
        | 2. COLUMN HEADERS (rows 2–3)
        |
        | A  No.                       merged 2-3
        | B  Date of Screening         merged 2-3
        | C  Family Serial Number      merged 2-3
        | D  Name                      merged 2-3
        | E  Complete Address          merged 2-3
        | F  Date of Birth             merged 2-3
        | G  Age (in years)            merged 2-3
        | H  Age Group                 merged 2-3
        | I  Sex                       merged 2-3
        | J  Screened for Eye Ailment  merged 2-3
        |    ── All Age Groups (K2:L2) ──
        | K  Identified with Eye Diseases   row 3
        | L  Date Referred                  row 3
        | M  Remarks                   merged 2-3
        |--------------------------------------------------------------------------
        */
        foreach (['A2:A3','B2:B3','C2:C3','D2:D3','E2:E3','F2:F3',
                  'G2:G3','H2:H3','I2:I3','J2:J3','M2:M3'] as $r) {
            $sheet->mergeCells($r);
        }
        $sheet->setCellValue('A2', 'No.');
        $sheet->setCellValue('B2', "Date of Screening\n(mm/dd/yy)");
        $sheet->setCellValue('C2', 'Family Serial Number');
        $sheet->setCellValue('D2', "Name\n(LastName, FullName, MI)");
        $sheet->setCellValue('E2', 'Complete Address');
        $sheet->setCellValue('F2', "Date of Birth\n(mm/dd/yy)");
        $sheet->setCellValue('G2', "Age\n(in years)");
        $sheet->setCellValue('H2', "Age Group\n\nA- 0-9 years old\nB - 10-19 years old\nC - 20-59 years old\nD - 60 years old and above");
        $sheet->setCellValue('I2', "Sex\n\nM - Male\nF - Female");
        $sheet->setCellValue('J2', "Screened for Eye Ailment/s\n\n1 - Yes\n0 - No");
        $mc('K2:L2', 'All Age Groups');
        $sheet->setCellValue('M2', 'Remarks');

        $sheet->setCellValue('K3', "Identified with Eye Diseases\n\nA - Changes in Vision\nB - Changes in Appearance\nC - Eye and Orbital Injury\nD - Routine Eye Exam\n0 - No\n");
        $sheet->setCellValue('L3', "Date Referred to Eye Care Professionals (Ophthalmologists and Optometrist)\n\n(mm/dd/yy)");

        $sheet->getStyle('A2:M3')->applyFromArray($hStyle);
        $sheet->getRowDimension(2)->setRowHeight(40);
        $sheet->getRowDimension(3)->setRowHeight(55);

        /*
        |--------------------------------------------------------------------------
        | 3. DATA ROWS (1 row per record)
        |--------------------------------------------------------------------------
        */
        $currentRow = 4;
        $totalRows  = max($records->count(), 10);

        for ($idx = 1; $idx <= $totalRows; $idx++) {
            $rec = $records->get($idx - 1);

            $sheet->setCellValue("A{$currentRow}", $idx);

            if ($rec) {
                $sheet->setCellValue("B{$currentRow}", $fmt($rec->date_screening));
                $sheet->setCellValue("C{$currentRow}", $rec->family_serial ?? '');
                $sheet->setCellValue("D{$currentRow}", strtoupper($rec->name ?? ''));
                $sheet->setCellValue("E{$currentRow}", $rec->address ?? '');
                $sheet->setCellValue("F{$currentRow}", $fmt($rec->date_of_birth));
                $sheet->setCellValue("G{$currentRow}", $rec->age ?? '');
                $sheet->setCellValue("H{$currentRow}", $rec->age_group ?? '');
                $sheet->setCellValue("I{$currentRow}", $rec->sex ?? '');
                $sheet->setCellValue("J{$currentRow}", $rec->screened ?? '');
                $sheet->setCellValue("K{$currentRow}", $rec->eye_disease_code ?? '');
                $sheet->setCellValue("L{$currentRow}", $fmt($rec->date_referred));
                $sheet->setCellValue("M{$currentRow}", $rec->remarks ?? '');
            }

            $sheet->getRowDimension($currentRow)->setRowHeight(20);
            $currentRow++;
        }

        /*
        |--------------------------------------------------------------------------
        | 4. BORDERS, WIDTHS, ALIGNMENT
        |--------------------------------------------------------------------------
        */
        $lastRow = $currentRow - 1;
        $sheet->getStyle("A2:M{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A4:M{$lastRow}")->applyFromArray([
            'font'      => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
        ]);
        $sheet->getStyle("D4:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("E4:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("M4:M{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        foreach ([
            'A'=>9,'B'=>14,'C'=>13,'D'=>28,'E'=>22,'F'=>13,'G'=>10,
            'H'=>16,'I'=>10,'J'=>13,'K'=>34,'L'=>17,'M'=>22,
        ] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. STREAM FILE
        |--------------------------------------------------------------------------
        */
        $filename = "TCL_EYES_SCREENING_{$year}_{$barangay}.xlsx";
        $savePath = storage_path("app/{$filename}");
        (new Xlsx($spreadsheet))->save($savePath);

        return response()->download($savePath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Download Target Client List (TCL) for Cervical Cancer Screening and Breast Mass Examination
     * Layout mirrors Cervical_Cancer_Screening.xlsx — sheet: TCL_CANCER
     *
     * Header structure : rows 1-2 (title), rows 3-5 (column headers, 3-tier)
     * Data structure   : 1 row per record
     * Total columns    : P (16)
     */
    public function cervicalCancerScreeningDownload(Request $request)
    {
        $year     = $request->input('year', date('Y'));
        $barangay = $request->input('barangay', 'All');

        $records = DB::table('cervical_cancer_screenings')
            ->when($barangay !== 'All', fn($q) => $q->where('address', 'like', "%{$barangay}%"))
            ->whereYear('created_at', $year)
            ->orderBy('created_at')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('TCL_CANCER');
        $sheet->setShowGridlines(true);

        $FILL_H = 'CFE2F3';

        $hStyle = [
            'font'      => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
            'fill'      => ['fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $FILL_H]],
        ];

        $mc  = fn(string $range, $val) => $sheet->mergeCells($range)
            && $sheet->setCellValue(explode(':', $range)[0], $val);
        $fmt = fn($d) => (!empty($d) && strtotime($d)) ? date('m/d/Y', strtotime($d)) : '';

        /*
        |--------------------------------------------------------------------------
        | 1. TITLE ROWS (1-2)
        |--------------------------------------------------------------------------
        */
        $mc('A1:S2', 'Target Client List for Cervical Cancer Screening and Breast Mass Examination');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 22, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(28);

        /*
        |--------------------------------------------------------------------------
        | 2. COLUMN HEADERS (rows 3–5)
        |
        | A  No.                    merged 3-5
        | B  Date of Assessment     merged 3-5
        | C  Family Serial Number   merged 3-5
        | D  Name                   merged 3-5
        | E  Complete Address       merged 3-5
        | F  Date of Birth          merged 3-5
        | G  Age (in years)         merged 3-5
        |    ── Cervical Cancer (H3:J3) ──
        | H  Cervical Screening Done    row 4
        | I  Result of Diagnosis        row 4
        | J  Linked to Care             row 4
        |    ── Breast Cancer (K3:O3) ──
        | K  Risk Assessment Result     row 4
        | L  Age to Risk Classification row 4
        | M  Examination                row 4
        | N  Results                    row 4
        | O  Linked to Care             row 4
        | P  Remarks                merged 3-5
        |--------------------------------------------------------------------------
        */
        foreach (['A3:A5','B3:B5','C3:C5','D3:D5','E3:E5','F3:F5','G3:G5','P3:P5'] as $r) {
            $sheet->mergeCells($r);
        }
        $sheet->setCellValue('A3', 'No.');
        $sheet->setCellValue('B3', "Date of Assessment\n(mm/dd/yy)");
        $sheet->setCellValue('C3', 'Family Serial Number');
        $sheet->setCellValue('D3', "Name\n(LastName, FullName, MI)");
        $sheet->setCellValue('E3', 'Complete Address');
        $sheet->setCellValue('F3', "Date of Birth\n(mm/dd/yy)");
        $sheet->setCellValue('G3', "Age\n(in years)\n");
        $mc('H3:J3', 'Cervical Cancer');
        $mc('K3:O3', 'Breast Cancer');
        $sheet->setCellValue('P3', 'Remarks');

        $mc('H4:H5', "Cervical Cancer Screening Done\n\n3 - HPV DNA\n2 - Pap Smear\n1 - VIA\n0 - Assessed only");
        $mc('I4:I5', "Result of Diagnosis or Screening\n\n\n2 - Positive\n1 - Suspicious CA\n0  - Negative");
        $mc('J4:J5', "Linked to Care\n\n2 - Referred (with referral form)\n1 - Treated\n0 - No");
        $mc('K4:K5', "Risk Assessment Result\n\n2 - High-risk\n1 - Symptomatic\n0 - Asymptomatic");
        $mc('L4:L5', "Age to Risk Classification\n\nA: 30-69 high risk or symptomatic\nB: 50-69 asymptomatic");
        $mc('M4:M5', "Examination\n\nCBE - clinical breast examination\nM - mammogram or ultrasound");
        $mc('N4:N5', "Results\n\n3: Remarkable for CBE\n2: Unremarkable for CBE\n1: BI-RADS 3-6 for Mammogram/Ultrasound\n0: BI-RADS 0-2 for Mammogram/Ultrasound");
        $mc('O4:O5', "Linked to Care\n\n1 - Referred for further treatment or management\n\n0 - No");

        $sheet->getStyle('A3:P5')->applyFromArray($hStyle);
        $sheet->getRowDimension(3)->setRowHeight(20);
        $sheet->getRowDimension(4)->setRowHeight(80);
        $sheet->getRowDimension(5)->setRowHeight(20);

        /*
        |--------------------------------------------------------------------------
        | 3. DATA ROWS (1 row per record)
        |--------------------------------------------------------------------------
        */
        $currentRow = 6;
        $totalRows  = max($records->count(), 10);

        for ($idx = 1; $idx <= $totalRows; $idx++) {
            $rec = $records->get($idx - 1);

            $sheet->setCellValue("A{$currentRow}", $idx);

            if ($rec) {
                $sheet->setCellValue("B{$currentRow}", $fmt($rec->date_assessment));
                $sheet->setCellValue("C{$currentRow}", $rec->family_serial ?? '');
                $sheet->setCellValue("D{$currentRow}", strtoupper($rec->client_name ?? ''));
                $sheet->setCellValue("E{$currentRow}", $rec->address ?? '');
                $sheet->setCellValue("F{$currentRow}", $fmt($rec->date_of_birth));
                $sheet->setCellValue("G{$currentRow}", $rec->age ?? '');

                $sheet->setCellValue("H{$currentRow}", $rec->cervical_screening_done ?? '');
                $sheet->setCellValue("I{$currentRow}", $rec->cervical_result ?? '');
                $sheet->setCellValue("J{$currentRow}", $rec->cervical_linked_to_care ?? '');

                $sheet->setCellValue("K{$currentRow}", $rec->breast_risk_assessment ?? '');
                $sheet->setCellValue("L{$currentRow}", $rec->breast_age_risk_class ?? '');
                $sheet->setCellValue("M{$currentRow}", $rec->breast_exam_type ?? '');
                $sheet->setCellValue("N{$currentRow}", $rec->breast_result ?? '');
                $sheet->setCellValue("O{$currentRow}", $rec->breast_linked_to_care ?? '');

                $sheet->setCellValue("P{$currentRow}", $rec->remarks ?? '');
            }

            $sheet->getRowDimension($currentRow)->setRowHeight(20);
            $currentRow++;
        }

        /*
        |--------------------------------------------------------------------------
        | 4. BORDERS, WIDTHS, ALIGNMENT
        |--------------------------------------------------------------------------
        */
        $lastRow = $currentRow - 1;
        $sheet->getStyle("A3:P{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A6:P{$lastRow}")->applyFromArray([
            'font'      => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
        ]);
        $sheet->getStyle("D6:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("E6:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("P6:P{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        foreach ([
            'A'=>7,'B'=>13,'C'=>13,'D'=>31,'E'=>39,'F'=>13,'G'=>13,
            'H'=>28,'I'=>13,'J'=>13,'K'=>13,'L'=>16,'M'=>13,'N'=>21,'O'=>16,'P'=>22,
        ] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. STREAM FILE
        |--------------------------------------------------------------------------
        */
        $filename = "TCL_CERVICAL_CANCER_SCREENING_{$year}_{$barangay}.xlsx";
        $savePath = storage_path("app/{$filename}");
        (new Xlsx($spreadsheet))->save($savePath);

        return response()->download($savePath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Download Target Client List (TCL) for Geriatric Screening and Senior Citizen Immunization
     * Layout mirrors Geriatric_Screening.xlsx — sheet: TCL_GERIATRICS&IMMU
     *
     * Header structure : rows 1-2 (title), rows 3-5 (column headers, single tier merged)
     * Data structure   : 1 row per record
     * Total columns    : N (14)
     */
    public function geriatricScreeningDownload(Request $request)
    {
        $year     = $request->input('year', date('Y'));
        $barangay = $request->input('barangay', 'All');

        $records = DB::table('geriatric_screening_records')
            ->when($barangay !== 'All', fn($q) => $q->where('address', 'like', "%{$barangay}%"))
            ->whereYear('created_at', $year)
            ->orderBy('created_at')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('TCL_GERIATRICS&IMMU');
        $sheet->setShowGridlines(true);

        $FILL_H = 'CFE2F3';

        $hStyle = [
            'font'      => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
            'fill'      => ['fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $FILL_H]],
        ];

        $mc  = fn(string $range, $val) => $sheet->mergeCells($range)
            && $sheet->setCellValue(explode(':', $range)[0], $val);
        $fmt = fn($d) => (!empty($d) && strtotime($d)) ? date('m/d/Y', strtotime($d)) : '';

        /*
        |--------------------------------------------------------------------------
        | 1. TITLE ROWS (1-2)
        |--------------------------------------------------------------------------
        */
        $mc('A1:N2', 'Target Client List for Geriatric Screening and Senior Citizen Immunization');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 22, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(28);

        /*
        |--------------------------------------------------------------------------
        | 2. COLUMN HEADERS (rows 3–5, merged single tier)
        |
        | A  No.
        | B  Date of Screening
        | C  Family Serial Number
        | D  Name
        | E  Complete Address
        | F  Date of Birth
        | G  Age (in years)
        | H  Sex
        | I  Results
        | J  Provided with individualized care plan / referred
        | K  Received PPV upon reaching 60 years old
        | L  PPV Immunization (date given)
        | M  Influenza Immunization (date given)
        | N  Remarks
        |--------------------------------------------------------------------------
        */
        foreach (['A3:A5','B3:B5','C3:C5','D3:D5','E3:E5','F3:F5','G3:G5',
                  'H3:H5','I3:I5','J3:J5','K3:K5','L3:L5','M3:M5','N3:N5'] as $r) {
            $sheet->mergeCells($r);
        }
        $sheet->setCellValue('A3', 'No.');
        $sheet->setCellValue('B3', "Date of Screening\n(mm/dd/yy)");
        $sheet->setCellValue('C3', 'Family Serial Number');
        $sheet->setCellValue('D3', "Name\n(LastName, FullName, MI)");
        $sheet->setCellValue('E3', 'Complete Address');
        $sheet->setCellValue('F3', "Date of Birth\n(mm/dd/yy)");
        $sheet->setCellValue('G3', "Age\n(in years)\n");
        $sheet->setCellValue('H3', "Sex\n\nM - Male\nF- Female");
        $sheet->setCellValue('I3', "Results\nA - Memory  \nB - Depression  \nC - Polypharmacy  \nD - Urinary Incontinence  \nE - Functional Capacity  \nF - Fall (History and Screening Test)  \nG - Malnutrition  \nH - Hearing  \nI - Vision\n0 - Negative  \n");
        $sheet->setCellValue('J3', "Provided with an individualized care plan and/or referred to the appropriate specialist or service providers\n\n1 - Yes\n0 - No");
        $sheet->setCellValue('K3', "Received PPV upon reaching 60 years old\n\n1 - Yes\n0 - No");
        $sheet->setCellValue('L3', "PPV Immunization\n(provide PPV if did not receive yet upon reaching 60 years old)\n\ndate given\n(mm/dd/yy)");
        $sheet->setCellValue('M3', "Influenza Immunization\n\ndate given\n(mm/dd/yy)");
        $sheet->setCellValue('N3', 'Remarks');

        $sheet->getStyle('A3:N5')->applyFromArray($hStyle);
        $sheet->getRowDimension(3)->setRowHeight(85);
        $sheet->getRowDimension(4)->setRowHeight(0);
        $sheet->getRowDimension(5)->setRowHeight(0);

        /*
        |--------------------------------------------------------------------------
        | 3. DATA ROWS (1 row per record)
        |--------------------------------------------------------------------------
        */
        $currentRow = 6;
        $totalRows  = max($records->count(), 10);

        for ($idx = 1; $idx <= $totalRows; $idx++) {
            $rec = $records->get($idx - 1);

            $sheet->setCellValue("A{$currentRow}", $idx);

            if ($rec) {
                $sheet->setCellValue("B{$currentRow}", $fmt($rec->date_of_screening));
                $sheet->setCellValue("C{$currentRow}", $rec->family_serial_number ?? '');
                $sheet->setCellValue("D{$currentRow}", strtoupper($rec->name ?? ''));
                $sheet->setCellValue("E{$currentRow}", $rec->address ?? '');
                $sheet->setCellValue("F{$currentRow}", $fmt($rec->date_of_birth));
                $sheet->setCellValue("G{$currentRow}", $rec->age ?? '');
                $sheet->setCellValue("H{$currentRow}", $rec->sex ?? '');
                $sheet->setCellValue("I{$currentRow}", $rec->results ?? '');
                $sheet->setCellValue("J{$currentRow}", $rec->care_plan_provided ? '1' : '0');
                $sheet->setCellValue("K{$currentRow}", $rec->ppv_received_at60 ? '1' : '0');
                $sheet->setCellValue("L{$currentRow}", $fmt($rec->ppv_date_given));
                $sheet->setCellValue("M{$currentRow}", $fmt($rec->influenza_date_given));
                $sheet->setCellValue("N{$currentRow}", $rec->remarks ?? '');
            }

            $sheet->getRowDimension($currentRow)->setRowHeight(20);
            $currentRow++;
        }

        /*
        |--------------------------------------------------------------------------
        | 4. BORDERS, WIDTHS, ALIGNMENT
        |--------------------------------------------------------------------------
        */
        $lastRow = $currentRow - 1;
        $sheet->getStyle("A3:N{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A6:N{$lastRow}")->applyFromArray([
            'font'      => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
        ]);
        $sheet->getStyle("D6:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("E6:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("N6:N{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        foreach ([
            'A'=>7,'B'=>13,'C'=>13,'D'=>31,'E'=>13,'F'=>13,'G'=>13,
            'H'=>27,'I'=>13,'J'=>13,'K'=>13,'L'=>13,'M'=>13,'N'=>22,
        ] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. STREAM FILE
        |--------------------------------------------------------------------------
        */
        $filename = "TCL_GERIATRIC_SCREENING_{$year}_{$barangay}.xlsx";
        $savePath = storage_path("app/{$filename}");
        (new Xlsx($spreadsheet))->save($savePath);

        return response()->download($savePath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Download Target Client List (TCL) for Oral Health Care
     * Layout mirrors Oral_Health_Care.xlsx — sheet: TCL_OHC
     *
     * Header structure : row 1 (title), rows 2-6 (column headers, multi-tier)
     * Data structure    : 1 row per record
     * Total columns     : AF (32)
     */
    public function oralHealthCareDownload(Request $request)
    {
        $year     = $request->input('year', date('Y'));
        $barangay = $request->input('barangay', 'All');

        $records = DB::table('oral_health_care')
            ->when($barangay !== 'All', fn($q) => $q->where('address', 'like', "%{$barangay}%"))
            ->whereYear('created_at', $year)
            ->orderBy('created_at')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('TCL_OHC');
        $sheet->setShowGridlines(true);

        $FILL_H = 'CFE2F3';

        $hStyle = [
            'font'      => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
            'fill'      => ['fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $FILL_H]],
        ];

        $mc  = fn(string $range, $val) => $sheet->mergeCells($range)
            && $sheet->setCellValue(explode(':', $range)[0], $val);
        $fmt = fn($d) => (!empty($d) && strtotime($d)) ? date('m/d/Y', strtotime($d)) : '';
        $b1  = fn($v) => $v ? '1' : '0';

        /*
        |--------------------------------------------------------------------------
        | 1. TITLE ROW
        |--------------------------------------------------------------------------
        */
        $mc('I1:AE1', 'Target Client List for Oral Health Care');
        $sheet->getStyle('I1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 18, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(23);

        /*
        |--------------------------------------------------------------------------
        | 2. COLUMN HEADERS (rows 2–6)
        |
        | A  No.                              merged 2-6
        | B  Date of Visit                    merged 2-6
        | C  Family Serial Number             merged 2-6
        | D  Name                             merged 2-6
        | E  Complete Address                 merged 2-6
        | F  Date of Birth                    merged 2-6
        | G  Age (in months)                  merged 2-6
        | H  Sex                              merged 2-6
        |    ── RPOC for 1st Visit 0-11 months (I2:M3) ──
        | I  Oral screening                    row 4
        | J  Risk assessment                   row 4
        | K  Oral hygiene instruction          row 4
        | L  Counseling                        row 4
        | M  Fluoride varnish application      row 4
        | N  Complete RPOC                    merged 2-6
        | O  Age (in years)                    merged 2-6
        | P  Age Group                         merged 2-3 (P2:Q5)
        |    ── RPOC 1yr+/Pregnant (R2:AA2) ──
        | R  Oral screening 1st                row 4
        | S  Oral screening 2nd                row 4
        | T  Risk assessment 1st               row 4
        | U  Risk assessment 2nd                row 4
        | V  Oral prophylaxis 1st               row 4
        | W  Oral prophylaxis 2nd               row 4
        | X  Fluoride varnish 1st               row 4
        | Y  Fluoride varnish 2nd               row 4
        | Z  Counseling 1st                     row 4
        | AA Counseling 2nd                     row 4
        | AB Complete RPOC 1st Visit          merged 2-6
        | AC Complete RPOC 2nd Visit          merged 2-6
        | AD Service Location                 merged 2-3(AD2:AE5) span row6: 1st/2nd visit
        | AF Remarks                          merged 2-6
        |--------------------------------------------------------------------------
        */
        foreach (['A2:A6','B2:B6','C2:C6','D2:D6','E2:E6','F2:F6','G2:G6','H2:H6',
                  'N2:N6','O2:O6','AB2:AB6','AC2:AC6','AF2:AF6'] as $r) {
            $sheet->mergeCells($r);
        }
        $sheet->setCellValue('A2', 'No.');
        $sheet->setCellValue('B2', "Date of Visit\n(mm/dd/yy)");
        $sheet->setCellValue('C2', 'Family Serial Number');
        $sheet->setCellValue('D2', "Name\n(LastName, FullName, MI)");
        $sheet->setCellValue('E2', 'Complete Address');
        $sheet->setCellValue('F2', "Date of Birth\n(mm/dd/yy)");
        $sheet->setCellValue('G2', "Age\n(in months)");
        $sheet->setCellValue('H2', "Sex\n\nM - Male\nF - Female");
        $mc('I2:M3', "Routine Preventive Oral Care (RPOC) for 1st Dental Visit of 0-11 months\n(please check)");
        $sheet->setCellValue('N2', "Complete Routine Preventive Oral Care (RPOC)\n\n1 - Yes\n0 - No");
        $sheet->setCellValue('O2', "Age\n(in years)");
        $mc('P2:Q5', "Age Group\n\nA - 1-4 years old\nB - 5-9 years old\nC - 10-19 years old\nD - 20-59 years old\nE - >60 years old\nF - Pregnant 10-14 years old\nG - Pregnant 15-19 years old\nH- Pregnant 20-49 years old");
        $mc('R2:AA2', 'Routine Preventive Oral Care (RPOC) for 1 year old and above and Pregnant');
        $sheet->setCellValue('AB2', "Complete RPOC for 1st Visit\n\n1 - Yes\n0 - No");
        $sheet->setCellValue('AC2', "Complete RPOC for 2nd Visit\n\n1 - Yes\n0 - No");
        $mc('AD2:AE5', "Service Location\n\nA - Facility\nB - Non-Facility");
        $sheet->setCellValue('AF2', 'Remarks');

        // Row 3 — sub-section labels for the RPOC 1yr+ span
        $mc('R3:S3', 'Oral screening');
        $mc('T3:U3', 'Risk assessment');
        $mc('V3:W3', 'Oral prophylaxis');
        $mc('X3:Y3', 'Fluoride varnish application');
        $mc('Z3:AA3', 'Counseling');

        // Row 4 — leaf headers
        $sheet->setCellValue('I4', 'Oral screening');
        $sheet->setCellValue('J4', 'Risk assessment');
        $sheet->setCellValue('K4', 'Oral hygiene instruction');
        $sheet->setCellValue('L4', 'Counseling');
        $sheet->setCellValue('M4', "Fluoride varnish application\n(for 9-11 months only)");
        $sheet->setCellValue('R4', "1st\n(mm/dd/yy)");
        $sheet->setCellValue('S4', "2nd\n(mm/dd/yy)\n\nat least 4 months interval from the 1st visit");
        $sheet->setCellValue('T4', "1st\n(mm/dd/yy)");
        $sheet->setCellValue('U4', "2nd\n(mm/dd/yy)\n\nat least 4 months interval from the 1st visit");
        $sheet->setCellValue('V4', "1st\n(mm/dd/yy)");
        $sheet->setCellValue('W4', "2nd\n(mm/dd/yy)\n\nat least 4 months interval from the 1st visit");
        $sheet->setCellValue('X4', "1st\n(mm/dd/yy)");
        $sheet->setCellValue('Y4', "2nd\n(mm/dd/yy)\n\nat least 4 months interval from the 1st visit");
        $sheet->setCellValue('Z4', "1st\n(mm/dd/yy)");
        $sheet->setCellValue('AA4', "2nd\n(mm/dd/yy)\n\nat least 4 months interval from the 1st visit");
        foreach (['I4','J4','K4','L4','M4','R4','S4','T4','U4','V4','W4','X4','Y4','Z4','AA4'] as $c) {
            $sheet->mergeCells("{$c}:" . preg_replace('/4$/', '6', $c));
        }

        // Row 6 — visit labels under the merged I2:M3 RPOC block columns
        $sheet->setCellValue('P6', '1st Visit');
        $sheet->setCellValue('Q6', '2nd Visit');
        $sheet->setCellValue('AD6', '1st Visit');
        $sheet->setCellValue('AE6', '2nd Visit');

        $sheet->getStyle('A2:AF6')->applyFromArray($hStyle);
        $sheet->getRowDimension(2)->setRowHeight(26);
        $sheet->getRowDimension(3)->setRowHeight(13);
        $sheet->getRowDimension(4)->setRowHeight(39);
        $sheet->getRowDimension(5)->setRowHeight(51);
        $sheet->getRowDimension(6)->setRowHeight(17);

        /*
        |--------------------------------------------------------------------------
        | 3. DATA ROWS (1 row per record)
        |--------------------------------------------------------------------------
        */
        $currentRow = 7;
        $totalRows  = max($records->count(), 10);

        for ($idx = 1; $idx <= $totalRows; $idx++) {
            $rec = $records->get($idx - 1);

            $sheet->setCellValue("A{$currentRow}", $idx);

            if ($rec) {
                $sheet->setCellValue("B{$currentRow}", $fmt($rec->date_of_visit));
                $sheet->setCellValue("C{$currentRow}", $rec->family_serial ?? '');
                $sheet->setCellValue("D{$currentRow}", strtoupper($rec->name ?? ''));
                $sheet->setCellValue("E{$currentRow}", $rec->address ?? '');
                $sheet->setCellValue("F{$currentRow}", $fmt($rec->date_of_birth));
                $sheet->setCellValue("G{$currentRow}", $rec->age_months ?? '');
                $sheet->setCellValue("H{$currentRow}", $rec->sex ?? '');

                // RPOC for 1st Dental Visit (0-11 months)
                $sheet->setCellValue("I{$currentRow}", $b1($rec->rpoc0_oral_screening));
                $sheet->setCellValue("J{$currentRow}", $b1($rec->rpoc0_risk_assessment));
                $sheet->setCellValue("K{$currentRow}", $b1($rec->rpoc0_oral_hygiene));
                $sheet->setCellValue("L{$currentRow}", $b1($rec->rpoc0_counseling));
                $sheet->setCellValue("M{$currentRow}", $b1($rec->rpoc0_fluoride_varnish));
                $sheet->setCellValue("N{$currentRow}", $rec->complete_rpoc0 ?? '');

                $sheet->setCellValue("O{$currentRow}", $rec->age_years ?? '');
                $sheet->setCellValue("P{$currentRow}", $rec->age_group1st ?? '');
                $sheet->setCellValue("Q{$currentRow}", $rec->age_group2nd ?? '');

                $sheet->setCellValue("R{$currentRow}", $fmt($rec->oral_screening1st));
                $sheet->setCellValue("S{$currentRow}", $fmt($rec->oral_screening2nd));
                $sheet->setCellValue("T{$currentRow}", $fmt($rec->risk_assessment1st));
                $sheet->setCellValue("U{$currentRow}", $fmt($rec->risk_assessment2nd));
                $sheet->setCellValue("V{$currentRow}", $fmt($rec->oral_prophylaxis1st));
                $sheet->setCellValue("W{$currentRow}", $fmt($rec->oral_prophylaxis2nd));
                $sheet->setCellValue("X{$currentRow}", $fmt($rec->fluoride_varnish1st));
                $sheet->setCellValue("Y{$currentRow}", $fmt($rec->fluoride_varnish2nd));
                $sheet->setCellValue("Z{$currentRow}", $fmt($rec->counseling1st));
                $sheet->setCellValue("AA{$currentRow}", $fmt($rec->counseling2nd));

                $sheet->setCellValue("AB{$currentRow}", $rec->complete_rpoc1st ?? '');
                $sheet->setCellValue("AC{$currentRow}", $rec->complete_rpoc2nd ?? '');
                $sheet->setCellValue("AD{$currentRow}", $rec->service_location1st ?? '');
                $sheet->setCellValue("AE{$currentRow}", $rec->service_location2nd ?? '');

                $sheet->setCellValue("AF{$currentRow}", $rec->remarks ?? '');
            }

            $sheet->getRowDimension($currentRow)->setRowHeight(20);
            $currentRow++;
        }

        /*
        |--------------------------------------------------------------------------
        | 4. BORDERS, WIDTHS, ALIGNMENT
        |--------------------------------------------------------------------------
        */
        $lastRow = $currentRow - 1;
        $sheet->getStyle("A2:AF{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A7:AF{$lastRow}")->applyFromArray([
            'font'      => ['size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
        ]);
        $sheet->getStyle("D7:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("E7:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("AF7:AF{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        foreach ([
            'A'=>6,'B'=>13,'C'=>13,'D'=>31,'E'=>13,'F'=>10,'G'=>7,'H'=>10,
            'I'=>8,'J'=>10,'K'=>10,'L'=>10,'M'=>13,'N'=>16,'O'=>8,'P'=>11,'Q'=>11,
            'R'=>10,'S'=>13,'T'=>9,'U'=>13,'V'=>11,'W'=>13,'X'=>10,'Y'=>13,'Z'=>10,'AA'=>13,
            'AB'=>8,'AC'=>13,'AD'=>12,'AE'=>13,'AF'=>28,
        ] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. STREAM FILE
        |--------------------------------------------------------------------------
        */
        $filename = "TCL_ORAL_HEALTH_CARE_{$year}_{$barangay}.xlsx";
        $savePath = storage_path("app/{$filename}");
        (new Xlsx($spreadsheet))->save($savePath);

        return response()->download($savePath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Download Target Client List (TCL) for PhilPEN Risk Assessment
     * Layout mirrors PhilPEN_Risk_Assessment.xlsx — sheet: TCL_PhilPEN
     *
     * Header structure : row 1 (title), rows 2-6 (column headers, multi-tier with
     *                     12-month medication grids for Hypertension and Diabetes)
     * Data structure    : 2 rows per record (row-1 = main values / row-2 = #-counts for meds)
     * Total columns     : CT (98)
     */
    public function philPENRiskAssessmentDownload(Request $request)
    {
        $year     = $request->input('year', date('Y'));
        $barangay = $request->input('barangay', 'All');

        $records = DB::table('philpen_risk_assessments')
            ->when($barangay !== 'All', function ($q) use ($barangay) {
                $q->join('household_profiles', 'philpen_risk_assessments.profile_id', '=', 'household_profiles.id')
                  ->where('household_profiles.barangay', $barangay)
                  ->select('philpen_risk_assessments.*');
            })
            ->whereYear('philpen_risk_assessments.created_at', $year)
            ->orderBy('philpen_risk_assessments.created_at')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('TCL_PhilPEN');
        $sheet->setShowGridlines(true);

        $FILL_H = 'CFE2F3';

        $hStyle = [
            'font'      => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
            'fill'      => ['fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $FILL_H]],
        ];

        $mc  = fn(string $range, $val) => $sheet->mergeCells($range)
            && $sheet->setCellValue(explode(':', $range)[0], $val);
        $fmt = fn($d) => (!empty($d) && strtotime($d)) ? date('m/d/Y', strtotime($d)) : '';

        /*
        |--------------------------------------------------------------------------
        | 1. TITLE ROW
        |--------------------------------------------------------------------------
        */
        $mc('A1:BI1', 'Target Client List for PhilPEN Risk Assessment');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 22, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(32);

        /*
        |--------------------------------------------------------------------------
        | 2. COLUMN HEADERS (rows 2–6)
        |
        | A-I   Patient core (merged 2-6)
        | J-T   Risk Factor Assessment span (J2:T2)
        | U-BH  Hypertension span (U2:BH2) — Screening + 12-month meds grid
        | BI-CT Type 2 Diabetes Mellitus span (BI2:CT2) — Result + 12-month meds grid
        |--------------------------------------------------------------------------
        */
        foreach (['A2:A6','B2:B6','C2:C6','D2:D6','E2:E6','F2:F6','G2:G6','H2:H6','I2:I6'] as $r) {
            $sheet->mergeCells($r);
        }
        $sheet->setCellValue('A2', 'No.');
        $sheet->setCellValue('B2', "Date of Assessment\n(mm/dd/yy)");
        $sheet->setCellValue('C2', 'Family Serial Number');
        $sheet->setCellValue('D2', "Name\n(LastName, FullName, MI)");
        $sheet->setCellValue('E2', 'Complete Adress');
        $sheet->setCellValue('F2', "Date of Birth\n(mm/dd/yy)");
        $sheet->setCellValue('G2', "Age\n(in years)");
        $sheet->setCellValue('H2', "Age Group\n\nA - 20-59 years old\n B - 60 years old and above");
        $sheet->setCellValue('I2', "Sex\n\nM - Male\nF- Female");

        $mc('J2:T2', 'Risk Factor Assessment');
        $mc('U2:BH2', 'Screening and Medicine Provision');
        $mc('BI2:CT2', 'Screening and Medicine Provision');

        // Row 3 — Risk Factor Assessment sub-headers
        $mc('J3:J6', "Current Smokers\n \nRefers to any person who smoked the following product at least once for the past year\n\n3- Both\n2 - Vaporized Nicotine Products\n1 - Tobacco Product\n 0 - No");
        $mc('K3:K5', 'ASK');
        $mc('L3:L5', 'ADVISE');
        $mc('M3:M5', 'ASSESS');
        $mc('N3:N5', 'ASSIST');
        $mc('O3:O5', 'ARRANGE');
        $mc('P3:P6', "Provided Brief Tobacco Intervention\n(If Current Smoker is either 1, 2 or 3 and ALL 5A's are 1)\n\n1 - Yes\n0 - No");
        $mc('Q3:Q6', "Binge Alcohol Drinker\n \nBinge Drinkers are those who drink whether habitual or not in the past year:\n\nMale: 5 or more standard drinks in a row (in 1 day) \nFemale: 4 or more standard drinks in a row (in 1 day)\n\n1 - Yes\n 0 - No");
        $mc('R3:R6', "Insufficient Physical Activity\n \n If not achieving Moderate or Vigorous activity for atleast 150 minutes in a week, spread throughout the week with no more than 2 days apart\n\n1 - Yes\n 0 - No");
        $mc('S3:S6', "Consumed Unhealthy Diet\n \n Unhealthy diet: If the intake for fruits and vegetables are below 400g (five servings of fruits and vegetables per day)\n\n1 - Yes\n 0 - No");
        $mc('T3:T6', "Overweight/Obese\n\nNormal:\n 18.5 - 22.9\n Overweight:\n 23.0 - 24.9\n Obese:\n > 25.0\n\n\n 0 - Normal\n1 - Overweight\n2 - Obese\n\n\n");
        $mc('K6:O6', "1 - Yes\n0 - No");

        // Row 3 — Hypertension / Diabetes section labels
        $mc('U3:BH3', 'Hypertension');
        $mc('BI3:CT3', 'Type 2 Diabetes Mellitus');

        // Row 4 — Hypertension screening + meds sub-headers
        $mc('U4:V6', "Completed Screening\n (mm/dd/yy)\n\nFor known hypertensive: Input date of 1st reading\n For newly diagnosed: Input date of 1st reading on 1st row and 2nd reading on 2nd row\n \nWith BP taken in two (2) readings (average) per day on two (2) separate days within the week at least three (3) days apart\n        ");
        $mc('W4:W6', "Result\n\n1 : >140 mmHg systolic and/or >90 mmHg diastolic\n  0 : < 140/90 mmHg");
        $mc('X4:X6', "Number of Medications Needed per Month\n(for multidrug therapy)\n\n1st row - Initial needs\n2nd row - Change in prescription #");
        $mc('Y4:BH4', "Antihypertensive Medication\n\n#: Number of Tablets");

        // Row 4 — Diabetes result + meds sub-headers
        $mc('BI4:BI6', "Result\n\n1 - Fasting Blood Sugar ≥ 126mg/dL (7.0 mmol/L) - fasting of 8-14 hours OR\n     Random Blood Sugar ≥ 200mg/dL (11.1 mmol/L) with classic symptoms of diabetes OR\nHBA1c ≥ 6.5%\n\n0 - Negative");
        $mc('BJ4:CS4', "Antidiabetic Medication\n\n#: Number of Tablets");
        $mc('CT4:CT6', 'Remarks');

        // Row 5 — monthly spans for Hypertension meds (Y-BH, 12 months x 3 cols: PBF/OOP/Both)
        $htMonths  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $htStart   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString('Y');
        foreach ($htMonths as $i => $monthName) {
            $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($htStart + $i * 3);
            $endCol   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($htStart + $i * 3 + 2);
            $mc("{$startCol}5:{$endCol}5", "{$monthName}\nCheck Both if PBF is 60% and above");
            $sheet->setCellValue($startCol . '6', 'PBF');
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($htStart + $i * 3 + 1) . '6', 'OOP');
            $sheet->setCellValue($endCol . '6', 'Both');
        }

        // Row 5 — monthly spans for Diabetes meds (BJ-CS, 12 months x 3 cols: PBF/OOP/Both)
        $dmStart = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString('BJ');
        foreach ($htMonths as $i => $monthName) {
            $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dmStart + $i * 3);
            $endCol   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dmStart + $i * 3 + 2);
            $mc("{$startCol}5:{$endCol}5", "{$monthName}\nCheck Both if PBF is 60% and above");
            $sheet->setCellValue($startCol . '6', 'PBF');
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dmStart + $i * 3 + 1) . '6', 'OOP');
            $sheet->setCellValue($endCol . '6', 'Both');
        }

        $sheet->getStyle('A2:CT6')->applyFromArray($hStyle);
        $sheet->getRowDimension(2)->setRowHeight(32);
        $sheet->getRowDimension(3)->setRowHeight(16);
        $sheet->getRowDimension(4)->setRowHeight(56);
        $sheet->getRowDimension(5)->setRowHeight(80);
        $sheet->getRowDimension(6)->setRowHeight(21);

        /*
        |--------------------------------------------------------------------------
        | 3. DATA ROWS (2 rows per record: row-1 = main values, row-2 = monthly #: counts)
        |--------------------------------------------------------------------------
        */
        // Static columns merged across both rows
        $staticMergeCols = ['A','B','C','D','E','F','G','H','I',
                             'J','K','L','M','N','O','P','Q','R','S','T',
                             'U','V','W','X','BI','CT'];

        $currentRow = 7;
        $totalRows  = max($records->count(), 10);

        for ($idx = 1; $idx <= $totalRows; $idx++) {
            $rec = $records->get($idx - 1);
            $r1  = $currentRow;
            $r2  = $currentRow + 1;

            foreach ($staticMergeCols as $col) {
                $sheet->mergeCells("{$col}{$r1}:{$col}{$r2}");
            }

            $sheet->setCellValue("A{$r1}", $idx);

            // Monthly medication grids default to '#:' placeholders unless populated below
            foreach (range(0, 11) as $i) {
                $htCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($htStart + $i * 3);
                $dmCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dmStart + $i * 3);
                $sheet->setCellValue("{$htCol}{$r1}", '#:');
                $sheet->setCellValue("{$dmCol}{$r1}", '#:');
            }

            if ($rec) {
                $sheet->setCellValue("B{$r1}", $fmt($rec->date_assessment));
                $sheet->setCellValue("C{$r1}", $rec->family_serial ?? '');
                $sheet->setCellValue("D{$r1}", strtoupper($rec->name ?? ''));
                $sheet->setCellValue("E{$r1}", $rec->address ?? '');
                $sheet->setCellValue("F{$r1}", $fmt($rec->date_of_birth));
                $sheet->setCellValue("G{$r1}", $rec->age ?? '');
                $sheet->setCellValue("H{$r1}", $rec->age_group ?? '');
                $sheet->setCellValue("I{$r1}", $rec->sex ?? '');

                $sheet->setCellValue("J{$r1}", $rec->current_smoker ?? '');
                $sheet->setCellValue("K{$r1}", $rec->bti_ask ?? '');
                $sheet->setCellValue("L{$r1}", $rec->bti_advise ?? '');
                $sheet->setCellValue("M{$r1}", $rec->bti_assess ?? '');
                $sheet->setCellValue("N{$r1}", $rec->bti_assist ?? '');
                $sheet->setCellValue("O{$r1}", $rec->bti_arrange ?? '');
                $sheet->setCellValue("P{$r1}", $rec->provided_bti ?? '');
                $sheet->setCellValue("Q{$r1}", $rec->binge_alcohol ?? '');
                $sheet->setCellValue("R{$r1}", $rec->insufficient_pa ?? '');
                $sheet->setCellValue("S{$r1}", $rec->unhealthy_diet ?? '');
                $sheet->setCellValue("T{$r1}", $rec->bmi_category ?? '');

                // Hypertension: U-V (screening dates, row1/row2), W (result), X (meds needed)
                $sheet->setCellValue("U{$r1}", $fmt($rec->screening_date1));
                $sheet->setCellValue("U{$r2}", $fmt($rec->screening_date2));
                $sheet->setCellValue("V{$r1}", ($rec->bp_systolic1 || $rec->bp_diastolic1) ? "{$rec->bp_systolic1}/{$rec->bp_diastolic1}" : '');
                $sheet->setCellValue("V{$r2}", ($rec->bp_systolic2 || $rec->bp_diastolic2) ? "{$rec->bp_systolic2}/{$rec->bp_diastolic2}" : '');
                $sheet->setCellValue("W{$r1}", $rec->hypertension_result ?? '');
                $sheet->setCellValue("X{$r1}", $rec->meds_initial ?? '');
                $sheet->setCellValue("X{$r2}", $rec->meds_changed ?? '');

                // Diabetes result
                $sheet->setCellValue("BI{$r1}", '');

                // Monthly medication grids — populate from monthly_meds JSON
                // Expected structure: { "hypertension": {"Jan": {"pbf":bool,"oop":bool,"both":bool}, ...},
                //                       "diabetes":     {"Jan": {...}, ...} }
                $monthly = $rec->monthly_meds ?? null;
                $monthlyArr = is_string($monthly) ? (json_decode($monthly, true) ?? []) : ($monthly ?? []);
                $htData = $monthlyArr['hypertension'] ?? [];
                $dmData = $monthlyArr['diabetes'] ?? [];

                foreach ($htMonths as $i => $monthName) {
                    $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($htStart + $i * 3);
                    $oopCol   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($htStart + $i * 3 + 1);
                    $bothCol  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($htStart + $i * 3 + 2);
                    $entry    = $htData[$monthName] ?? null;
                    $sheet->setCellValue("{$startCol}{$r1}", '#: ' . ($entry['pbf_count']  ?? ''));
                    $sheet->setCellValue("{$oopCol}{$r1}",   $entry && !empty($entry['oop'])  ? '✔' : '');
                    $sheet->setCellValue("{$bothCol}{$r1}",  $entry && !empty($entry['both']) ? '✔' : '');
                }
                foreach ($htMonths as $i => $monthName) {
                    $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dmStart + $i * 3);
                    $oopCol   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dmStart + $i * 3 + 1);
                    $bothCol  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dmStart + $i * 3 + 2);
                    $entry    = $dmData[$monthName] ?? null;
                    $sheet->setCellValue("{$startCol}{$r1}", '#: ' . ($entry['pbf_count']  ?? ''));
                    $sheet->setCellValue("{$oopCol}{$r1}",   $entry && !empty($entry['oop'])  ? '✔' : '');
                    $sheet->setCellValue("{$bothCol}{$r1}",  $entry && !empty($entry['both']) ? '✔' : '');
                }

                $sheet->setCellValue("CT{$r1}", '');
            }

            $sheet->getRowDimension($r1)->setRowHeight(18);
            $sheet->getRowDimension($r2)->setRowHeight(15);
            $currentRow += 2;
        }

        /*
        |--------------------------------------------------------------------------
        | 4. BORDERS, WIDTHS, ALIGNMENT
        |--------------------------------------------------------------------------
        */
        $lastRow = $currentRow - 1;
        $sheet->getStyle("A2:CT{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A7:CT{$lastRow}")->applyFromArray([
            'font'      => ['size' => 8, 'name' => 'Arial'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
        ]);
        $sheet->getStyle("D7:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("E7:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("CT7:CT{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $colWidths = [
            'A'=>6,'B'=>13,'C'=>13,'D'=>28,'E'=>13,'F'=>13,'G'=>13,'H'=>13,'I'=>13,
            'J'=>17,'K'=>4,'L'=>13,'M'=>13,'N'=>13,'O'=>13,'P'=>22,'Q'=>13,
            'R'=>21,'S'=>22,'T'=>16,'U'=>13,'V'=>13,'W'=>17,'X'=>18,
            'BI'=>18,'CT'=>14,
        ];
        foreach ($colWidths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
        // Monthly medication columns: narrow #: / OOP / Both pattern
        foreach (range(0, 11) as $i) {
            $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($htStart + $i * 3);
            $oopCol   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($htStart + $i * 3 + 1);
            $bothCol  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($htStart + $i * 3 + 2);
            $sheet->getColumnDimension($startCol)->setWidth(9);
            $sheet->getColumnDimension($oopCol)->setWidth(13);
            $sheet->getColumnDimension($bothCol)->setWidth(4);

            $dStartCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dmStart + $i * 3);
            $dOopCol   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dmStart + $i * 3 + 1);
            $dBothCol  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dmStart + $i * 3 + 2);
            $sheet->getColumnDimension($dStartCol)->setWidth(7);
            $sheet->getColumnDimension($dOopCol)->setWidth(13);
            $sheet->getColumnDimension($dBothCol)->setWidth(4);
        }

        /*
        |--------------------------------------------------------------------------
        | 5. STREAM FILE
        |--------------------------------------------------------------------------
        */
        $filename = "TCL_PHILPEN_RISK_ASSESSMENT_{$year}_{$barangay}.xlsx";
        $savePath = storage_path("app/{$filename}");
        (new Xlsx($spreadsheet))->save($savePath);

        return response()->download($savePath, $filename)->deleteFileAfterSend(true);
    }
}