<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\HouseholdProfile;
use App\Models\FamilyPlanningRecord;
use App\Models\FamilyPlanningDropOut;
use App\Models\MaternalCareRecord;
use App\Models\ChildImmunizationRecord;
use App\Models\ChildImmunizationSchoolRecord;
use App\Models\ChildNutritionRecord;
use App\Models\ChildSickRecord;
use App\Models\OralHealthCare;
use App\Models\PhilpenRiskAssessment;
use App\Models\EyesScreening;
use App\Models\GeriatricScreeningRecord;
use App\Models\MentalHealthRecord;
use App\Models\CervicalCancerScreening;
use App\Models\EnvironmentalHealthRecord;
use App\Models\FilariasisRegistry;
use App\Models\RabiesRecord;
use App\Models\SchistosomiasisRegistry;
use App\Models\SthRegistryRecord;
use App\Models\LeprosyRegistry;
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
        $currentUsersBOM = $currentUsersEOM = $otherReport = $dropOutsPrev = $dropOutsPresent = [];

        foreach ($methods as $m) {
            $newAcceptorsPrevMonth[$m] = $otherAcceptorsPresent[$m] = $newAcceptorsPresent[$m] =
            $currentUsersBOM[$m] = $currentUsersEOM[$m] = $otherReport[$m] = $dropOutsPrev[$m] = $dropOutsPresent[$m] = $empty;
        }

        // 2. Time Period Parameters
        $period          = $this->resolveReportPeriod($request);
        $startOfSelected = $period['start'];
        $endOfSelected   = $period['end'];
        $previousStart   = $period['previousStart'];
        $previousEnd     = $period['previousEnd'];

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
                ->filter(fn ($d) => $d->between($startOfSelected, $endOfSelected))
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

            if ($isDropoutBom) {
                $dropOutsPrev[$method][$age]++;
                $dropOutsPrev[$method]['total']++;
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
            if ($regDate->between($startOfSelected, $endOfSelected)) {
                if ($item->clientType === 'NA = New Acceptors'){
                    $newAcceptorsPresent[$method][$age]++;
                    $newAcceptorsPresent[$method]['total']++;
                } else {
                    $otherAcceptorsPresent[$method][$age]++;
                    $otherAcceptorsPresent[$method]['total']++;
                }
            } elseif ($regDate->between($previousStart, $previousEnd)) {
                if ($item->clientType === 'NA = New Acceptors'){
                    $newAcceptorsPrevMonth[$method][$age]++;
                    $newAcceptorsPrevMonth[$method]['total']++;
                } else {
                    $otherReport[$method][$age]++;
                    $otherReport[$method]['total']++;
                }
            } elseif ($regDate->lessThan($previousStart)) {
                $otherReport[$method][$age]++;
                $otherReport[$method]['total']++;
            }
        }

        // --- Reconcile BOM/EOM current users from the acceptor/drop-out ledgers ---
        foreach ($methods as $method) {
            foreach (['10-14', '15-19', '20-49'] as $age) {
                $currentUsersBOM[$method][$age] = max(
                    0,
                    $otherReport[$method][$age]
                    - $dropOutsPrev[$method][$age]
                );

                $currentUsersEOM[$method][$age] = max(
                    0,
                    $currentUsersBOM[$method][$age]
                    + $otherAcceptorsPresent[$method][$age]
                    + $newAcceptorsPrevMonth[$method][$age]
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

        // return response()->json($familyData);

        return response()->json([
            'status' => 'success',
            'period' => $period['periodMeta'],
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
        $period          = $this->resolveReportPeriod($request);
        $startOfSelected = $period['start'];
        $endOfSelected   = $period['end'];

        // 2. Location Filter Parameters
        $location = $this->resolveLocationFilters($request);

        // 3. Bucket Templates
        $ageBracketEmpty = ['10-14' => 0, '15-19' => 0, '20-49' => 0, 'total' => 0];

        $prenatalKeys = [
            'anc8Completed', 'nutritionAssessed', 'nutritionNormal', 'nutritionLow', 'nutritionHigh',
            'td2PlusFirstPregnancy', 'td2Plus',
            'ifaCompleted', 'mmCompleted', 'ccCompleted',
            'anemiaScreened', 'anemiaDiagnosed',
            'gdmScreened', 'gdmDiagnosed',
            'dewormed',
            'bpMeasured', 'highBpOrDanger', 'referred',
            'anc8A1', 'anc8A2', 'anc81B', 'anc8B1', 'anc8B1', 'anc8B2', 'anc8B3'
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
        // Intrapartum/newborn indicators are now bucketed by the MOTHER's
        // age bracket (10-14/15-19/20-49/total), matching the rest of
        // Section B, instead of by the newborn's sex.
        $intrapartum = [];
        foreach ($intrapartumKeys as $k) {
            $intrapartum[$k] = $ageBracketEmpty;
        }

        $postpartumKeys = ['pnc4Completed', 'ifaCompleted', 'vitACompleted', 'bpMeasured', 'highBpOrDanger', 'referred', 'pnc4A1', 'pnc4A2', 'pnc41B', 'pnc4B1', 'pnc4B2', 'pnc4B3'];
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
            ->get();
            // ->filter(function ($record) use ($startOfSelected, $endOfSelected) {
            //     if (empty($record->registrationDate)) {
            //         return false;
            //     }
            //     try {
            //         return Carbon::parse($record->registrationDate)->between($startOfSelected, $endOfSelected);
            //     } catch (\Exception $e) {
            //         return false;
            //     }
            // });

        // return response()->json($records, 200);
            // Log::info('Sync upload received (delta push of unsynced records).');
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
                    if($anc->classificationStatus === "A - Resident"){
                        $bump($prenatal, 'anc8Completed');
                        $bump($prenatal, 'anc8A1');
                    }
                    if($anc->classificationStatus === "B - Trans In"){
                        $bump($prenatal, 'anc8Completed');
                        $bump($prenatal, 'anc8A2');
                    }
                    if($anc->classificationStatus === "A - Resident"){
                        $bump($prenatal, 'anc81B');
                        $bump($prenatal, 'anc8B1');
                    }
                    if($anc->classificationStatus === "B - Trans In"){
                        $bump($prenatal, 'anc81B');
                        $bump($prenatal, 'anc8B2');
                    }
                    if($anc->classificationStatus === "C - Trans Out before receiving 8ANCS"){
                        $bump($prenatal, 'anc81B');
                        $bump($prenatal, 'anc8B3');
                    }
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
                    
                if ($visitsCompleted >= 4 || $pnc->visit4_6wDate !== null) {
                    if($pnc->PostpartumClassification === 'A - Resident'){
                        $bump($postpartum, 'pnc4Completed');
                        $bump($postpartum, 'pnc4A1');
                    }
                    if($pnc->PostpartumClassification === 'B - Trans in'){
                        $bump($postpartum, 'pnc4Completed');
                        $bump($postpartum, 'pnc4A2');
                    }
                    if($pnc->PostpartumClassification === 'A - Resident'){
                        $bump($postpartum, 'pnc41B');
                        $bump($postpartum, 'pnc4B1');
                    }
                    if($pnc->PostpartumClassification === 'B - Trans in'){
                        $bump($postpartum, 'pnc41B');
                        $bump($postpartum, 'pnc4B2');
                    }
                    if($pnc->PostpartumClassification === 'C - Trans Out before completing 4PNC'){
                        $bump($postpartum, 'pnc41B');
                        $bump($postpartum, 'pnc4B3');
                    }
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

            // ── Intrapartum / Newborn Care, tallied by the MOTHER's age bracket (intrapartum_records) ──
            if ($ip = $record->intrapartum) {
                $bumpAge = function (string $key) use (&$intrapartum, $bracket) {
                    $intrapartum[$key][$bracket]++;
                    $intrapartum[$key]['total']++;
                };

                $bumpAge('totalDeliveries');

                if ($this->contains($ip->attendantAtBirth, 'physician')) {
                    $bumpAge('attendantPhysician');
                }
                if ($this->contains($ip->attendantAtBirth, 'nurse')) {
                    $bumpAge('attendantNurse');
                }
                if ($this->contains($ip->attendantAtBirth, 'midwife')) {
                    $bumpAge('attendantMidwife');
                }

                if ($this->contains($ip->placeOfDelivery, 'public')) {
                    $bumpAge('facilityPublic');
                }
                if ($this->contains($ip->placeOfDelivery, 'private')) {
                    $bumpAge('facilityPrivate');
                }

                if ($this->contains($ip->deliveryType, 'vaginal')) {
                    $bumpAge('deliveryVaginal');
                }
                if ($this->contains($ip->deliveryType, 'cesarean') || $this->contains($ip->deliveryType, 'caesarean')) {
                    $bumpAge('deliveryCesarean');
                }
                if ($this->contains($ip->deliveryType, 'combined')) {
                    $bumpAge('deliveryCombined');
                }

                if ($this->contains($ip->deliveryOutcome, 'pre-term') || $this->contains($ip->deliveryOutcome, 'preterm')) {
                    $bumpAge('outcomePreTerm');
                } elseif ($this->contains($ip->deliveryOutcome, 'full') || $this->contains($ip->deliveryOutcome, 'term')) {
                    $bumpAge('outcomeFullTerm');
                }
                if ($this->contains($ip->deliveryOutcome, 'fetal death') || $this->contains($ip->deliveryOutcome, 'stillbirth')) {
                    $bumpAge('outcomeFetalDeath');
                }
                if ($this->contains($ip->deliveryOutcome, 'abortion') || $this->contains($ip->deliveryOutcome, 'miscarriage')) {
                    $bumpAge('outcomeAbortion');
                }

                if ($this->contains($ip->weightClassification, 'normal')) {
                    $bumpAge('birthWeightNormal');
                } elseif ($this->contains($ip->weightClassification, 'low')) {
                    $bumpAge('birthWeightLow');
                } else {
                    $bumpAge('birthWeightUnknown');
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'period' => $period['periodMeta'],
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
        $period          = $this->resolveReportPeriod($request);
        $startOfSelected = $period['start'];
        $endOfSelected   = $period['end'];
        $year            = $period['year'];

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
        $immRecords = ChildImmunizationRecord::query()
            ->when(true, function ($q) use ($location) {
                $this->applyProfileIdLocationFilter($q, $location);
            })
            ->get();
        

        // Dose columns exclusive to A.1 (0-11 months, current year cohort).
        // MMR 2 is NOT listed under A.1 in the spec — it only appears in A.2.
        $doseColumns0_11 = [
            'bcgWithin24hDate' => 'bcg24h', 'bcgLateDate' => 'bcgLate',
            'hepaBWithin24hDate' => 'hepB24h', 'hepaBLateDate' => 'hepBLate',
            'dpt1Date' => 'dpt1', 'dpt2Date' => 'dpt2', 'dpt3Date' => 'dpt3',
            'opv1Date' => 'opv1', 'opv2Date' => 'opv2', 'opv3Date' => 'opv3',
            'ipv1Date' => 'ipv1', 'ipv2Date' => 'ipv2',
            'pcv1Date' => 'pcv1', 'pcv2Date' => 'pcv2', 'pcv3Date' => 'pcv3',
            'mmr1Date' => 'mmr1',
        ];

        // Dose columns for A.2 (previous-year cohort, catch-up doses).
        // Excludes bcg24h, bcgLate, hepB24h, hepBLate (not listed in A.2 spec).
        // Includes mmr2Date and FIC/CIC (handled separately below).
        $doseColumnsPrev = [
            'dpt1Date' => 'dpt1', 'dpt2Date' => 'dpt2', 'dpt3Date' => 'dpt3',
            'opv1Date' => 'opv1', 'opv2Date' => 'opv2', 'opv3Date' => 'opv3',
            'ipv1Date' => 'ipv1', 'ipv2Date' => 'ipv2',
            'pcv1Date' => 'pcv1', 'pcv2Date' => 'pcv2', 'pcv3Date' => 'pcv3',
            'mmr1Date' => 'mmr1', 'mmr2Date' => 'mmr2',
        ];

        // return response()->json($immRecords, 200);

        foreach ($immRecords as $rec) {
            $dob = $this->parseDateOrNull($rec->dateOfBirth ?? null);

            // Cohort classification per spec:
            //   A.1 — child is < 12 months old at the end of the reporting period
            //          (i.e. born within the last 11 months 29 days).
            //   A.2 — child is >= 12 months old at the end of the reporting period
            //          (i.e. born more than 11 months ago — previous year catch-up).
            $ageMonthsAtPeriodEnd = $dob ? (int) $dob->diffInMonths($endOfSelected) : null;
            $isCurrentYearCohort  = $ageMonthsAtPeriodEnd !== null && $ageMonthsAtPeriodEnd < 12;
            $isPreviousYearCohort = $ageMonthsAtPeriodEnd !== null && $ageMonthsAtPeriodEnd >= 12;

            if ($isCurrentYearCohort) {
                foreach ($doseColumns0_11 as $col => $key) {
                    $doseDate = $this->parseDateOrNull($rec->{$col} ?? null);
                    // return response()->json($endOfSelected, 200);
                    if ($doseDate && $doseDate->between($startOfSelected, $endOfSelected)) {
                        $bump($imm0_11, $key, $rec->sex ?? null);
                    }
                } 
            } elseif ($isPreviousYearCohort) {
                foreach ($doseColumnsPrev as $col => $key) {
                    $doseDate = $this->parseDateOrNull($rec->{$col} ?? null);
                    if ($doseDate && $doseDate->between($startOfSelected, $endOfSelected)) {
                        $bump($immPrev, $key, $rec->sex ?? null);
                    }
                }
            }

            // CPAB — Children Protected At Birth.
            // Spec: td2Mother = 1 OR td3To5Mother = 1, AND dateOfBirth < 12 months.
            // No "dose given this month" date to check — this is a birth-cohort flag,
            // so we simply count it for every current-year (0-11 mo) child whose
            // record falls in the reporting period via the location/query scope above.
            if ($isCurrentYearCohort && ($this->truthy($rec->td2Mother ?? null) || $this->truthy($rec->td3To5Mother ?? null))) {
                $bump($imm0_11, 'cpab', $rec->sex ?? null);
            }

            // FIC / CIC completion — tallied under the previous-year table (A.2)
            // per the FHSIS M1 form layout.
            // Gate on the completion DATE being recorded, not on the ficBcg/cicBcg
            // checkbox: a child whose BCG field was left blank but whose FIC date was
            // stamped would be silently dropped by the old ficBcg truthy check.
            $ficDate = $this->parseDateOrNull($rec->ficDate ?? null);
            if ($ficDate && $ficDate->between($startOfSelected, $endOfSelected)) {
                $bump($immPrev, 'fic', $rec->sex ?? null);
            }
            $cicDate = $this->parseDateOrNull($rec->cicDate ?? null);
            if ($cicDate && $cicDate->between($startOfSelected, $endOfSelected)) {
                $bump($immPrev, 'cic', $rec->sex ?? null);
            }
        }

        // ── A.3 School / Community Based Immunization (child_immunization_school_records) ──
        $schoolRecords = ChildImmunizationSchoolRecord::query()
            ->when(true, function ($q) use ($location) {
                $this->applyProfileIdLocationFilter($q, $location);
            })
            ->get();

        foreach ($schoolRecords as $rec) {
            // Spec: gradeLevel = "A" for Grade 1 learners, gradeLevel = "C" for Grade 7 learners.
            // The DB stores the single-letter code, not a free-form string — match exactly.
            $grade    = strtoupper(trim((string) ($rec->gradeLevel ?? '')));
            $isGrade1 = $grade === 'A';
            $isGrade7 = $grade === 'C';

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
        // Per spec, each indicator is gated on its OWN date column matching the
        // reporting period — NOT on dateRegistration. MAM/SAM flags have no date
        // gate at all (spec says mamIdentified = 1, not a date comparison).
        // We therefore load all location-scoped records and evaluate each indicator
        // field independently.
        $nutritionRecords = ChildNutritionRecord::query()
            ->when(true, function ($q) use ($location) {
                $this->applyProfileIdLocationFilter($q, $location);
            })
            ->get();

        foreach ($nutritionRecords as $rec) {
            $sex = $rec->sex ?? null;

            // Helper: true when a date-type field falls within the reporting period.
            $inPeriod = function (?string $value) use ($startOfSelected, $endOfSelected): bool {
                $d = $this->parseDateOrNull($value);
                return $d && $d->between($startOfSelected, $endOfSelected);
            };

            // 1. Newborns initiated on breastfeeding within 1 hour after birth.
            //    Gate: breastfeedingDate within the reporting period.
            if ($inPeriod($rec->breastfeedingDate ?? null)) {
                $bump($nutrition, 'breastfeedingInit', $sex);
            }

            // 2. LBW infants given complete iron supplements.
            //    Gate: ironCompletedDate within the reporting period (spec uses the
            //    date column, not the boolean ironCompleted flag).
            if ($this->contains($rec->birthWeightStatus ?? null, 'low') && $inPeriod($rec->ironCompletedDate ?? null)) {
                $bump($nutrition, 'lbwIronComplete', $sex);
            }

            // 3a. Infants 6-11 months who received 1 dose of Vitamin A.
            //     Gate: vitaA6to11 date within the reporting period.
            if ($inPeriod($rec->vitaA6to11 ?? null)) {
                $bump($nutrition, 'vitA6to11', $sex);
            }

            // 3b. Children 12-59 months who completed 2 doses of Vitamin A.
            //     Spec: count if ANY of the 7 listed dose date columns falls within
            //     the reporting period (at least one dose was given this month).
            //     Note: vitaA200Y2D2 exists in the model but is NOT in the spec list.
            $vitADosesInPeriod = collect([
                $rec->vitaA200Y1D1 ?? null, $rec->vitaA200Y1D2 ?? null,
                $rec->vitaA200Y2D1 ?? null,
                $rec->vitaA200Y3D1 ?? null, $rec->vitaA200Y3D2 ?? null,
                $rec->vitaA200Y4D1 ?? null, $rec->vitaA200Y4D2 ?? null,
            ])->contains(fn ($d) => $inPeriod($d));
            if ($vitADosesInPeriod) {
                $bump($nutrition, 'vitA12to59TwoDoses', $sex);
            }

            // 4a. Infants 6-11 months who completed routine MNP supplementation.
            //     Gate: mnp6to11Completed date within the reporting period.
            if ($inPeriod($rec->mnp6to11Completed ?? null)) {
                $bump($nutrition, 'mnp6to11', $sex);
            }

            // 4b. Children 12-23 months who completed routine MNP supplementation.
            //     Gate: mnp12to23Completed date within the reporting period.
            if ($inPeriod($rec->mnp12to23Completed ?? null)) {
                $bump($nutrition, 'mnp12to23', $sex);
            }

            // 5a. Infants 6-11 months who completed routine LNS-SQ supplementation.
            //     Gate: lns6to11Completed date within the reporting period.
            if ($inPeriod($rec->lns6to11Completed ?? null)) {
                $bump($nutrition, 'lns6to11', $sex);
            }

            // 5b. Children 12-23 months who completed routine LNS-SQ supplementation.
            //     Gate: lns12to23Completed date within the reporting period.
            if ($inPeriod($rec->lns12to23Completed ?? null)) {
                $bump($nutrition, 'lns12to23', $sex);
            }

            // ── MAM / SAM (integer tallies per record) ───────────────────────
            // Spec: these are flag checks (= 1), NOT date comparisons — no period gate.
            $bumpN = function (string $key, int $n) use (&$nutrition2, $sex, $sexEmpty) {
                if ($n <= 0) return;
                if (!isset($nutrition2[$key])) $nutrition2[$key] = $sexEmpty;
                $nutrition2[$key]['total'] += $n;
                if ($sk = $this->sexKey($sex)) $nutrition2[$key][$sk] += $n;
            };
            // seen0to59: spec says "0-59 months old SEEN during the reporting period".
            // We scope to age range; the broader record set is already location-filtered.
            $nutAgeMonths = is_numeric($rec->ageMonths ?? null) ? (int) $rec->ageMonths : null;
            if ($nutAgeMonths !== null && $nutAgeMonths >= 0 && $nutAgeMonths <= 59) {
                $bumpN('seen0to59', 1);
            }
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
        $sickRecords = ChildSickRecord::query()
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
            // Diarrhea indicator is "0-59 months old" — guard age before the pneumonia block.
            $is0to59 = $ageMonths !== null && $ageMonths >= 0 && $ageMonths <= 59;
            if ($is0to59 && $this->truthy($rec->diagnosisPersistentDiarrhea ?? null)) {
                $bump($mgmtSick, 'diarrhea0to59Seen', $sex);
                if ($this->truthy($rec->orsOnly ?? null)) {
                    $bump($mgmtSick, 'orsOnly', $sex);
                }
                if ($this->truthy($rec->orsAndZinc ?? null)) {
                    $bump($mgmtSick, 'orsZinc', $sex);
                }
            }
            if ($is0to59 && !empty($rec->pneumoniaDateGiven)) {
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
            'period' => $period['periodMeta'],
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
        $period          = $this->resolveReportPeriod($request);
        $startOfSelected = $period['start'];
        $endOfSelected   = $period['end'];

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

        $records = OralHealthCare::query()
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
            'period' => $period['periodMeta'],
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
     * NOTE: mental_health_records has no profileId column, so that indicator
     * group cannot be scoped by region/province/municipality/barangay.
     * cervical.* is scoped to women aged 30-65 and breast.* to women aged
     * 30-69 (50-69 for the asymptomatic-screening item), computed from
     * date_of_birth as of each record's date_assessment.
     * breast.* relies on parsing breast_risk_assessment / breast_exam_type as
     * comma-separated multi-value fields — flagged as an assumption in the
     * code, please verify against the real column encoding.
     * geriatricScreening.positive is mapped from results = "0" per the spec as
     * given — double-check this against the real encoding, since it reads
     * backwards from what "positive result" normally implies.
     */
    public function nonCommunicableDisease(Request $request)
    {
        $period          = $this->resolveReportPeriod($request);
        $startOfSelected = $period['start'];
        $endOfSelected   = $period['end'];

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
        $philpenRecords = PhilpenRiskAssessment::query()
            ->when(true, function ($q) use ($location) {
                $this->applyProfileIdLocationFilter($q, $location, 'profile_id');
            })
            ->get();

        foreach ($philpenRecords as $rec) {
            $sex = $rec->sex ?? null;
            $age = is_numeric($rec->age ?? null) ? (int) $rec->age : null;
            $ageGroupRaw = trim((string) ($rec->age_group ?? ''));
            $is2059  = ($age !== null && $age >= 20 && $age <= 59)
                || str_starts_with($ageGroupRaw, 'A')
                || ($this->contains($ageGroupRaw, '20') && $this->contains($ageGroupRaw, '59'));
            $is60plus = ($age !== null && $age >= 60)
                || str_starts_with($ageGroupRaw, 'B')
                || $this->contains($ageGroupRaw, '60');

            $assessed = $this->parseDateOrNull($rec->date_assessment ?? null);
            $inPeriod = $assessed && $assessed->between($startOfSelected, $endOfSelected);

            if ($inPeriod) {
                $bucket = $is60plus ? 'lifestyle60plus' : ($is2059 ? 'lifestyle2059' : null);
                if ($bucket) {
                    $target = $bucket === 'lifestyle60plus' ? $lifestyle60plus : $lifestyle2059;

                    // current_smoker is coded: 2 = Tobacco Products, 3 = Vaporized Nicotine
                    // Products, 4 = Both. "Current Smokers" (1a/2a) is the sum of the three.
                    $smokerCode = (int) ($rec->current_smoker ?? 0);
                    if (in_array($smokerCode, [2, 3, 4], true)) $bump($target, 'currentSmoker', $sex);
                    if ($smokerCode === 2) $bump($target, 'smokerTobacco', $sex);
                    if ($smokerCode === 3) $bump($target, 'smokerVaporized', $sex);
                    if ($smokerCode === 4) $bump($target, 'smokerBoth', $sex);

                    if ($this->truthy($rec->provided_bti ?? null))   $bump($target, 'providedBti', $sex);
                    if ($this->truthy($rec->binge_alcohol ?? null))  $bump($target, 'bingeAlcohol', $sex);
                    if ($this->truthy($rec->insufficient_pa ?? null)) $bump($target, 'insufficientPa', $sex);
                    if ($this->truthy($rec->unhealthy_diet ?? null)) $bump($target, 'unhealthyDiet', $sex);
                    // bmi_category is coded: 1 = overweight, 2 = obese.
                    if ((int) ($rec->bmi_category ?? 0) === 1) $bump($target, 'overweight', $sex);
                    if ((int) ($rec->bmi_category ?? 0) === 2) $bump($target, 'obese', $sex);

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
        // age_group is a single letter: A = 0-9, B = 10-19, C = 20-59, D = 60+.
        // eye_disease_code is a single letter: A = changes in vision, B = changes
        // in appearance, C = eye/orbital injury, D = routine eye exams.
        $eyeAgeSuffix     = ['A' => '0_9', 'B' => '10_19', 'C' => '20_59', 'D' => '60plus'];
        $eyeCategoryLabel = ['A' => 'Vision', 'B' => 'Appearance', 'C' => 'Injury', 'D' => 'Routine'];

        $blindnessKeys = ['screened0_9', 'screened10_19', 'screened20_59', 'screened60plus', 'identified', 'referred'];
        foreach ($eyeAgeSuffix as $suffix) {
            $blindnessKeys[] = "identified{$suffix}";
            $blindnessKeys[] = "referred{$suffix}";
            foreach ($eyeCategoryLabel as $cat) {
                $blindnessKeys[] = "identified{$cat}{$suffix}";
            }
        }
        $blindness = array_fill_keys($blindnessKeys, $sexEmpty);

        $eyeRecords = EyesScreening::query()
            ->when(true, function ($q) use ($location) {
                $this->applyProfileIdLocationFilter($q, $location, 'profile_id');
            })
            ->get();

        foreach ($eyeRecords as $rec) {
            $sex = $rec->sex ?? null;

            $ag = strtoupper(trim((string) ($rec->age_group ?? '')));
            $ageLetter = in_array($ag, ['A', 'B', 'C', 'D'], true) ? $ag : null;
            $suffix = $ageLetter ? $eyeAgeSuffix[$ageLetter] : null;

            $screenedDate = $this->parseDateOrNull($rec->date_screening ?? null);
            $screenedInPeriod = $screenedDate && $screenedDate->between($startOfSelected, $endOfSelected);

            // 1a-1d. Screened for eye disease/s, by age group
            if ($screenedInPeriod && $suffix && $this->truthy($rec->screened ?? null)) {
                $bump($blindness, "screened{$suffix}", $sex);
            }

            // 2a-2d / 2a1-2d4. Screened and identified with an eye ailment, by age
            // group and by category (vision / appearance / injury / routine exam)
            if ($screenedInPeriod && $suffix) {
                $code = strtoupper(trim((string) ($rec->eye_disease_code ?? '')));
                if (isset($eyeCategoryLabel[$code])) {
                    $bump($blindness, "identified{$eyeCategoryLabel[$code]}{$suffix}", $sex);
                    $bump($blindness, "identified{$suffix}", $sex);
                    $bump($blindness, 'identified', $sex);
                }
            }

            // 3a-3d. Identified and referred to an eye health professional, by age
            // group — scoped to referrals made within the reporting period.
            $referredDate = $this->parseDateOrNull($rec->date_referred ?? null);
            if ($referredDate && $referredDate->between($startOfSelected, $endOfSelected) && $suffix) {
                $bump($blindness, "referred{$suffix}", $sex);
                $bump($blindness, 'referred', $sex);
            }
        }

        // ── E5/E6: Senior Immunization & Geriatric Screening (geriatric_screening_records) ──
        $seniorImmunizationKeys = ['ppvNotPreviouslyReceived', 'ppvGiven', 'seniorsSeen', 'influenzaGiven'];
        $seniorImmunization = array_fill_keys($seniorImmunizationKeys, $sexEmpty);

        $geriatricKeys = ['screened', 'positive', 'memory', 'depression', 'polypharmacy', 'urinaryIncontinence'];
        $geriatricScreening = array_fill_keys($geriatricKeys, $sexEmpty);

        $geriatricRecords = GeriatricScreeningRecord::query()
            ->when(true, function ($q) use ($location) {
                $this->applyProfileIdLocationFilter($q, $location, 'profile_id');
            })
            ->get();

        foreach ($geriatricRecords as $rec) {
            $sex = $rec->sex ?? null;

            $seenDate = $this->parseDateOrNull($rec->date_of_screening ?? null);
            $seenInPeriod = $seenDate && $seenDate->between($startOfSelected, $endOfSelected);

            if ($seenInPeriod) {
                $bump($seniorImmunization, 'seniorsSeen', $sex);
                // ppv_received_at60 = false means this senior, seen this period,
                // had not previously received PPV upon reaching 60.
                if (!$this->truthy($rec->ppv_received_at60 ?? null)) {
                    $bump($seniorImmunization, 'ppvNotPreviouslyReceived', $sex);
                }
            }

            $ppvDate = $this->parseDateOrNull($rec->ppv_date_given ?? null);
            if ($ppvDate && $ppvDate->between($startOfSelected, $endOfSelected)) {
                $bump($seniorImmunization, 'ppvGiven', $sex);
            }

            $influenzaDate = $this->parseDateOrNull($rec->influenza_date_given ?? null);
            if ($influenzaDate && $influenzaDate->between($startOfSelected, $endOfSelected)) {
                $bump($seniorImmunization, 'influenzaGiven', $sex);
            }

            $results = strtoupper((string) ($rec->results ?? ''));
            if ($results !== '' && preg_match('/[A-I]/', $results)) {
                $bump($geriatricScreening, 'screened', $sex);
            }
            // NOTE: spec literally maps "positive result" to results = "0". This
            // reads as the inverse of what "positive" normally means for a screening
            // result field — verify against the app's actual encoding before relying
            // on this in production.
            if (trim($rec->results ?? '') === '0') {
                $bump($geriatricScreening, 'positive', $sex);
            }
            if (str_contains($results, 'A')) $bump($geriatricScreening, 'memory', $sex);
            if (str_contains($results, 'B')) $bump($geriatricScreening, 'depression', $sex);
            if (str_contains($results, 'C')) $bump($geriatricScreening, 'polypharmacy', $sex);
            if (str_contains($results, 'D')) $bump($geriatricScreening, 'urinaryIncontinence', $sex);
        }

        // ── E7: Mental Health (mental_health_records — no location link) ─────
        $mentalHealthKeys = ['screened0_9', 'screened10_19', 'screened20_59', 'screened60plus'];
        $mentalHealth = array_fill_keys($mentalHealthKeys, $sexEmpty);

        $mentalRecords = MentalHealthRecord::query()
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
        // cervical_screening_done: 0 = Assessed Only, 1 = VIA, 2 = Pap Smear, 3 = HPV DNA
        // cervical_result: 1 = suspicious, 2 = precancerous lesion
        // cervical_linked_to_care: 1 = Treated, 2 = Referred
        //
        // ASSUMPTION (flagged — please verify against the real column encoding):
        // the spec ANDs breast_risk_assessment against both a numeric (1/2) and a
        // letter ("A"/"B") value on the same field, and separately ANDs
        // breast_exam_type against both "CBE"/"M" and "A". The only way those are
        // simultaneously satisfiable is if these are comma-separated multi-value
        // strings (like geriatric_screening_records.results), so both fields are
        // parsed as comma lists here rather than single values.
        $cervicalKeys = [
            'screened', 'via', 'papSmear', 'hpvDna', 'assessedOnly',
            'suspicious', 'suspiciousLinkedToCare', 'suspiciousLinkedTreated', 'suspiciousLinkedReferred',
            'precancerous', 'precancerousLinkedToCare', 'precancerousLinkedTreated', 'precancerousLinkedReferred',
        ];
        $cervical = array_fill_keys($cervicalKeys, 0);

        $breastKeys = [
            'seen', 'highRiskOrSymptomatic',
            'provided', 'providedCbe', 'providedMammogram',
            'remarkable', 'remarkableCbe', 'remarkableMammogram',
            'linkedToCare', 'linkedToCareCbe', 'linkedToCareMammogram',
            'asymptomaticScreened', 'asymptomaticCbe', 'asymptomaticMammogram',
        ];
        $breast = array_fill_keys($breastKeys, 0);

        $cancerRecords = CervicalCancerScreening::query()
            ->when(true, function ($q) use ($location) {
                $this->applyProfileIdLocationFilter($q, $location, 'profile_id');
            })
            ->get()
            ->filter(function ($rec) use ($startOfSelected, $endOfSelected) {
                $d = $this->parseDateOrNull($rec->date_assessment ?? null);
                return $d && $d->between($startOfSelected, $endOfSelected);
            });

        $tokens = function ($value): array {
            return array_map(fn($p) => strtoupper(trim($p)), explode(',', (string) $value));
        };
        $hasToken = function ($value, string $token) use ($tokens): bool {
            return in_array(strtoupper($token), $tokens($value), true);
        };
        $ageAt = function ($dob, $asOf) {
            $d = $this->parseDateOrNull($dob);
            return ($d && $asOf) ? $d->diffInYears($asOf) : null;
        };

        foreach ($cancerRecords as $rec) {
            $assessedOn = $this->parseDateOrNull($rec->date_assessment ?? null);
            $age = $ageAt($rec->date_of_birth ?? null, $assessedOn);

            // ── E8. Cervical Cancer (women aged 30-65) ──
            if ($age !== null && $age >= 30 && $age <= 65) {
                $done = (int) ($rec->cervical_screening_done ?? -1);
                if (in_array($done, [0, 1, 2, 3], true)) $cervical['screened']++;
                if ($done === 1) $cervical['via']++;
                if ($done === 2) $cervical['papSmear']++;
                if ($done === 3) $cervical['hpvDna']++;
                if ($done === 0) $cervical['assessedOnly']++;

                $result = (int) ($rec->cervical_result ?? 0);
                $linked = (int) ($rec->cervical_linked_to_care ?? 0);

                if ($result === 1) {
                    $cervical['suspicious']++;
                    if (in_array($linked, [1, 2], true)) $cervical['suspiciousLinkedToCare']++;
                    if ($linked === 1) $cervical['suspiciousLinkedTreated']++;
                    if ($linked === 2) $cervical['suspiciousLinkedReferred']++;
                }
                if ($result === 2) {
                    $cervical['precancerous']++;
                    if (in_array($linked, [1, 2], true)) $cervical['precancerousLinkedToCare']++;
                    if ($linked === 1) $cervical['precancerousLinkedTreated']++;
                    if ($linked === 2) $cervical['precancerousLinkedReferred']++;
                }
            }

            // ── E9. Breast Cancer ──
            $riskVal = $rec->breast_risk_assessment ?? '';
            $examType = strtoupper(trim((string) ($rec->breast_exam_type ?? '')));
            $isCbe = $examType === 'CBE';
            $isMammogram = $examType === 'M';
            $isSymptomaticPath = ($hasToken($riskVal, '1') || $hasToken($riskVal, '2')) && $hasToken($riskVal, 'A');
            $isAsymptomaticPath = $hasToken($riskVal, '0') && $hasToken($riskVal, 'B');
            $isRemarkable = (int) ($rec->breast_result ?? 0) === 3;
            $isLinkedToCare = (int) ($rec->breast_linked_to_care ?? 0) === 1;

            // Item 1 has no source condition in the spec; counted as any woman
            // aged 30-69 with a breast assessment record in the period.
            if ($age !== null && $age >= 30 && $age <= 69 && ($isSymptomaticPath || $isAsymptomaticPath)) {
                $breast['seen']++;
            }

            if ($age !== null && $age >= 30 && $age <= 69 && $isSymptomaticPath) {
                $breast['highRiskOrSymptomatic']++;

                if ($isCbe || $isMammogram) {
                    $breast['provided']++;
                    if ($isCbe) $breast['providedCbe']++;
                    if ($isMammogram) $breast['providedMammogram']++;

                    if ($isRemarkable) {
                        $breast['remarkable']++;
                        if ($isCbe) $breast['remarkableCbe']++;
                        if ($isMammogram) $breast['remarkableMammogram']++;

                        if ($isLinkedToCare) {
                            $breast['linkedToCare']++;
                            if ($isCbe) $breast['linkedToCareCbe']++;
                            if ($isMammogram) $breast['linkedToCareMammogram']++;
                        }
                    }
                }
            }

            // Item 6: asymptomatic women aged 50-69
            if ($age !== null && $age >= 50 && $age <= 69 && $isAsymptomaticPath && ($isCbe || $isMammogram)) {
                $breast['asymptomaticScreened']++;
                if ($isCbe) $breast['asymptomaticCbe']++;
                if ($isMammogram) $breast['asymptomaticMammogram']++;
            }
        }

        return response()->json([
            'status' => 'success',
            'period' => $period['periodMeta'],
            'filters' => $location['codes'],
            'data' => [
                'lifestyle2059'  => $lifestyle2059,
                'lifestyle60plus' => $lifestyle60plus,
                'cvd2059'  => $cvd2059,
                'cvd60plus' => $cvd60plus,
                'dm2059'   => $dm2059,
                'dm60plus' => $dm60plus,
                'blindness' => $blindness,
                'seniorImmunization' => $seniorImmunization,
                'geriatricScreening' => $geriatricScreening,
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
        $records = EnvironmentalHealthRecord::query()->get();

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
     * schistosomiasis.mdaGiven5_14/15_19/20_59/60plus assumes item 14's spec
     * text (age_group = "B" repeated for all four sub-rows) is a copy-paste
     * error and maps age groups B/C/D/E respectively — verify against source.
     * leprosy.newlyDetected* assumes item 2's identical 2a/2b/2c spec text
     * (case_history = 0, no age filter) should also be gated by age_group —
     * verify against source.
     * hivAidsSti is sourced from maternal_care_records joined to
     * prenatal_lab_screening_records (via the prenatalLabScreening relation),
     * scoped by household location like the maternal care report, and bumped
     * as female since there is no separate sex column on that table.
     */
    public function infectiousDisease(Request $request)
    {
        $period          = $this->resolveReportPeriod($request);
        $startOfSelected = $period['start'];
        $endOfSelected   = $period['end'];
        $location        = $this->resolveLocationFilters($request);

        $sexEmpty = ['male' => 0, 'female' => 0, 'total' => 0];
        $bump = function (array &$bucket, string $key, ?string $sex) use ($sexEmpty) {
            if (!isset($bucket[$key])) $bucket[$key] = $sexEmpty;
            $bucket[$key]['total']++;
            if ($sk = $this->sexKey($sex)) $bucket[$key][$sk]++;
        };
        $inPeriod = fn (?string $d) => ($p = $this->parseDateOrNull($d)) && $p->between($startOfSelected, $endOfSelected);

        // ── A. Filariasis ─────────────────────────────────────────────────
        $filariasis = [];
        $filRecords = FilariasisRegistry::query()
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
        $rabiesRecords = RabiesRecord::query()
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
        // age_group: A = 1-4, B = 5-14, C = 15-19, D = 20-59, E = 60+
        $schAgeSuffix = ['A' => '1_4', 'B' => '5_14', 'C' => '15_19', 'D' => '20_59', 'E' => '60plus'];
        $schistosomiasis = [];
        $schRecords = SchistosomiasisRegistry::query()->get();

        foreach ($schRecords as $rec) {
            $sex = $rec->sex ?? null;
            $ag = strtoupper(trim((string) ($rec->age_group ?? '')));
            $suffix = in_array($ag, ['A', 'B', 'C', 'D', 'E'], true) ? $schAgeSuffix[$ag] : null;
            $regInPeriod = $inPeriod($rec->date_of_registration ?? null);

            if ($regInPeriod) {
                // 1 / 1a-1e. Patients Seen
                $bump($schistosomiasis, 'patientsSeen', $sex);
                if ($suffix) $bump($schistosomiasis, "patientsSeen{$suffix}", $sex);

                $withSigns = $this->truthy($rec->with_signs_symptoms ?? null);
                $clinicalTreated = $this->truthy($rec->clinical_first_treatment_given ?? null) || $this->truthy($rec->clinical_retreatment ?? null);
                $clinicalCured = $this->truthy($rec->clinical_cured ?? null);
                $complicated = $this->truthy($rec->complicated ?? null);
                // "complicated = 0" per the spec means explicitly recorded as
                // non-complicated, not merely absent/null.
                $nonComplicated = isset($rec->complicated) && !$this->truthy($rec->complicated);
                $confirmedRetreated = $this->truthy($rec->confirmed_retreatment ?? null);
                $confirmedCured = $this->truthy($rec->confirmed_cured ?? null);

                // 2 / 2a-2e. Clinical/Suspected Cases Seen
                if ($withSigns) {
                    $bump($schistosomiasis, 'suspectedCases', $sex);
                    if ($suffix) $bump($schistosomiasis, "suspectedCases{$suffix}", $sex);
                }

                // 3 / 3a-3d. Clinical/Suspected Cases Treated, by age (5-14 and up only)
                if ($clinicalTreated) {
                    $bump($schistosomiasis, 'treatedByAge', $sex);
                    if (in_array($ag, ['B', 'C', 'D', 'E'], true)) $bump($schistosomiasis, "treated{$suffix}", $sex);
                }
                // 4a-4b. Treated by treatment type (not age-gated)
                if ($this->truthy($rec->clinical_first_treatment_given ?? null)) $bump($schistosomiasis, 'treatedFirst', $sex);
                if ($this->truthy($rec->clinical_retreatment ?? null)) $bump($schistosomiasis, 'treatedRetreatment', $sex);

                // 5 / 5a-5d. Cured, by age (5-14 and up only)
                if ($clinicalCured) {
                    $bump($schistosomiasis, 'cured', $sex);
                    if (in_array($ag, ['B', 'C', 'D', 'E'], true)) $bump($schistosomiasis, "cured{$suffix}", $sex);
                }

                // 6 / 6a-6e. Confirmed COMPLICATED, by age
                if ($complicated) {
                    $bump($schistosomiasis, 'complicated', $sex);
                    if ($suffix) $bump($schistosomiasis, "complicated{$suffix}", $sex);
                }
                // 7 / 7a-7e. Confirmed NON-COMPLICATED, by age
                if ($nonComplicated) {
                    $bump($schistosomiasis, 'nonComplicated', $sex);
                    if ($suffix) $bump($schistosomiasis, "nonComplicated{$suffix}", $sex);
                }

                // 8 / 8a-8d. Confirmed COMPLICATED TREATED, by age (5-14 and up only)
                if ($complicated && $confirmedRetreated) {
                    $bump($schistosomiasis, 'complicatedTreated', $sex);
                    if (in_array($ag, ['B', 'C', 'D', 'E'], true)) $bump($schistosomiasis, "complicatedTreated{$suffix}", $sex);
                }
                // 9 / 9a-9d. Confirmed NON-COMPLICATED TREATED, by age (5-14 and up only)
                if ($nonComplicated && $confirmedRetreated) {
                    $bump($schistosomiasis, 'nonComplicatedTreated', $sex);
                    if (in_array($ag, ['B', 'C', 'D', 'E'], true)) $bump($schistosomiasis, "nonComplicatedTreated{$suffix}", $sex);
                }
                // 10a-10b. Confirmed Treated by treatment (not age-gated)
                if ($this->truthy($rec->confirmed_first_treatment_given ?? null)) $bump($schistosomiasis, 'confirmedTreatedFirst', $sex);
                if ($confirmedRetreated) $bump($schistosomiasis, 'confirmedTreatedRetreatment', $sex);

                // 11 / 11a-11d. Confirmed COMPLICATED CURED, by age (5-14 and up only)
                if ($complicated && $confirmedCured) {
                    $bump($schistosomiasis, 'complicatedCured', $sex);
                    if (in_array($ag, ['B', 'C', 'D', 'E'], true)) $bump($schistosomiasis, "complicatedCured{$suffix}", $sex);
                }
                // 12 / 12a-12d. Confirmed NON-COMPLICATED CURED, by age (5-14 and up only)
                if ($nonComplicated && $confirmedCured) {
                    $bump($schistosomiasis, 'nonComplicatedCured', $sex);
                    if (in_array($ag, ['B', 'C', 'D', 'E'], true)) $bump($schistosomiasis, "nonComplicatedCured{$suffix}", $sex);
                }

                // 14 / 14a-14d. Dewormed with Praziquantel during MDA, by age (5-14
                // and up). NOTE: the spec repeats age_group = "B" for all four
                // sub-rows (14a-14d), which looks like a copy-paste error given the
                // labels are 5-14/15-19/20-59/60+ — mapped to B/C/D/E respectively,
                // consistent with every other item in this section. Verify against
                // the source spec if that's not the intent.
                if ($this->truthy($rec->mda_given ?? null) || !empty($rec->mda_date_given)) {
                    $bump($schistosomiasis, 'mdaGiven', $sex);
                    if (in_array($ag, ['B', 'C', 'D', 'E'], true)) $bump($schistosomiasis, "mdaGiven{$suffix}", $sex);
                }
            }

            // 13 / 13a-13e. Referred to Hospital — gated by date_referred_to_hospital
            // falling in the reporting period, independent of date_of_registration.
            $referredDate = $this->parseDateOrNull($rec->date_referred_to_hospital ?? null);
            if ($referredDate && $referredDate->between($startOfSelected, $endOfSelected)) {
                $bump($schistosomiasis, 'referredToHospital', $sex);
                if ($suffix) $bump($schistosomiasis, "referredToHospital{$suffix}", $sex);
            }
        }

        // ── D. Soil-Transmitted Helminthiasis (STH) ──────────────────────
        // age_classification: A = 1-4, B = 5-14, C = 15-19, D = 20-59, E = 60+
        // residency: 1 = Resident, 0 = Non-Resident
        // screening_result: 1 = Suspected, 2 = Confirmed
        // treatment_given: 1 or 2 = treated
        // january_mda_modality / july_mda_modality: 1 = School-Based, 2 = Community
        $sthAgeSuffix = ['A' => '1_4', 'B' => '5_14', 'C' => '15_19', 'D' => '20_59', 'E' => '60plus'];
        $sth = [];
        $sthRecords = SthRegistryRecord::query()
            ->get()
            ->filter(fn ($r) => $inPeriod($r->date_of_registration ?? null));

        foreach ($sthRecords as $rec) {
            $sex = $rec->sex ?? null;
            $ac = strtoupper(trim((string) ($rec->age_classification ?? '')));
            $suffix = isset($sthAgeSuffix[$ac]) ? $sthAgeSuffix[$ac] : null;

            // residency is 1/0, so a plain truthy() check can't distinguish
            // "non-resident" (0) from "not recorded" (null).
            $residency = $rec->residency ?? null;
            $isResident    = $residency !== null && (int) $residency === 1;
            $isNonResident = $residency !== null && (int) $residency === 0;

            $result = (int) ($rec->screening_result ?? 0);
            $isSuspected = $result === 1;
            $isConfirmed = $result === 2;
            $isTreated = in_array((int) ($rec->treatment_given ?? 0), [1, 2], true);

            $janModality = (int) ($rec->january_mda_modality ?? 0);
            $julModality = (int) ($rec->july_mda_modality ?? 0);

            // 1 / 1a-1e. Screened for STH (any record registered in the period)
            $bump($sth, 'screened', $sex);
            if ($suffix) $bump($sth, "screened{$suffix}", $sex);

            // 2 / 2a-2b. Suspected by place of diagnosis
            // NOTE: the spec's 2a/2b conditions omit screening_result, so items 2
            // and 3 would disagree; residency alone is not "suspected". Filtered
            // by screening_result = 1 here so 2 and 3 reconcile — verify intent.
            if ($isSuspected) {
                $bump($sth, 'suspected', $sex);
                if ($isResident)    $bump($sth, 'suspectedResident', $sex);
                if ($isNonResident) $bump($sth, 'suspectedNonResident', $sex);
                // 3 / 3a-3e. Suspected by age group
                if ($suffix) $bump($sth, "suspected{$suffix}", $sex);
            }

            // 4 / 4a-4b and 5 / 5a-5e. Confirmed by place of diagnosis / age group
            if ($isConfirmed) {
                $bump($sth, 'confirmed', $sex);
                if ($isResident)    $bump($sth, 'confirmedResident', $sex);
                if ($isNonResident) $bump($sth, 'confirmedNonResident', $sex);
                if ($suffix) $bump($sth, "confirmed{$suffix}", $sex);
            }

            // 6 / 6a-6b and 7 / 7a-7e. Treated by place of diagnosis / age group
            if ($isTreated) {
                $bump($sth, 'treated', $sex);
                if ($isResident)    $bump($sth, 'treatedResident', $sex);
                if ($isNonResident) $bump($sth, 'treatedNonResident', $sex);
                if ($suffix) $bump($sth, "treated{$suffix}", $sex);
            }

            // 8 / 9. 1-4 year olds dewormed during January / July MDA, by modality
            if ($ac === 'A') {
                if ($janModality === 1 || $janModality === 2) {
                    $bump($sth, 'januaryMda1_4', $sex);
                    if ($janModality === 1) $bump($sth, 'januaryMda1_4School', $sex);
                    if ($janModality === 2) $bump($sth, 'januaryMda1_4Community', $sex);
                }
                if ($julModality === 1 || $julModality === 2) {
                    $bump($sth, 'julyMda1_4', $sex);
                    if ($julModality === 1) $bump($sth, 'julyMda1_4School', $sex);
                    if ($julModality === 2) $bump($sth, 'julyMda1_4Community', $sex);
                }
            }

            // 10 / 11. 5-14 year olds dewormed during January / July MDA, by modality
            if ($ac === 'B') {
                if ($janModality === 1 || $janModality === 2) {
                    $bump($sth, 'januaryMda5_14', $sex);
                    if ($janModality === 1) $bump($sth, 'januaryMda5_14School', $sex);
                    if ($janModality === 2) $bump($sth, 'januaryMda5_14Community', $sex);
                }
                if ($julModality === 1 || $julModality === 2) {
                    $bump($sth, 'julyMda5_14', $sex);
                    if ($julModality === 1) $bump($sth, 'julyMda5_14School', $sex);
                    if ($julModality === 2) $bump($sth, 'julyMda5_14Community', $sex);
                }
            }

            // 12 / 12a-12b. 15-19 year olds dewormed during January / July MDA
            if ($ac === 'C') {
                $janDewormed = ($janModality === 1 || $janModality === 2);
                $julDewormed = ($julModality === 1 || $julModality === 2);
                if ($janDewormed) $bump($sth, 'adolescentJanuaryMda', $sex);
                if ($julDewormed) $bump($sth, 'adolescentJulyMda', $sex);
                if ($janDewormed || $julDewormed) $bump($sth, 'adolescentMda', $sex);
            }
        }

        // ── E. Leprosy ────────────────────────────────────────────────────
        // age_group: A = 0-14, B = 15-18, C = 19+
        $lepAgeSuffix = ['A' => '0_14', 'B' => '15_18', 'C' => '19plus'];
        $leprosy = [];
        $lepRecords = LeprosyRegistry::query()
            ->get()
            ->filter(fn ($r) => $inPeriod($r->date_of_registration ?? null));

        foreach ($lepRecords as $rec) {
            $sex = $rec->sex ?? null;
            $ag = strtoupper(trim((string) ($rec->age_group ?? '')));
            $suffix = $lepAgeSuffix[$ag] ?? null;

            // 1 / 1a-1c. Registered cases
            $bump($leprosy, 'registered', $sex);
            if ($suffix) $bump($leprosy, "registered{$suffix}", $sex);

            // 2 / 2a-2c. Newly detected cases.
            // NOTE: the spec gives the identical condition (case_history = 0, no
            // age filter) for 2a/2b/2c, which can't distinguish the three
            // sub-rows. Assumed to mean case_history = 0 AND age_group = A/B/C
            // respectively, consistent with every other item here — verify
            // against source.
            if ((int) ($rec->case_history ?? -1) === 0) {
                $bump($leprosy, 'newlyDetected', $sex);
                if ($suffix) $bump($leprosy, "newlyDetected{$suffix}", $sex);
            }

            // 3 / 3a-3c. Confirmed cases
            if ($this->truthy($rec->confirmed_case ?? null)) {
                $bump($leprosy, 'confirmed', $sex);
                if ($suffix) $bump($leprosy, "confirmed{$suffix}", $sex);
            }

            // 4 / 4a-4c. Completed fixed-duration MDT
            if ($this->truthy($rec->completed_fixed_mdt ?? null)) {
                $bump($leprosy, 'completedMdt', $sex);
                if ($suffix) $bump($leprosy, "completedMdt{$suffix}", $sex);
            }

            // 5 / 5a-5c. Confirmed cases treated (beyond fixed-duration MDT) —
            // distinct from item 4; previously these two were merged.
            if ($this->truthy($rec->beyond_fixed_mdt ?? null)) {
                $bump($leprosy, 'treated', $sex);
                if ($suffix) $bump($leprosy, "treated{$suffix}", $sex);
            }

            // 6 / 6a-6c. Newly detected with Grade 2 Disability
            if ($this->truthy($rec->grade2_disability ?? null)) {
                $bump($leprosy, 'grade2Disability', $sex);
                if ($suffix) $bump($leprosy, "grade2Disability{$suffix}", $sex);
            }
        }

        // ── F. HIV-AIDS/STI (prenatal_lab_screening_records via maternal_care_records) ──
        // maternal_care_records.ageGroup: "A - 10-14 years old" / "B - 15-19 years old" / "C - 20-49 years old"
        $stiAgeSuffix = ['A' => '10_14', 'B' => '15_19', 'C' => '20_49'];
        $hivAidsSti = [];
        $stiRecords = MaternalCareRecord::query()
            ->with(['prenatalLabScreening'])
            ->whereHas('householdProfile', function ($q) use ($location) {
                $this->applyHouseholdLocationFilter($q, $location);
            })
            ->get();

        foreach ($stiRecords as $mrec) {
            $lab = $mrec->prenatalLabScreening;
            if (!$lab) continue;

            $agLetter = strtoupper(trim(explode(' - ', (string) ($mrec->ageGroup ?? ''))[0] ?? ''));
            $suffix = $stiAgeSuffix[$agLetter] ?? null;
            // All records here are pregnant women; there is no separate sex
            // column on maternal_care_records, so counts are bumped as female.
            $sex = 'female';

            $syphilisDate = $this->parseDateOrNull($lab->syphilisDate ?? null);
            if ($syphilisDate && $syphilisDate->between($startOfSelected, $endOfSelected)) {
                $bump($hivAidsSti, 'syphilisScreened', $sex);
                if ($suffix) $bump($hivAidsSti, "syphilisScreened{$suffix}", $sex);

                if ($this->contains($lab->syphilisResult ?? null, 'reactive')) {
                    $bump($hivAidsSti, 'syphilisReactive', $sex);
                    if ($suffix) $bump($hivAidsSti, "syphilisReactive{$suffix}", $sex);
                }
                if ($this->truthy($lab->syphilisTreatment ?? null) || $this->contains($lab->syphilisTreatment ?? null, 'yes')) {
                    $bump($hivAidsSti, 'syphilisTreated', $sex);
                    if ($suffix) $bump($hivAidsSti, "syphilisTreated{$suffix}", $sex);
                }
            }

            $hivDate = $this->parseDateOrNull($lab->hivDate ?? null);
            if ($hivDate && $hivDate->between($startOfSelected, $endOfSelected)) {
                $bump($hivAidsSti, 'hivScreened', $sex);
                if ($suffix) $bump($hivAidsSti, "hivScreened{$suffix}", $sex);

                if ($this->contains($lab->hivResult ?? null, 'reactive')) {
                    $bump($hivAidsSti, 'hivReactive', $sex);
                    if ($suffix) $bump($hivAidsSti, "hivReactive{$suffix}", $sex);
                }
            }

            $hepBDate = $this->parseDateOrNull($lab->hepBDate ?? null);
            if ($hepBDate && $hepBDate->between($startOfSelected, $endOfSelected)) {
                $bump($hivAidsSti, 'hepBScreened', $sex);
                if ($suffix) $bump($hivAidsSti, "hepBScreened{$suffix}", $sex);

                if ($this->contains($lab->hepBResult ?? null, 'reactive')) {
                    $bump($hivAidsSti, 'hepBReactive', $sex);
                    if ($suffix) $bump($hivAidsSti, "hepBReactive{$suffix}", $sex);
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'period' => $period['periodMeta'],
            'data' => [
                'filariasis'      => $filariasis,
                'rabies'          => $rabies,
                'schistosomiasis' => $schistosomiasis,
                'sth'             => $sth,
                'leprosy'         => $leprosy,
                'hivAidsSti'      => $hivAidsSti,
            ],
        ]);
    }

    /**
     * Resolves the reporting period from the request. Accepts either a
     * `quarter` (1-4) + `year` pair for quarterly reports (Q1AllPrograms.tsx)
     * or a `month` (1-12) + `year` pair for monthly reports (M1AllPrograms.tsx,
     * the default when neither is supplied). Returns Carbon start/end bounds
     * for the selected period and the immediately preceding period (used for
     * "previous month/quarter" ledger comparisons), plus a small metadata
     * array echoed back in each endpoint's `period` response key.
     */
    private function resolveReportPeriod(Request $request): array
    {
        $year = (int) $request->input('year', now()->year);

        if ($request->boolean('annual')) {
            $start = Carbon::create($year, 1, 1)->startOfYear();
            $end   = Carbon::create($year, 12, 31)->endOfYear();

            $previousStart = Carbon::create($year - 1, 1, 1)->startOfYear();
            $previousEnd   = Carbon::create($year - 1, 12, 31)->endOfYear();

            return [
                'start'         => $start,
                'end'           => $end,
                'previousStart' => $previousStart,
                'previousEnd'   => $previousEnd,
                'year'          => $year,
                'periodMeta'    => [
                    'label' => 'Annual',
                    'year'  => $year,
                ],
            ];
        }

        if ($request->filled('quarter')) {
            $quarter = (int) $request->input('quarter');
            $quarter = max(1, min(4, $quarter ?: 1));

            $startMonth = (($quarter - 1) * 3) + 1;
            $start = Carbon::create($year, $startMonth, 1)->startOfMonth();
            $end   = $start->copy()->addMonths(2)->endOfMonth();

            $previousStart = $start->copy()->subMonths(3);
            $previousEnd   = $previousStart->copy()->addMonths(2)->endOfMonth();

            $quarterLabels = [
                1 => '1st Quarter (Jan - Mar)',
                2 => '2nd Quarter (Apr - Jun)',
                3 => '3rd Quarter (Jul - Sep)',
                4 => '4th Quarter (Oct - Dec)',
            ];

            return [
                'start'         => $start,
                'end'           => $end,
                'previousStart' => $previousStart,
                'previousEnd'   => $previousEnd,
                'year'          => $start->year,
                'periodMeta'    => [
                    'quarter' => $quarter,
                    'label'   => $quarterLabels[$quarter],
                    'year'    => $start->year,
                ],
            ];
        }

        $month = (int) $request->input('month', now()->month);
        $selectedMonth = Carbon::create($year, $month, 1);
        $start = $selectedMonth->copy()->startOfMonth();
        $end   = $selectedMonth->copy()->endOfMonth();

        $previousMonth = $selectedMonth->copy()->subMonth();
        $previousStart = $previousMonth->copy()->startOfMonth();
        $previousEnd   = $previousMonth->copy()->endOfMonth();

        return [
            'start'         => $start,
            'end'           => $end,
            'previousStart' => $previousStart,
            'previousEnd'   => $previousEnd,
            'year'          => $selectedMonth->year,
            'periodMeta'    => [
                'month' => $selectedMonth->format('F'),
                'year'  => $selectedMonth->year,
            ],
        ];
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
        if ($age === 0) {
            return '10-14';
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