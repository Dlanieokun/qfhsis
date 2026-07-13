<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\HouseholdProfile;
use App\Models\FamilyPlanningRecord;
use App\Models\FamilyPlanningDropOut;
use App\Models\MaternalCareRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PhoReportController extends Controller
{
    public function familyPlaning(Request $request)
    {
        // 1. Setup Configuration
        $methodDB = [
            'BTL = Bilateral Tubal Ligation'           => 'btl',
            'NSV = No-Scalpel Vasectomy'               => 'nsv',
            'CON = Condom'                             => 'condom',
            'Pills-POP = Progestin Only Pills'         => 'pills-pop',
            'Pills-COC = Combined Oral Contraceptives' => 'pills-coc',
            'INJ = DMPA (Injectables)'                 => 'injectable',
            'IMP-I = Implant (Interval)'               => 'implant-interval',
            'IMP-PP = Implant (Postpartum)'            => 'implant-pp',
            'IUD-I = IUD Interval'                     => 'iud-interval',
            'IUD-PP = IUD Postpartum'                  => 'iud-pp',
            'NFP-LAM = Lactational Amenorrhea Method'  => 'lam',
            'NFP-BBT = Basal Body Temperature'         => 'bbt',
            'NFP-CMM = Cervical Mucus Method'          => 'cmm',
            'NFP-STM = Sympto-Thermal Method'          => 'stm',
            'NFP-SDM = Standard Days Method'           => 'sdm',
        ];

        $bracketsDB = [
            'A - 10-14 years old' => '10-14',
            'B - 15-19 years old' => '15-19',
            'C - 20-49 years old' => '20-49',
        ];

        $empty = ['10-14' => 0, '15-19' => 0, '20-49' => 0, 'total' => 0];
        $methods = array_unique(array_values($methodDB));

        $newAcceptorsPrevMonth = $otherAcceptorsPresent = $newAcceptorsPresent =
        $currentUsersBOM = $currentUsersEOM = $dropOutsPresent = [];

        foreach ($methods as $m) {
            $newAcceptorsPrevMonth[$m] = $otherAcceptorsPresent[$m] = $newAcceptorsPresent[$m] =
            $currentUsersBOM[$m] = $currentUsersEOM[$m] = $dropOutsPresent[$m] = $empty;
        }

        // 2. Time Period Parameters
        $month = (int) $request->input('month', now()->month);
        $year  = (int) $request->input('year', now()->year);
        $selectedMonth   = Carbon::create($year, $month, 1);
        $previousMonth   = $selectedMonth->copy()->subMonth();
        $startOfSelected = $selectedMonth->copy()->startOfMonth();
        $endOfSelected   = $selectedMonth->copy()->endOfMonth();

        // 3. Location Filter Parameters
        $location = $this->resolveLocationFilters($request);

        // 4. Fetch and Process, scoped to the household profile's location
        $familyData = FamilyPlanningRecord::with(['followUps', 'dropOuts', 'householdProfile'])
            ->whereHas('householdProfile', function ($q) use ($location) {
                $this->applyHouseholdLocationFilter($q, $location);
            })
            ->get();

        foreach ($familyData as $item) {
            if (!isset($methodDB[$item->methodUsed]) || !isset($bracketsDB[$item->ageGroupCategory])) continue;

            $method  = $methodDB[$item->methodUsed];
            $age     = $bracketsDB[$item->ageGroupCategory];
            $regDate = Carbon::parse($item->registrationDate);

            // Earliest drop-out on/before the end of the selected month
            $dropoutAtEom = $item->dropOuts
                ->map(fn ($d) => Carbon::parse($d->dropOutDate))
                ->filter(fn ($d) => $d->lessThanOrEqualTo($endOfSelected))
                ->sort()
                ->first();
            $isDropoutEom = $dropoutAtEom !== null;

            // Earliest drop-out strictly before the start of the selected month
            $dropoutAtBom = $item->dropOuts
                ->map(fn ($d) => Carbon::parse($d->dropOutDate))
                ->filter(fn ($d) => $d->lessThan($startOfSelected))
                ->sort()
                ->first();
            $isDropoutBom = $dropoutAtBom !== null;

            if ($isDropoutEom) {
                $dropOutsPresent[$method][$age]++;
                $dropOutsPresent[$method]['total']++;
            }

            // Current User - End of Month: registered on/before EOM and not dropped out by EOM
            if ($regDate->lessThanOrEqualTo($endOfSelected) && !$isDropoutEom) {
                $currentUsersEOM[$method][$age]++;
                $currentUsersEOM[$method]['total']++;
            }

            // Current User - Beginning of Month: registered before BOM and not dropped out before BOM
            if ($regDate->lessThan($startOfSelected) && !$isDropoutBom) {
                $currentUsersBOM[$method][$age]++;
                $currentUsersBOM[$method]['total']++;
            }

            // Logic for New/Other Acceptors
            if ($regDate->isSameMonth($selectedMonth)) {
                $newAcceptorsPresent[$method][$age]++;
                $newAcceptorsPresent[$method]['total']++;
            } elseif ($regDate->isSameMonth($previousMonth)) {
                $newAcceptorsPrevMonth[$method][$age]++;
                $newAcceptorsPrevMonth[$method]['total']++;
            } elseif ($regDate->lessThan($previousMonth->copy()->startOfMonth())) {
                $otherAcceptorsPresent[$method][$age]++;
                $otherAcceptorsPresent[$method]['total']++;
            }
        }

        // --- Reconcile BOM/EOM current users from the acceptor/drop-out ledgers ---
        foreach ($methods as $method) {
            foreach (['10-14', '15-19', '20-49'] as $age) {
                // BOM: Previous + Other Acceptors (NO present-month acceptors)
                $currentUsersBOM[$method][$age] = max(
                    0,
                    $newAcceptorsPrevMonth[$method][$age]
                    + $otherAcceptorsPresent[$method][$age]
                );

                // EOM: Previous + Other + Present Acceptors - Present Dropouts
                $currentUsersEOM[$method][$age] = max(
                    0,
                    $newAcceptorsPrevMonth[$method][$age]
                    + $otherAcceptorsPresent[$method][$age]
                    + $newAcceptorsPresent[$method][$age]
                    - $dropOutsPresent[$method][$age]
                );
            }

            $currentUsersBOM[$method]['total'] =
                $currentUsersBOM[$method]['10-14']
                + $currentUsersBOM[$method]['15-19']
                + $currentUsersBOM[$method]['20-49'];

            $currentUsersEOM[$method]['total'] =
                $currentUsersEOM[$method]['10-14']
                + $currentUsersEOM[$method]['15-19']
                + $currentUsersEOM[$method]['20-49'];
        }

        // Demand satisfied = aggregate of all current (EOM) modern-method users across brackets
        $demandSatisfied = $empty;
        foreach ($currentUsersEOM as $counts) {
            $demandSatisfied['10-14'] += $counts['10-14'];
            $demandSatisfied['15-19'] += $counts['15-19'];
            $demandSatisfied['20-49'] += $counts['20-49'];
            $demandSatisfied['total'] += $counts['total'];
        }

        return response()->json([
            'status' => 'success',
            'period' => ['month' => $selectedMonth->format('F'), 'year' => $selectedMonth->year],
            'filters' => $location['codes'],
            'data' => [
                'demandSatisfied'              => $demandSatisfied,
                'currentUsersByMethod'         => $currentUsersEOM,
                'currentUsersBeginningOfMonth' => $currentUsersBOM,
                'newAcceptorsPreviousMonth'    => $newAcceptorsPrevMonth,
                'otherAcceptorsPresentMonth'   => $otherAcceptorsPresent,
                'newAcceptorsPresentMonth'     => $newAcceptorsPresent,
                'dropOutsPresentMonth'         => $dropOutsPresent,
            ]
        ]);
    }

    /**
     * SECTION B. MATERNAL CARE AND SERVICES
     *
     * Builds the prenatal / intrapartum / postpartum indicator sets consumed by
     * M1AllPrograms.tsx's SectionB, scoped to the selected reporting month and
     * (optionally) region/province/municipality/barangay.
     */
    public function maternalCare(Request $request)
    {
        // 1. Time Period Parameters — women are counted in the month they registered
        $month = (int) $request->input('month', now()->month);
        $year  = (int) $request->input('year', now()->year);
        $selectedMonth   = Carbon::create($year, $month, 1);
        $startOfSelected = $selectedMonth->copy()->startOfMonth();
        $endOfSelected   = $selectedMonth->copy()->endOfMonth();

        // 2. Location Filter Parameters
        $location = $this->resolveLocationFilters($request);

        // 3. Bucket Templates
        $ageBracketEmpty = ['10-14' => 0, '15-19' => 0, '20-49' => 0, 'total' => 0];
        $sexBracketEmpty = ['male' => 0, 'female' => 0, 'total' => 0];

        $prenatalKeys = [
            'anc8Completed', 'nutritionAssessed', 'nutritionNormal', 'nutritionLow', 'nutritionHigh',
            'td2PlusFirstPregnancy', 'td2Plus',
            'ifaCompleted', 'mmCompleted', 'ccCompleted',
            'anemiaScreened', 'anemiaDiagnosed',
            'gdmScreened', 'gdmDiagnosed',
            'dewormed',
            'bpMeasured', 'highBpOrDanger', 'referred',
        ];
        $prenatal = array_fill_keys($prenatalKeys, null);
        foreach ($prenatalKeys as $k) {
            $prenatal[$k] = $ageBracketEmpty;
        }

        $intrapartumKeys = [
            'totalDeliveries',
            'attendantPhysician', 'attendantNurse', 'attendantMidwife',
            'facilityPublic', 'facilityPrivate',
            'deliveryVaginal', 'deliveryCesarean', 'deliveryCombined',
            'outcomeFullTerm', 'outcomePreTerm', 'outcomeFetalDeath', 'outcomeAbortion',
            'birthWeightNormal', 'birthWeightLow', 'birthWeightUnknown',
        ];
        $intrapartum = [];
        foreach ($intrapartumKeys as $k) {
            $intrapartum[$k] = $sexBracketEmpty;
        }

        $postpartumKeys = ['pnc4Completed', 'ifaCompleted', 'vitACompleted', 'bpMeasured', 'highBpOrDanger', 'referred'];
        $postpartum = [];
        foreach ($postpartumKeys as $k) {
            $postpartum[$k] = $ageBracketEmpty;
        }

        // 4. Fetch maternal care records with all related sub-records, scoped to location + month
        $records = MaternalCareRecord::with([
                'householdProfile',
                'prenatal8Anc',
                'prenatalImmunization',
                'prenatalLabScreening',
                'prenatalSupplementation',
                'intrapartum',
                'postpartum',
            ])
            ->whereHas('householdProfile', function ($q) use ($location) {
                $this->applyHouseholdLocationFilter($q, $location);
            })
            ->get()
            ->filter(function ($record) use ($startOfSelected, $endOfSelected) {
                if (empty($record->registrationDate)) {
                    return false;
                }
                try {
                    return Carbon::parse($record->registrationDate)->between($startOfSelected, $endOfSelected);
                } catch (\Exception $e) {
                    return false;
                }
            });

        foreach ($records as $record) {
            $bracket = $this->ageBracket($record->age !== null ? (int) $record->age : null);
            if (!$bracket) {
                continue; // outside the tracked 10-49 WRA range
            }

            $bump = function (array &$bucket, string $key) use ($bracket) {
                $bucket[$key][$bracket]++;
                $bucket[$key]['total']++;
            };

            // ── Nutritional Status (from MaternalCareRecord.bmiStatus) ──────────
            if (!empty($record->bmiStatus)) {
                $bump($prenatal, 'nutritionAssessed');
                if ($this->contains($record->bmiStatus, 'normal')) {
                    $bump($prenatal, 'nutritionNormal');
                } elseif ($this->contains($record->bmiStatus, 'low') || $this->contains($record->bmiStatus, 'under')) {
                    $bump($prenatal, 'nutritionLow');
                } elseif ($this->contains($record->bmiStatus, 'high') || $this->contains($record->bmiStatus, 'over') || $this->contains($record->bmiStatus, 'obese')) {
                    $bump($prenatal, 'nutritionHigh');
                }
            }

            // ── Td-Containing Vaccination (prenatal_immunization_records + gravidaPara parity) ──
            if ($imm = $record->prenatalImmunization) {
                $tdDosesGiven = collect([$imm->td1Date, $imm->td2Date, $imm->td3Date, $imm->td4Date, $imm->td5Date])
                    ->filter(fn ($d) => !empty($d))
                    ->count();

                preg_match('/G\s*(\d+)/i', (string) $record->gravidaPara, $gMatch);
                $gravida = isset($gMatch[1]) ? (int) $gMatch[1] : null;

                if ($gravida === 1 && $tdDosesGiven >= 2) {
                    $bump($prenatal, 'td2PlusFirstPregnancy');
                } elseif ($gravida !== null && $gravida >= 2 && $tdDosesGiven >= 3) {
                    $bump($prenatal, 'td2Plus');
                }
            }

            // ── 8ANC completion + BP / danger signs / referral (prenatal_8anc_records) ──
            if ($anc = $record->prenatal8Anc) {
                if ($this->truthy($anc->completed8Anc)) {
                    $bump($prenatal, 'anc8Completed');
                }

                $bpTaken = false;
                for ($i = 1; $i <= 8; $i++) {
                    if (!empty($anc->{"visit{$i}Bp"})) {
                        $bpTaken = true;
                        break;
                    }
                }
                if ($bpTaken) {
                    $bump($prenatal, 'bpMeasured');
                }

                if ($this->truthy($anc->highBp) || $this->truthy($anc->dangerSigns)) {
                    $bump($prenatal, 'highBpOrDanger');
                }
                if ($this->truthy($anc->highBpReferred)) {
                    $bump($prenatal, 'referred');
                }
            }

            // ── Lab Screening: Anemia (CBC) + Gestational Diabetes (prenatal_lab_screening_records) ──
            if ($lab = $record->prenatalLabScreening) {
                if (!empty($lab->cbcDate)) {
                    $bump($prenatal, 'anemiaScreened');
                    if ($this->contains($lab->cbcResult, 'anemi') || $this->contains($lab->cbcResult, 'low')) {
                        $bump($prenatal, 'anemiaDiagnosed');
                    }
                }
                if (!empty($lab->gdmDate)) {
                    $bump($prenatal, 'gdmScreened');
                    if ($this->contains($lab->gdmResult, 'positive') || $this->contains($lab->gdmResult, 'gdm')) {
                        $bump($prenatal, 'gdmDiagnosed');
                    }
                }
            }

            // ── Supplementation + Deworming (prenatal_supplementation_records) ──
            if ($supp = $record->prenatalSupplementation) {
                if ($this->truthy($supp->completed_ifa)) {
                    $bump($prenatal, 'ifaCompleted');
                }
                if ($this->truthy($supp->completed_mm)) {
                    $bump($prenatal, 'mmCompleted');
                }
                if ($this->truthy($supp->completed_cc)) {
                    $bump($prenatal, 'ccCompleted');
                }
                if ($this->truthy($supp->received_deworming)) {
                    $bump($prenatal, 'dewormed');
                }
            }

            // ── Postpartum Care (postpartum_records) ─────────────────────────
            if ($pnc = $record->postpartum) {
                $visitsCompleted = collect([$pnc->visit24hDate, $pnc->visit1wDate, $pnc->visit2_4wDate, $pnc->visit4_6wDate])
                    ->filter(fn ($d) => !empty($d))
                    ->count();
                if ($visitsCompleted >= 4) {
                    $bump($postpartum, 'pnc4Completed');
                }
                if ($this->truthy($pnc->completedIfa)) {
                    $bump($postpartum, 'ifaCompleted');
                }
                if ($this->truthy($pnc->completedVitA)) {
                    $bump($postpartum, 'vitACompleted');
                }

                $bpTakenPnc = collect([$pnc->bpSys24h, $pnc->bpSys1w, $pnc->bpSys2_4w, $pnc->bpSys4_6w])
                    ->filter(fn ($v) => !empty($v))
                    ->isNotEmpty();
                if ($bpTakenPnc) {
                    $bump($postpartum, 'bpMeasured');
                }
                if ($this->truthy($pnc->highBpGeneral) || $this->truthy($pnc->dangerSignsGeneral)) {
                    $bump($postpartum, 'highBpOrDanger');
                }
                if ($this->truthy($pnc->referredGeneral)) {
                    $bump($postpartum, 'referred');
                }
            }

            // ── Intrapartum / Newborn Care, tallied by newborn sex (intrapartum_records) ──
            if ($ip = $record->intrapartum) {
                $sex = strtolower((string) $ip->sex) === 'male'
                    ? 'male'
                    : (strtolower((string) $ip->sex) === 'female' ? 'female' : null);

                $bumpSex = function (string $key) use (&$intrapartum, $sex) {
                    $intrapartum[$key]['total']++;
                    if ($sex) {
                        $intrapartum[$key][$sex]++;
                    }
                };

                $bumpSex('totalDeliveries');

                if ($this->contains($ip->attendantAtBirth, 'physician')) {
                    $bumpSex('attendantPhysician');
                }
                if ($this->contains($ip->attendantAtBirth, 'nurse')) {
                    $bumpSex('attendantNurse');
                }
                if ($this->contains($ip->attendantAtBirth, 'midwife')) {
                    $bumpSex('attendantMidwife');
                }

                if ($this->contains($ip->placeOfDelivery, 'public')) {
                    $bumpSex('facilityPublic');
                }
                if ($this->contains($ip->placeOfDelivery, 'private')) {
                    $bumpSex('facilityPrivate');
                }

                if ($this->contains($ip->deliveryType, 'vaginal')) {
                    $bumpSex('deliveryVaginal');
                }
                if ($this->contains($ip->deliveryType, 'cesarean') || $this->contains($ip->deliveryType, 'caesarean')) {
                    $bumpSex('deliveryCesarean');
                }
                if ($this->contains($ip->deliveryType, 'combined')) {
                    $bumpSex('deliveryCombined');
                }

                if ($this->contains($ip->deliveryOutcome, 'pre-term') || $this->contains($ip->deliveryOutcome, 'preterm')) {
                    $bumpSex('outcomePreTerm');
                } elseif ($this->contains($ip->deliveryOutcome, 'full') || $this->contains($ip->deliveryOutcome, 'term')) {
                    $bumpSex('outcomeFullTerm');
                }
                if ($this->contains($ip->deliveryOutcome, 'fetal death') || $this->contains($ip->deliveryOutcome, 'stillbirth')) {
                    $bumpSex('outcomeFetalDeath');
                }
                if ($this->contains($ip->deliveryOutcome, 'abortion') || $this->contains($ip->deliveryOutcome, 'miscarriage')) {
                    $bumpSex('outcomeAbortion');
                }

                if ($this->contains($ip->weightClassification, 'normal')) {
                    $bumpSex('birthWeightNormal');
                } elseif ($this->contains($ip->weightClassification, 'low')) {
                    $bumpSex('birthWeightLow');
                } else {
                    $bumpSex('birthWeightUnknown');
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'period' => ['month' => $selectedMonth->format('F'), 'year' => $selectedMonth->year],
            'filters' => $location['codes'],
            'data' => [
                'prenatal'    => $prenatal,
                'intrapartum' => $intrapartum,
                'postpartum'  => $postpartum,
            ],
        ]);
    }

    /**
     * SECTION C. CHILD CARE AND SERVICES
     *
     * Builds the immunization / school immunization / nutrition / sick-child indicator
     * sets consumed by M1AllPrograms.tsx's SectionC, scoped to the selected reporting
     * month and (where the underlying table supports it) region/province/municipality/barangay.
     *
     * Data sources:
     *  - child_immunization_records        (0-11mo + previous-year catch-up doses)
     *  - child_immunization_school_records (school / community based immunization)
     *  - child_nutrition_records           (breastfeeding, iron, VitA, MNP, LNS, MAM/SAM)
     *  - child_sick_records                (IMCI: VitA, diarrhea, pneumonia management)
     *
     * NOTE: child_sick_records has no profileId column, so it cannot be scoped by
     * region/province/municipality/barangay — only by the reporting month.
     */
    public function childCare(Request $request)
    {
        $month = (int) $request->input('month', now()->month);
        $year  = (int) $request->input('year', now()->year);
        $selectedMonth   = Carbon::create($year, $month, 1);
        $startOfSelected = $selectedMonth->copy()->startOfMonth();
        $endOfSelected   = $selectedMonth->copy()->endOfMonth();

        $location = $this->resolveLocationFilters($request);

        $sexEmpty = ['male' => 0, 'female' => 0, 'total' => 0];
        $bump = function (array &$bucket, string $key, ?string $sex) use ($sexEmpty) {
            if (!isset($bucket[$key])) {
                $bucket[$key] = $sexEmpty;
            }
            $bucket[$key]['total']++;
            if ($sexKey = $this->sexKey($sex)) {
                $bucket[$key][$sexKey]++;
            }
        };

        $imm0_11 = [];
        $immPrev = [];
        $schoolImm = [];
        $nutrition = [];
        $nutrition2 = [];
        $mgmtSick = [];

        // ── A.1 / A.2 Immunization (child_immunization_records) ─────────────
        $immRecords = DB::table('child_immunization_records')
            ->when(true, function ($q) use ($location) {
                $this->applyProfileIdLocationFilter($q, $location);
            })
            ->get();

        // Maps DB date column -> reporting key. CPAB/FIC/CIC have no per-dose age
        // cohort of their own, so they are always attributed to the child's own record.
        $doseCohortColumns = [
            'bcgWithin24hDate' => 'bcg24h', 'bcgLateDate' => 'bcgLate',
            'hepaBWithin24hDate' => 'hepB24h', 'hepaBLateDate' => 'hepBLate',
            'dpt1Date' => 'dpt1', 'dpt2Date' => 'dpt2', 'dpt3Date' => 'dpt3',
            'opv1Date' => 'opv1', 'opv2Date' => 'opv2', 'opv3Date' => 'opv3',
            'ipv1Date' => 'ipv1', 'ipv2Date' => 'ipv2',
            'pcv1Date' => 'pcv1', 'pcv2Date' => 'pcv2', 'pcv3Date' => 'pcv3',
            'mmr1Date' => 'mmr1', 'mmr2Date' => 'mmr2',
        ];

        foreach ($immRecords as $rec) {
            $dob = $this->parseDateOrNull($rec->dateOfBirth ?? null);
            // Cohort: children born in the reporting year are tallied under A.1
            // (current year, 0-11mo); children born the previous year are tallied
            // under A.2 (previous year catch-up doses).
            $isCurrentYearCohort  = $dob && (int) $dob->year === $year;
            $isPreviousYearCohort = $dob && (int) $dob->year === $year - 1;

            foreach ($doseCohortColumns as $col => $key) {
                $doseDate = $this->parseDateOrNull($rec->{$col} ?? null);
                if (!$doseDate || !$doseDate->between($startOfSelected, $endOfSelected)) {
                    continue;
                }
                if ($isCurrentYearCohort) {
                    $bump($imm0_11, $key, $rec->sex ?? null);
                } elseif ($isPreviousYearCohort) {
                    $bump($immPrev, $key, $rec->sex ?? null);
                }
            }

            // CPAB — assessed at registration/delivery, so it belongs to the birth cohort.
            if ($this->truthy($rec->td2Mother ?? null) || $this->truthy($rec->td3To5Mother ?? null)) {
                $regDate = $this->parseDateOrNull($rec->registrationDate ?? null);
                if ($regDate && $regDate->between($startOfSelected, $endOfSelected)) {
                    $bump($imm0_11, 'cpab', $rec->sex ?? null);
                }
            }

            // FIC / CIC completion — tallied under the previous-year table (A.2)
            // per the FHSIS M1 form layout.
            $ficDate = $this->parseDateOrNull($rec->ficDate ?? null);
            if ($this->truthy($rec->ficBcg ?? null) && $ficDate && $ficDate->between($startOfSelected, $endOfSelected)) {
                $bump($immPrev, 'fic', $rec->sex ?? null);
            }
            $cicDate = $this->parseDateOrNull($rec->cicDate ?? null);
            if ($this->truthy($rec->cicBcg ?? null) && $cicDate && $cicDate->between($startOfSelected, $endOfSelected)) {
                $bump($immPrev, 'cic', $rec->sex ?? null);
            }
        }

        // ── A.3 School / Community Based Immunization (child_immunization_school_records) ──
        $schoolRecords = DB::table('child_immunization_school_records')
            ->when(true, function ($q) use ($location) {
                $this->applyProfileIdLocationFilter($q, $location);
            })
            ->get();

        foreach ($schoolRecords as $rec) {
            $grade = strtolower((string) ($rec->gradeLevel ?? ''));
            $isGrade1 = str_contains($grade, '1');
            $isGrade7 = str_contains($grade, '7');

            $tdDate = $this->parseDateOrNull($rec->tdDate ?? null);
            if ($tdDate && $tdDate->between($startOfSelected, $endOfSelected)) {
                if ($isGrade1) $bump($schoolImm, 'grade1Td', $rec->sex ?? null);
                if ($isGrade7) $bump($schoolImm, 'grade7Td', $rec->sex ?? null);
            }
            $mrDate = $this->parseDateOrNull($rec->mrDate ?? null);
            if ($mrDate && $mrDate->between($startOfSelected, $endOfSelected)) {
                if ($isGrade1) $bump($schoolImm, 'grade1Mr', $rec->sex ?? null);
                if ($isGrade7) $bump($schoolImm, 'grade7Mr', $rec->sex ?? null);
            }

            $hpv1Sbi = $this->parseDateOrNull($rec->hpv1SbiDate ?? null);
            if ($hpv1Sbi && $hpv1Sbi->between($startOfSelected, $endOfSelected)) {
                $bump($schoolImm, 'hpv1Sbi', $rec->sex ?? null);
            }
            $hpv1Cbi = $this->parseDateOrNull($rec->hpv1CbiDate ?? null);
            if ($hpv1Cbi && $hpv1Cbi->between($startOfSelected, $endOfSelected)) {
                $bump($schoolImm, 'hpv1Cbi', $rec->sex ?? null);
            }
            $hpv2Cbi = $this->parseDateOrNull($rec->hpv2CbiDate ?? null);
            if ($hpv2Cbi && $hpv2Cbi->between($startOfSelected, $endOfSelected)) {
                $bump($schoolImm, 'hpv2Cbi', $rec->sex ?? null);
            }
        }

        // ── Nutrition (child_nutrition_records) ──────────────────────────────
        $nutritionRecords = DB::table('child_nutrition_records')
            ->when(true, function ($q) use ($location) {
                $this->applyProfileIdLocationFilter($q, $location);
            })
            ->get()
            ->filter(function ($rec) use ($startOfSelected, $endOfSelected) {
                $d = $this->parseDateOrNull($rec->dateRegistration ?? null);
                return $d && $d->between($startOfSelected, $endOfSelected);
            });

        foreach ($nutritionRecords as $rec) {
            $sex = $rec->sex ?? null;

            if (!empty($rec->breastfeedingDate)) {
                $bump($nutrition, 'breastfeedingInit', $sex);
            }
            if ($this->contains($rec->birthWeightStatus ?? null, 'low') && $this->truthy($rec->ironCompleted ?? null)) {
                $bump($nutrition, 'lbwIronComplete', $sex);
            }
            if (!empty($rec->vitaA6to11)) {
                $bump($nutrition, 'vitA6to11', $sex);
            }
            $vitADoses = collect([
                $rec->vitaA200Y1D1 ?? null, $rec->vitaA200Y1D2 ?? null,
                $rec->vitaA200Y2D1 ?? null, $rec->vitaA200Y2D2 ?? null,
                $rec->vitaA200Y3D1 ?? null, $rec->vitaA200Y3D2 ?? null,
                $rec->vitaA200Y4D1 ?? null, $rec->vitaA200Y4D2 ?? null,
            ])->filter(fn ($d) => !empty($d))->count();
            if ($vitADoses >= 2) {
                $bump($nutrition, 'vitA12to59TwoDoses', $sex);
            }
            if ($this->truthy($rec->mnp6to11Completed ?? null)) {
                $bump($nutrition, 'mnp6to11', $sex);
            }
            if ($this->truthy($rec->mnp12to23Completed ?? null)) {
                $bump($nutrition, 'mnp12to23', $sex);
            }
            if ($this->truthy($rec->lns6to11Completed ?? null)) {
                $bump($nutrition, 'lns6to11', $sex);
            }
            if ($this->truthy($rec->lns12to23Completed ?? null)) {
                $bump($nutrition, 'lns12to23', $sex);
            }

            // ── MAM / SAM (integer tallies per record) ───────────────────────
            $bumpN = function (string $key, int $n) use (&$nutrition2, $sex, $sexEmpty) {
                if ($n <= 0) return;
                if (!isset($nutrition2[$key])) $nutrition2[$key] = $sexEmpty;
                $nutrition2[$key]['total'] += $n;
                if ($sk = $this->sexKey($sex)) $nutrition2[$key][$sk] += $n;
            };
            $bumpN('seen0to59', 1); // one row = one child seen this month
            $bumpN('mamIdentified', (int) ($rec->mamIdentified ?? 0));
            $bumpN('samIdentified', (int) ($rec->samIdentified ?? 0));
            $bumpN('mamEnrolled', (int) ($rec->mamEnrolled ?? 0));
            $bumpN('mamCured', (int) ($rec->mamCured ?? 0));
            $bumpN('mamNonCured', (int) ($rec->mamNonCured ?? 0));
            $bumpN('mamDefaulted', (int) ($rec->mamDefaulted ?? 0));
            $bumpN('mamDied', (int) ($rec->mamDied ?? 0));
            $bumpN('samAdmitted', (int) ($rec->samAdmitted ?? 0));
            $bumpN('samCured', (int) ($rec->samCured ?? 0));
            $bumpN('samNonCured', (int) ($rec->samNonCured ?? 0));
            $bumpN('samDefaulted', (int) ($rec->samDefaulted ?? 0));
            $bumpN('samDied', (int) ($rec->samDied ?? 0));
        }

        // ── Management of Sick Children (child_sick_records — no location link) ──
        $sickRecords = DB::table('child_sick_records')
            ->get()
            ->filter(function ($rec) use ($startOfSelected, $endOfSelected) {
                $d = $this->parseDateOrNull($rec->dateRegistration ?? null);
                return $d && $d->between($startOfSelected, $endOfSelected);
            });

        foreach ($sickRecords as $rec) {
            $sex = $rec->sex ?? null;
            $ageMonths = is_numeric($rec->ageMonths ?? null) ? (int) $rec->ageMonths : null;
            $is6to11  = $ageMonths !== null && $ageMonths >= 6 && $ageMonths <= 11;
            $is12to59 = $ageMonths !== null && $ageMonths >= 12 && $ageMonths <= 59;

            if ($is6to11) {
                $bump($mgmtSick, 'sick6to11Seen', $sex);
                if ($this->truthy($rec->vitaminA100IU ?? null)) {
                    $bump($mgmtSick, 'vitA6to11Sick', $sex);
                }
            }
            if ($is12to59) {
                $bump($mgmtSick, 'sick12to59Seen', $sex);
                if ($this->truthy($rec->vitaminA200IU ?? null)) {
                    $bump($mgmtSick, 'vitA12to59Sick', $sex);
                }
            }
            if ($this->truthy($rec->diagnosisPersistentDiarrhea ?? null)) {
                $bump($mgmtSick, 'diarrhea0to59Seen', $sex);
                if ($this->truthy($rec->orsOnly ?? null)) {
                    $bump($mgmtSick, 'orsOnly', $sex);
                }
                if ($this->truthy($rec->orsAndZinc ?? null)) {
                    $bump($mgmtSick, 'orsZinc', $sex);
                }
            }
            if (!empty($rec->pneumoniaDateGiven)) {
                $bump($mgmtSick, 'pneumonia0to59Seen', $sex);
                $amoxDrops = $this->truthy($rec->amoxicillinDrops ?? null);
                $amoxClav  = $this->truthy($rec->amoxicillinClavulanate ?? null);
                $cefurox   = $this->truthy($rec->cefuroxime ?? null);
                $other     = $this->truthy($rec->pneumoniaOthers ?? null);
                if ($amoxDrops || $amoxClav || $cefurox || $other) {
                    $bump($mgmtSick, 'antibioticAny', $sex);
                }
                if ($amoxDrops) $bump($mgmtSick, 'amoxDrops', $sex);
                if ($amoxClav)  $bump($mgmtSick, 'amoxClav', $sex);
                if ($cefurox)   $bump($mgmtSick, 'cefuroxime', $sex);
                if ($other)     $bump($mgmtSick, 'otherAntibiotic', $sex);
            }
        }

        return response()->json([
            'status' => 'success',
            'period' => ['month' => $selectedMonth->format('F'), 'year' => $selectedMonth->year],
            'filters' => $location['codes'],
            'data' => [
                'imm0_11'    => $imm0_11,
                'immPrev'    => $immPrev,
                'schoolImm'  => $schoolImm,
                'nutrition'  => $nutrition,
                'nutrition2' => $nutrition2,
                'mgmtSick'   => $mgmtSick,
            ],
        ]);
    }

    /**
     * SECTION D. ORAL HEALTH CARE SERVICES
     *
     * Builds the 1st-visit / completed-2-visits indicator sets consumed by
     * M1AllPrograms.tsx's SectionD.
     *
     * NOTE: oral_health_care has no profileId column, so it cannot be scoped by
     * region/province/municipality/barangay — only by the reporting month.
     * The source table also has no pregnancy flag, so the "pregnant" bracket is
     * always returned empty.
     */
    public function oralHealthCare(Request $request)
    {
        $month = (int) $request->input('month', now()->month);
        $year  = (int) $request->input('year', now()->year);
        $selectedMonth   = Carbon::create($year, $month, 1);
        $startOfSelected = $selectedMonth->copy()->startOfMonth();
        $endOfSelected   = $selectedMonth->copy()->endOfMonth();

        $sexEmpty = ['male' => 0, 'female' => 0, 'total' => 0];
        $brackets = ['children1_4', 'children5_9', 'adolescents10_19', 'adults20_59', 'seniors60plus', 'pregnant'];

        $infantFirstVisit = $sexEmpty;
        $firstVisit = array_fill_keys($brackets, $sexEmpty);
        $firstVisitFacility = array_fill_keys($brackets, $sexEmpty);
        $firstVisitNonFacility = array_fill_keys($brackets, $sexEmpty);
        $completed2Visits = array_fill_keys($brackets, $sexEmpty);
        $completed2VisitsFacility = array_fill_keys($brackets, $sexEmpty);
        $completed2VisitsNonFacility = array_fill_keys($brackets, $sexEmpty);

        $bump = function (array &$bucket, string $key, ?string $sex) {
            $bucket[$key]['total']++;
            if ($sk = $this->sexKey($sex)) {
                $bucket[$key][$sk]++;
            }
        };

        $records = DB::table('oral_health_care')
            ->get()
            ->filter(function ($rec) use ($startOfSelected, $endOfSelected) {
                $d = $this->parseDateOrNull($rec->date_of_visit ?? null);
                return $d && $d->between($startOfSelected, $endOfSelected);
            });

        foreach ($records as $rec) {
            $sex = $rec->sex ?? null;

            // Infant (0-11 months) first dental visit
            $ageMonths = is_numeric($rec->age_months ?? null) ? (int) $rec->age_months : null;
            if ($ageMonths !== null && $ageMonths <= 11 && $this->truthy($rec->rpoc0_oral_screening ?? null)) {
                $infantFirstVisit['total']++;
                if ($sk = $this->sexKey($sex)) $infantFirstVisit[$sk]++;
            }

            $age = is_numeric($rec->age_years ?? null) ? (int) $rec->age_years : null;
            $bracket = $this->oralAgeBracket($age);
            if (!$bracket) {
                continue;
            }

            $location1 = strtolower((string) ($rec->service_location1st ?? ''));
            $location2 = strtolower((string) ($rec->service_location2nd ?? ''));
            $isFacility1 = str_contains($location1, 'facility') && !str_contains($location1, 'non');
            $isNonFacility1 = str_contains($location1, 'non');
            $isFacility2 = str_contains($location2, 'facility') && !str_contains($location2, 'non');
            $isNonFacility2 = str_contains($location2, 'non');

            if (!empty($rec->oral_screening1st)) {
                $bump($firstVisit, $bracket, $sex);
                if ($isFacility1) $bump($firstVisitFacility, $bracket, $sex);
                if ($isNonFacility1) $bump($firstVisitNonFacility, $bracket, $sex);
            }
            if (!empty($rec->oral_screening2nd)) {
                $bump($completed2Visits, $bracket, $sex);
                if ($isFacility2) $bump($completed2VisitsFacility, $bracket, $sex);
                if ($isNonFacility2) $bump($completed2VisitsNonFacility, $bracket, $sex);
            }
        }

        return response()->json([
            'status' => 'success',
            'period' => ['month' => $selectedMonth->format('F'), 'year' => $selectedMonth->year],
            'data' => [
                'infantFirstVisit'            => $infantFirstVisit,
                'firstVisit'                  => $firstVisit,
                'firstVisitFacility'          => $firstVisitFacility,
                'firstVisitNonFacility'       => $firstVisitNonFacility,
                'completed2Visits'            => $completed2Visits,
                'completed2VisitsFacility'    => $completed2VisitsFacility,
                'completed2VisitsNonFacility' => $completed2VisitsNonFacility,
            ],
        ]);
    }

    /**
     * SECTION E. NON-COMMUNICABLE DISEASES
     *
     * Builds the lifestyle / CVD / DM / blindness / mental health / cervical &
     * breast cancer indicator sets consumed by M1AllPrograms.tsx's SectionE.
     *
     * NOTE: geriatric_screening_records and mental_health_records have no
     * profileId column, so those two indicator groups cannot be scoped by
     * region/province/municipality/barangay.
     * The cervical_cancer_screenings table has no separate VIA / Pap Smear / HPV
     * DNA columns, so 'via', 'papSmear', 'hpvDna', 'assessedOnly', 'linkedTreated'
     * and 'linkedReferred' are always returned as 0.
     */
    public function nonCommunicableDisease(Request $request)
    {
        $month = (int) $request->input('month', now()->month);
        $year  = (int) $request->input('year', now()->year);
        $selectedMonth   = Carbon::create($year, $month, 1);
        $startOfSelected = $selectedMonth->copy()->startOfMonth();
        $endOfSelected   = $selectedMonth->copy()->endOfMonth();

        $location = $this->resolveLocationFilters($request);

        $sexEmpty = ['male' => 0, 'female' => 0, 'total' => 0];
        $bump = function (array &$bucket, string $key, ?string $sex) use ($sexEmpty) {
            if (!isset($bucket[$key])) $bucket[$key] = $sexEmpty;
            $bucket[$key]['total']++;
            if ($sk = $this->sexKey($sex)) $bucket[$key][$sk]++;
        };

        $lifestyle2059 = [];
        $lifestyle60plus = [];
        $cvd2059 = $sexEmpty;
        $cvd60plus = $sexEmpty;
        $dm2059 = $sexEmpty;
        $dm60plus = $sexEmpty;

        // ── E1-E3: PhilPEN risk assessments / CVD / DM (philpen_risk_assessments) ──
        $philpenRecords = DB::table('philpen_risk_assessments')
            ->when(true, function ($q) use ($location) {
                $this->applyProfileIdLocationFilter($q, $location, 'profile_id');
            })
            ->get();

        foreach ($philpenRecords as $rec) {
            $sex = $rec->sex ?? null;
            $age = is_numeric($rec->age ?? null) ? (int) $rec->age : null;
            $is2059  = ($age !== null && $age >= 20 && $age <= 59) || $this->contains($rec->age_group ?? null, '20-59');
            $is60plus = ($age !== null && $age >= 60) || $this->contains($rec->age_group ?? null, '60');

            $assessed = $this->parseDateOrNull($rec->date_assessment ?? null);
            $inPeriod = $assessed && $assessed->between($startOfSelected, $endOfSelected);

            if ($inPeriod) {
                $bucket = $is60plus ? 'lifestyle60plus' : ($is2059 ? 'lifestyle2059' : null);
                if ($bucket) {
                    $target = $bucket === 'lifestyle60plus' ? $lifestyle60plus : $lifestyle2059;

                    if ($this->truthy($rec->current_smoker ?? null)) $bump($target, 'currentSmoker', $sex);
                    if ($this->truthy($rec->provided_bti ?? null))   $bump($target, 'providedBti', $sex);
                    if ($this->truthy($rec->binge_alcohol ?? null))  $bump($target, 'bingeAlcohol', $sex);
                    if ($this->truthy($rec->insufficient_pa ?? null)) $bump($target, 'insufficientPa', $sex);
                    if ($this->truthy($rec->unhealthy_diet ?? null)) $bump($target, 'unhealthyDiet', $sex);
                    // bmi_category is coded (e.g. 3 = overweight, 4 = obese); columns for
                    // the tobacco-type breakdown (smokerTobacco/Vaporized/Both) don't
                    // exist in this table so they are left uncounted.
                    if ((int) ($rec->bmi_category ?? 0) === 3) $bump($target, 'overweight', $sex);
                    if ((int) ($rec->bmi_category ?? 0) === 4) $bump($target, 'obese', $sex);

                    if ($bucket === 'lifestyle60plus') {
                        $lifestyle60plus = $target;
                    } else {
                        $lifestyle2059 = $target;
                    }
                }
            }

            // Hypertension / diabetes identified in the current reporting month
            $screened = $this->parseDateOrNull($rec->screening_date1 ?? null) ?? $this->parseDateOrNull($rec->screening_date2 ?? null);
            if ($screened && $screened->between($startOfSelected, $endOfSelected)) {
                if ($this->truthy($rec->hypertension_result ?? null)) {
                    if ($is60plus) { $cvd60plus['total']++; if ($sk = $this->sexKey($sex)) $cvd60plus[$sk]++; }
                    elseif ($is2059) { $cvd2059['total']++; if ($sk = $this->sexKey($sex)) $cvd2059[$sk]++; }
                }
                if ($this->truthy($rec->diabetes_result ?? null)) {
                    if ($is60plus) { $dm60plus['total']++; if ($sk = $this->sexKey($sex)) $dm60plus[$sk]++; }
                    elseif ($is2059) { $dm2059['total']++; if ($sk = $this->sexKey($sex)) $dm2059[$sk]++; }
                }
            }
        }

        // ── E4: Blindness Prevention (eyes_screenings) ────────────────────────
        $blindnessKeys = ['screened0_9', 'screened10_19', 'screened20_59', 'screened60plus', 'identified', 'referred'];
        $blindness = array_fill_keys($blindnessKeys, $sexEmpty);

        $eyeRecords = DB::table('eyes_screenings')
            ->when(true, function ($q) use ($location) {
                $this->applyProfileIdLocationFilter($q, $location, 'profile_id');
            })
            ->get()
            ->filter(function ($rec) use ($startOfSelected, $endOfSelected) {
                $d = $this->parseDateOrNull($rec->date_screening ?? null);
                return $d && $d->between($startOfSelected, $endOfSelected);
            });

        foreach ($eyeRecords as $rec) {
            $sex = $rec->sex ?? null;
            if (!$this->truthy($rec->screened ?? null)) {
                continue;
            }
            $bracketKey = match (true) {
                $this->contains($rec->age_group ?? null, '0-9')   => 'screened0_9',
                $this->contains($rec->age_group ?? null, '10-19') => 'screened10_19',
                $this->contains($rec->age_group ?? null, '20-59') => 'screened20_59',
                $this->contains($rec->age_group ?? null, '60')    => 'screened60plus',
                default => null,
            };
            if ($bracketKey) {
                $bump($blindness, $bracketKey, $sex);
            }
            if (!empty($rec->eye_disease_code)) {
                $bump($blindness, 'identified', $sex);
            }
            if (!empty($rec->date_referred)) {
                $bump($blindness, 'referred', $sex);
            }
        }

        // ── E7: Mental Health (mental_health_records — no location link) ─────
        $mentalHealthKeys = ['screened0_9', 'screened10_19', 'screened20_59', 'screened60plus'];
        $mentalHealth = array_fill_keys($mentalHealthKeys, $sexEmpty);

        $mentalRecords = DB::table('mental_health_records')
            ->get()
            ->filter(function ($rec) use ($startOfSelected, $endOfSelected) {
                $d = $this->parseDateOrNull($rec->dateOfAssessment ?? null);
                return $d && $d->between($startOfSelected, $endOfSelected);
            });

        foreach ($mentalRecords as $rec) {
            if (!$this->truthy($rec->screenedMhgap ?? null)) {
                continue;
            }
            $sex = $rec->sex ?? null;
            $age = is_numeric($rec->age ?? null) ? (int) $rec->age : null;
            $bracket = $this->broadAgeBracket($age);
            $bracketKey = match ($bracket) {
                '0-9' => 'screened0_9', '10-19' => 'screened10_19',
                '20-59' => 'screened20_59', '60plus' => 'screened60plus',
                default => null,
            };
            if ($bracketKey) {
                $bump($mentalHealth, $bracketKey, $sex);
            }
        }

        // ── E8/E9: Cervical & Breast Cancer (cervical_cancer_screenings) ──────
        $cervical = ['screened' => 0, 'via' => 0, 'papSmear' => 0, 'hpvDna' => 0, 'assessedOnly' => 0,
                     'suspicious' => 0, 'linkedToCare' => 0, 'linkedTreated' => 0, 'linkedReferred' => 0];
        $breast = ['seen' => 0, 'highRiskOrSymptomatic' => 0, 'providedCbe' => 0, 'providedMammogram' => 0,
                   'remarkableCbe' => 0, 'remarkableMammogram' => 0, 'linkedToCare' => 0, 'asymptomaticScreened' => 0];

        $cancerRecords = DB::table('cervical_cancer_screenings')
            ->when(true, function ($q) use ($location) {
                $this->applyProfileIdLocationFilter($q, $location, 'profile_id');
            })
            ->get()
            ->filter(function ($rec) use ($startOfSelected, $endOfSelected) {
                $d = $this->parseDateOrNull($rec->date_assessment ?? null);
                return $d && $d->between($startOfSelected, $endOfSelected);
            });

        foreach ($cancerRecords as $rec) {
            if ($this->truthy($rec->cervical_screening_done ?? null)) {
                $cervical['screened']++;
                if ($this->truthy($rec->cervical_result ?? null)) {
                    $cervical['suspicious']++;
                }
            }
            if ($this->truthy($rec->cervical_linked_to_care ?? null)) {
                $cervical['linkedToCare']++;
            }

            $examType = strtolower((string) ($rec->breast_exam_type ?? ''));
            $isCbe = str_contains($examType, 'cbe') || str_contains($examType, 'clinical');
            $isMammogram = str_contains($examType, 'mammo');

            if ($this->truthy($rec->breast_risk_assessment ?? null)) {
                $breast['seen']++;
                $breast['highRiskOrSymptomatic']++;
                if ($isCbe) $breast['providedCbe']++;
                if ($isMammogram) $breast['providedMammogram']++;
                if ($this->truthy($rec->breast_result ?? null)) {
                    if ($isCbe) $breast['remarkableCbe']++;
                    if ($isMammogram) $breast['remarkableMammogram']++;
                }
                if ($this->truthy($rec->breast_linked_to_care ?? null)) {
                    $breast['linkedToCare']++;
                }
            } elseif ($isCbe || $isMammogram) {
                // Screened but not flagged high-risk/symptomatic => asymptomatic screening
                $breast['seen']++;
                $breast['asymptomaticScreened']++;
            }
        }

        return response()->json([
            'status' => 'success',
            'period' => ['month' => $selectedMonth->format('F'), 'year' => $selectedMonth->year],
            'filters' => $location['codes'],
            'data' => [
                'lifestyle2059'  => $lifestyle2059,
                'lifestyle60plus' => $lifestyle60plus,
                'cvd2059'  => $cvd2059,
                'cvd60plus' => $cvd60plus,
                'dm2059'   => $dm2059,
                'dm60plus' => $dm60plus,
                'blindness' => $blindness,
                'mentalHealth' => $mentalHealth,
                'cervical' => $cervical,
                'breast'   => $breast,
            ],
        ]);
    }

    /**
     * SECTION F. ENVIRONMENTAL HEALTH AND SANITATION
     *
     * Builds the water-source / sanitation-facility indicator sets consumed by
     * M1AllPrograms.tsx's SectionF.
     *
     * NOTE: environmental_health_records has neither a profileId column nor any
     * date column, so results cannot be scoped by month or by
     * region/province/municipality/barangay — this reflects the full,
     * all-time contents of the table.
     */
    public function environmentalHealth(Request $request)
    {
        $records = DB::table('environmental_health_records')->get();

        $levelI = $levelII = $levelIII = $safelyManagedWater = 0;
        $pourFlushSeptic = $pourFlushSewer = $vip = $basicSanitationFacility = $safelyManagedSanitation = 0;

        foreach ($records as $rec) {
            if ($this->truthy($rec->waterLevelI ?? null)) $levelI++;
            if ($this->truthy($rec->waterLevelII ?? null)) $levelII++;
            if ($this->truthy($rec->waterLevelIII ?? null)) $levelIII++;
            if ((int) ($rec->safelyManagedDrinkingWater ?? -1) === 1) $safelyManagedWater++;

            // unsanitaryToiletType: 1 = pour/flush septic, 2 = pour/flush sewer, 3 = VIP latrine
            $toiletType = (int) ($rec->unsanitaryToiletType ?? 0);
            if ($toiletType === 1) $pourFlushSeptic++;
            if ($toiletType === 2) $pourFlushSewer++;
            if ($toiletType === 3) $vip++;
            if ((int) ($rec->basicSanitationFacility ?? 0) === 1) $basicSanitationFacility++;
            if ((int) ($rec->safelyManagedSanitationService ?? 0) === 1) $safelyManagedSanitation++;
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'water' => [
                    'levelI' => $levelI, 'levelII' => $levelII, 'levelIII' => $levelIII,
                    'safelyManaged' => $safelyManagedWater,
                    'total' => $levelI + $levelII + $levelIII,
                ],
                'sanitation' => [
                    'pourFlushSeptic' => $pourFlushSeptic, 'pourFlushSewer' => $pourFlushSewer, 'vip' => $vip,
                    'basicSanitationFacility' => $basicSanitationFacility,
                    'safelyManagedSanitation' => $safelyManagedSanitation,
                    'total' => $pourFlushSeptic + $pourFlushSewer + $vip,
                ],
            ],
        ]);
    }

    /**
     * SECTION G. INFECTIOUS DISEASE PREVENTION AND CONTROL SERVICES
     *
     * Builds the filariasis / rabies / schistosomiasis / STH / leprosy indicator
     * sets consumed by M1AllPrograms.tsx's SectionG.
     *
     * NOTE: none of filariasis_registry_table, rabies_records,
     * schistosomiasis_registry, sth_registry_records or leprosy_registry carry a
     * profileId column, so none of this section can be scoped by
     * region/province/municipality/barangay — only by the reporting month.
     */
    public function infectiousDisease(Request $request)
    {
        $month = (int) $request->input('month', now()->month);
        $year  = (int) $request->input('year', now()->year);
        $selectedMonth   = Carbon::create($year, $month, 1);
        $startOfSelected = $selectedMonth->copy()->startOfMonth();
        $endOfSelected   = $selectedMonth->copy()->endOfMonth();

        $sexEmpty = ['male' => 0, 'female' => 0, 'total' => 0];
        $bump = function (array &$bucket, string $key, ?string $sex) use ($sexEmpty) {
            if (!isset($bucket[$key])) $bucket[$key] = $sexEmpty;
            $bucket[$key]['total']++;
            if ($sk = $this->sexKey($sex)) $bucket[$key][$sk]++;
        };
        $inPeriod = fn (?string $d) => ($p = $this->parseDateOrNull($d)) && $p->between($startOfSelected, $endOfSelected);

        // ── A. Filariasis ─────────────────────────────────────────────────
        $filariasis = [];
        $filRecords = DB::table('filariasis_registry_table')
            ->get()
            ->filter(fn ($r) => $inPeriod($r->date_of_registration ?? null));

        foreach ($filRecords as $rec) {
            $sex = $rec->sex ?? null;
            $result = strtolower((string) ($rec->blood_test_result ?? ''));
            $isPositive = str_contains($result, 'positive') || str_contains($result, 'reactive');

            if ($this->truthy($rec->nbe_performed ?? null)) {
                $bump($filariasis, 'examinedNbe', $sex);
                if ($isPositive) $bump($filariasis, 'positiveNbe', $sex);
            }
            if ($this->truthy($rec->rdt_performed ?? null)) {
                $bump($filariasis, 'examinedRdt', $sex);
                if ($isPositive) $bump($filariasis, 'positiveRdt', $sex);
            }
            if ($this->truthy($rec->has_lymphedema ?? null)) $bump($filariasis, 'lymphedema', $sex);
            if ($this->truthy($rec->has_elephantiasis ?? null)) $bump($filariasis, 'elephantiasis', $sex);
            if ($this->truthy($rec->has_hydrocele ?? null)) $bump($filariasis, 'hydrocele', $sex);
            if (!empty($rec->albendazole_date_given) || !empty($rec->dec_date_given) || !empty($rec->ivermectin_date_given)) {
                $bump($filariasis, 'receivedMda', $sex);
            }
        }

        // ── B. Rabies ─────────────────────────────────────────────────────
        $rabies = ['animalBites' => $sexEmpty, 'rabiesDeaths' => $sexEmpty];
        $rabiesRecords = DB::table('rabies_records')
            ->get()
            ->filter(fn ($r) => $inPeriod($r->date_of_bite ?? null));

        foreach ($rabiesRecords as $rec) {
            $sex = $rec->sex ?? null;
            $bump($rabies, 'animalBites', $sex);
            $outcome = strtolower((string) (($rec->pvrv_outcome ?? '') . ' ' . ($rec->pcev_outcome ?? '')));
            if (str_contains($outcome, 'died') || str_contains($outcome, 'death')) {
                $bump($rabies, 'rabiesDeaths', $sex);
            }
        }

        // ── C. Schistosomiasis ────────────────────────────────────────────
        $schistosomiasis = [];
        $schRecords = DB::table('schistosomiasis_registry')
            ->get()
            ->filter(fn ($r) => $inPeriod($r->date_of_registration ?? null));

        foreach ($schRecords as $rec) {
            $sex = $rec->sex ?? null;
            $bump($schistosomiasis, 'patientsSeen', $sex);
            if ($this->truthy($rec->with_signs_symptoms ?? null)) {
                $bump($schistosomiasis, 'suspectedCases', $sex);
            }
            if (!empty($rec->date_referred_to_hospital)) {
                $bump($schistosomiasis, 'referredToHospital', $sex);
            }
            if ($this->truthy($rec->mda_given ?? null) || !empty($rec->mda_date_given)) {
                $bump($schistosomiasis, 'mdaGiven', $sex);
            }
        }

        // ── D. Soil-Transmitted Helminthiasis (STH) ──────────────────────
        $sth = [];
        $sthRecords = DB::table('sth_registry_records')
            ->get()
            ->filter(fn ($r) => $inPeriod($r->date_of_registration ?? null));

        foreach ($sthRecords as $rec) {
            $sex = $rec->sex ?? null;
            $residency = strtolower((string) ($rec->residency ?? ''));
            $isResident = str_contains($residency, 'resident') && !str_contains($residency, 'non');
            $isNonResident = str_contains($residency, 'non');
            $result = strtolower((string) ($rec->screening_result ?? ''));

            if ($this->truthy($rec->screened ?? null)) {
                $bump($sth, 'screened', $sex);
            }
            if (str_contains($result, 'suspect')) {
                if ($isResident) $bump($sth, 'suspectedResident', $sex);
                if ($isNonResident) $bump($sth, 'suspectedNonResident', $sex);
            }
            if (str_contains($result, 'confirm')) {
                if ($isResident) $bump($sth, 'confirmedResident', $sex);
                if ($isNonResident) $bump($sth, 'confirmedNonResident', $sex);
            }
            if ($this->truthy($rec->treatment_given ?? null)) {
                if ($isResident) $bump($sth, 'treatedResident', $sex);
                if ($isNonResident) $bump($sth, 'treatedNonResident', $sex);
            }
            if (!empty($rec->january_mda_date)) $bump($sth, 'januaryMda', $sex);
            if (!empty($rec->july_mda_date)) $bump($sth, 'julyMda', $sex);
        }

        // ── E. Leprosy ────────────────────────────────────────────────────
        $leprosy = [];
        $lepRecords = DB::table('leprosy_registry')
            ->get()
            ->filter(fn ($r) => $inPeriod($r->date_of_registration ?? null));

        foreach ($lepRecords as $rec) {
            $sex = $rec->sex ?? null;
            $bump($leprosy, 'registered', $sex);
            if ($this->truthy($rec->confirmed_case ?? null)) {
                $bump($leprosy, 'confirmed', $sex);
            }
            $diagnosed = $this->parseDateOrNull($rec->date_of_diagnosis ?? null);
            if ($diagnosed && $diagnosed->between($startOfSelected, $endOfSelected)) {
                $bump($leprosy, 'newlyDetected', $sex);
            }
            if ($this->truthy($rec->completed_fixed_mdt ?? null) || $this->truthy($rec->beyond_fixed_mdt ?? null)) {
                $bump($leprosy, 'completedMdt', $sex);
            }
            if (!empty($rec->treatment_outcome ?? null)) {
                $bump($leprosy, 'treated', $sex);
            }
            if ($this->truthy($rec->grade2_disability ?? null)) {
                $bump($leprosy, 'grade2Disability', $sex);
            }
        }

        return response()->json([
            'status' => 'success',
            'period' => ['month' => $selectedMonth->format('F'), 'year' => $selectedMonth->year],
            'data' => [
                'filariasis'      => $filariasis,
                'rabies'          => $rabies,
                'schistosomiasis' => $schistosomiasis,
                'sth'             => $sth,
                'leprosy'         => $leprosy,
            ],
        ]);
    }

    /**
     * Resolves region/province/municipality/barangay codes from the request into the
     * descriptive text stored on household_profiles (which is synced from the mobile app).
     */
    private function resolveLocationFilters(Request $request): array
    {
        $regionCode       = $request->input('region');
        $provinceCode     = $request->input('province');
        $municipalityCode = $request->input('municipality');
        $barangayCode     = $request->input('barangay');

        return [
            'codes' => [
                'region'       => $regionCode,
                'province'     => $provinceCode,
                'municipality' => $municipalityCode,
                'barangay'     => $barangayCode,
            ],
            'desc' => [
                'region' => $regionCode
                    ? optional(DB::table('regions')->where('regCode', $regionCode)->first())->regDesc
                    : null,
                'province' => $provinceCode
                    ? optional(DB::table('provinces')->where('provCode', $provinceCode)->first())->provDesc
                    : null,
                'municipality' => $municipalityCode
                    ? optional(DB::table('municipalities')->where('citymunCode', $municipalityCode)->first())->citymunDesc
                    : null,
                'barangay' => $barangayCode
                    ? optional(DB::table('barangays')->where('brgyCode', $barangayCode)->first())->brgyDesc
                    : null,
            ],
        ];
    }

    /**
     * Applies the resolved region/province/municipality/barangay description filters
     * to a household_profiles query builder (used inside whereHas('householdProfile', ...)).
     */
    private function applyHouseholdLocationFilter($query, array $location): void
    {
        ['desc' => $desc] = $location;

        if ($desc['region']) {
            $query->where('region', $desc['region']);
        }
        if ($desc['province']) {
            $query->where('province', $desc['province']);
        }
        if ($desc['municipality']) {
            $query->where('municipality', $desc['municipality']);
        }
        if ($desc['barangay']) {
            $query->where('barangay', $desc['barangay']);
        }
    }

    /**
     * Maps a raw age to the FHSIS WRA age brackets, or null if outside the tracked range.
     */
    private function ageBracket(?int $age): ?string
    {
        if ($age === null) {
            return null;
        }
        if ($age >= 10 && $age <= 14) {
            return '10-14';
        }
        if ($age >= 15 && $age <= 19) {
            return '15-19';
        }
        if ($age >= 20 && $age <= 49) {
            return '20-49';
        }
        return null;
    }

    /**
     * Loosely interprets string/boolean "completed"-type flags (fields like completedIfa,
     * highBp, dangerSigns are stored as free-form strings rather than real booleans).
     */
    private function truthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === null) {
            return false;
        }
        $v = strtolower(trim((string) $value));
        return !in_array($v, ['', '0', 'no', 'false', 'none', 'n/a'], true);
    }

    /**
     * Case-insensitive substring check, null-safe.
     */
    private function contains(?string $haystack, string $needle): bool
    {
        if (!$haystack) {
            return false;
        }
        return str_contains(strtolower($haystack), strtolower($needle));
    }

    /**
     * Applies a region/province/municipality/barangay filter to a query builder for a
     * table that links back to household_profiles via an integer profile id column
     * (e.g. child_immunization_records.profileId, philpen_risk_assessments.profile_id).
     * Joins through household_profiles and matches on its descriptive location fields.
     */
    private function applyProfileIdLocationFilter($query, array $location, string $column = 'profileId'): void
    {
        ['desc' => $desc] = $location;

        if (!$desc['region'] && !$desc['province'] && !$desc['municipality'] && !$desc['barangay']) {
            return;
        }

        $query->whereIn($column, function ($sub) use ($desc) {
            $sub->select('id')->from('household_profiles');
            if ($desc['region']) $sub->where('region', $desc['region']);
            if ($desc['province']) $sub->where('province', $desc['province']);
            if ($desc['municipality']) $sub->where('municipality', $desc['municipality']);
            if ($desc['barangay']) $sub->where('barangay', $desc['barangay']);
        });
    }

    /**
     * Safely parses a free-form date string (as stored across these tables) into a
     * Carbon instance, or null if it's empty/unparsable.
     */
    private function parseDateOrNull(?string $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Normalizes a free-form sex string to 'male' | 'female' | null.
     */
    private function sexKey(?string $sex): ?string
    {
        $s = strtolower(trim((string) $sex));
        if (in_array($s, ['m', 'male'], true)) {
            return 'male';
        }
        if (in_array($s, ['f', 'female'], true)) {
            return 'female';
        }
        return null;
    }

    /**
     * Maps a raw age to the broad NCD/mental-health age brackets used across
     * Section E (0-9 / 10-19 / 20-59 / 60+), or null if the age is unavailable.
     */
    private function broadAgeBracket(?int $age): ?string
    {
        if ($age === null) {
            return null;
        }
        if ($age <= 9) return '0-9';
        if ($age <= 19) return '10-19';
        if ($age <= 59) return '20-59';
        return '60plus';
    }

    /**
     * Maps a raw age to the FHSIS oral-health-service age brackets used in Section D.
     */
    private function oralAgeBracket(?int $age): ?string
    {
        if ($age === null) {
            return null;
        }
        if ($age >= 1 && $age <= 4) return 'children1_4';
        if ($age >= 5 && $age <= 9) return 'children5_9';
        if ($age >= 10 && $age <= 19) return 'adolescents10_19';
        if ($age >= 20 && $age <= 59) return 'adults20_59';
        if ($age >= 60) return 'seniors60plus';
        return null;
    }
}