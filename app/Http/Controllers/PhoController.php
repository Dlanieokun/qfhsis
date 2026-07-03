<?php

namespace App\Http\Controllers;

use App\Models\HouseholdProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PhoController extends Controller
{
    /**
     * PHO View showing the M1/Q1/M2/A1 report tabs.
     */
    public function pho(): Response
    {
        return Inertia::render('fhsis/pho', [
            'familyPlanning' => $this->getFamilyPlanningData(),
            'maternalCare' => $this->getMaternalCareData(),
            'childCare' => $this->getChildCareData(),
        ]);
    }

    public function nurse(): Response
    {
        return Inertia::render('fhsis/PublicNurse');
    }

    /**
     * Builds the Section A (Family Planning) figures for the M1 form from
     * household_profiles.fpMethodUsed / dob.
     *
     * Covered:
     *  - A1 "Demand Satisfied": WRA (10-49) currently using a modern method,
     *    grouped by age bracket.
     *  - A2 "Current Users (End of the Month)" column, grouped by method +
     *    age bracket.
     *
     * NOT covered yet (left blank in the UI on purpose, needs month-scoped
     * queries against family_planning_records / family_planning_drop_outs):
     *  - Current Users (Beginning of the Month)
     *  - Acceptors
     *  - Drop-outs
     *  - New Acceptors
     */
    private function getFamilyPlanningData(): array
    {
        $methodKeys = [
            'btl', 'nsv', 'condom', 'pills-pop', 'pills-coc', 'injectable',
            'implant-interval', 'implant-pp', 'iud-interval', 'iud-pp',
            'lam', 'bbt', 'cmm', 'stm', 'sdm',
        ];

        $emptyBrackets = ['10-14' => 0, '15-19' => 0, '20-49' => 0, 'total' => 0];

        $demandSatisfied = $emptyBrackets;
        $currentUsersByMethod = [];
        foreach ($methodKeys as $key) {
            $currentUsersByMethod[$key] = $emptyBrackets;
        }

        HouseholdProfile::query()
            ->whereNotNull('dob')
            ->whereNotNull('fpMethodUsed')
            ->where('fpMethodUsed', '!=', '')
            ->where(function ($q) {
                $q->where('sex', 'Female')->orWhere('sex', 'F');
            })
            ->select(['id', 'dob', 'sex', 'fpMethodUsed'])
            ->chunk(500, function ($profiles) use (&$demandSatisfied, &$currentUsersByMethod) {
                foreach ($profiles as $profile) {
                    $bracket = $this->ageBracket($profile->dob);
                    if (! $bracket) {
                        continue;
                    }

                    $demandSatisfied[$bracket]++;
                    $demandSatisfied['total']++;

                    $method = strtolower($profile->fpMethodUsed);

                    if (str_contains($method, 'btl')) {
                        $this->tally($currentUsersByMethod['btl'], $bracket);
                    }
                    if (str_contains($method, 'nsv')) {
                        $this->tally($currentUsersByMethod['nsv'], $bracket);
                    }
                    if (str_contains($method, 'condom')) {
                        $this->tally($currentUsersByMethod['condom'], $bracket);
                    }
                    if (str_contains($method, 'pop')) {
                        $this->tally($currentUsersByMethod['pills-pop'], $bracket);
                    }
                    if (str_contains($method, 'coc')) {
                        $this->tally($currentUsersByMethod['pills-coc'], $bracket);
                    }
                    if (str_contains($method, 'dmpa') || str_contains($method, 'injectable')) {
                        $this->tally($currentUsersByMethod['injectable'], $bracket);
                    }
                    if (str_contains($method, 'implant') && str_contains($method, 'interval')) {
                        $this->tally($currentUsersByMethod['implant-interval'], $bracket);
                    }
                    if (str_contains($method, 'implant') && str_contains($method, 'pp')) {
                        $this->tally($currentUsersByMethod['implant-pp'], $bracket);
                    }
                    if (str_contains($method, 'iud') && str_contains($method, 'interval')) {
                        $this->tally($currentUsersByMethod['iud-interval'], $bracket);
                    }
                    if (str_contains($method, 'iud') && str_contains($method, 'pp')) {
                        $this->tally($currentUsersByMethod['iud-pp'], $bracket);
                    }
                    if (str_contains($method, 'lam')) {
                        $this->tally($currentUsersByMethod['lam'], $bracket);
                    }
                    if (str_contains($method, 'bbt')) {
                        $this->tally($currentUsersByMethod['bbt'], $bracket);
                    }
                    if (str_contains($method, 'cmm')) {
                        $this->tally($currentUsersByMethod['cmm'], $bracket);
                    }
                    if (str_contains($method, 'stm')) {
                        $this->tally($currentUsersByMethod['stm'], $bracket);
                    }
                    if (str_contains($method, 'sdm')) {
                        $this->tally($currentUsersByMethod['sdm'], $bracket);
                    }
                }
            });

        return [
            'demandSatisfied' => $demandSatisfied,
            'currentUsersByMethod' => $currentUsersByMethod,
        ];
    }

    private function tally(array &$bucket, string $bracket): void
    {
        $bucket[$bracket]++;
        $bucket['total']++;
    }

    /**
     * Builds Section B (Maternal Care and Services) figures for the M1 form.
     *
     * Data sources:
     *  - maternal_care_records            (age bracket, BMI status)
     *  - prenatal_8anc_records            (8ANC completion, BP monitoring, danger signs)
     *  - prenatal_immunization_records    (Td dosing)
     *  - prenatal_supplementation_records (IFA / MMS / Calcium / deworming completion)
     *  - prenatal_lab_screening_records   (CBC → anemia, GDM screening)
     *  - intrapartum_records              (deliveries, attendant, facility, type, outcome, birth weight)
     *  - postpartum_records               (4PNC completion, supplementation, BP monitoring)
     *
     * NOTE ON GAPS: the current schema doesn't distinguish Resident vs.
     * TRANS-IN/TRANS-OUT clients, nor "first trimester" specifically for the
     * BMI assessment, nor is there a dedicated field for "diagnosed with
     * anemia" separate from the CBC free-text result. Those sub-indicators
     * (a1/a2/b1/b2/b3 rows, resident splits, etc.) are left for manual entry
     * in the UI since they can't be reliably derived yet. Everything else
     * below is computed from real records.
     */
    private function getMaternalCareData(): array
    {
        $emptyBrackets = ['10-14' => 0, '15-19' => 0, '20-49' => 0, 'total' => 0];
        $emptySex = ['male' => 0, 'female' => 0, 'total' => 0];

        // ---- Pull maternal_care_records and index by id for age lookups ----
        $mothers = DB::table('maternal_care_records')
            ->select('id', 'age', 'birthDate', 'bmiStatus')
            ->get()
            ->keyBy('id');

        $ageBrackets = [];
        foreach ($mothers as $id => $mother) {
            $ageBrackets[$id] = $this->maternalAgeBracket($mother->age, $mother->birthDate);
        }

        $prenatal = [
            'anc8Completed' => $emptyBrackets,
            'nutritionAssessed' => $emptyBrackets,
            'nutritionNormal' => $emptyBrackets,
            'nutritionLow' => $emptyBrackets,
            'nutritionHigh' => $emptyBrackets,
            'td2PlusFirstPregnancy' => $emptyBrackets,
            'td2Plus' => $emptyBrackets,
            'ifaCompleted' => $emptyBrackets,
            'mmCompleted' => $emptyBrackets,
            'ccCompleted' => $emptyBrackets,
            'anemiaScreened' => $emptyBrackets,
            'anemiaDiagnosed' => $emptyBrackets,
            'gdmScreened' => $emptyBrackets,
            'gdmDiagnosed' => $emptyBrackets,
            'dewormed' => $emptyBrackets,
            'bpMeasured' => $emptyBrackets,
            'highBpOrDanger' => $emptyBrackets,
            'referred' => $emptyBrackets,
        ];

        // BMI / nutrition status comes straight off maternal_care_records
        foreach ($mothers as $id => $mother) {
            $bracket = $ageBrackets[$id] ?? null;
            if (! $bracket || ! $mother->bmiStatus) {
                continue;
            }

            $status = strtolower($mother->bmiStatus);
            $this->tally($prenatal['nutritionAssessed'], $bracket);

            if (str_contains($status, 'normal')) {
                $this->tally($prenatal['nutritionNormal'], $bracket);
            } elseif (str_contains($status, 'low') || str_contains($status, 'under')) {
                $this->tally($prenatal['nutritionLow'], $bracket);
            } elseif (str_contains($status, 'high') || str_contains($status, 'over')) {
                $this->tally($prenatal['nutritionHigh'], $bracket);
            }
        }

        // 8ANC completion + BP/danger sign monitoring
        DB::table('prenatal_8anc_records')
            ->select('maternalRecordId', 'completed8Anc', 'highBp', 'dangerSigns', 'highBpReferred',
                'visit1Bp', 'visit2Bp', 'visit3Bp', 'visit4Bp', 'visit5Bp', 'visit6Bp', 'visit7Bp', 'visit8Bp')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (&$prenatal, $ageBrackets) {
                foreach ($rows as $row) {
                    $bracket = $ageBrackets[$row->maternalRecordId] ?? null;
                    if (! $bracket) {
                        continue;
                    }

                    if ((int) $row->completed8Anc === 1) {
                        $this->tally($prenatal['anc8Completed'], $bracket);
                    }

                    $hasBp = false;
                    for ($i = 1; $i <= 8; $i++) {
                        if (! empty($row->{"visit{$i}Bp"})) {
                            $hasBp = true;
                            break;
                        }
                    }
                    if ($hasBp) {
                        $this->tally($prenatal['bpMeasured'], $bracket);
                    }

                    if ((int) $row->highBp === 1 || (int) $row->dangerSigns === 1) {
                        $this->tally($prenatal['highBpOrDanger'], $bracket);
                    }
                    if ((int) $row->highBpReferred === 1) {
                        $this->tally($prenatal['referred'], $bracket);
                    }
                }
            });

        // Td dosing — 2 doses (1st pregnancy) vs. 3+ doses (Td2Plus)
        DB::table('prenatal_immunization_records')
            ->select('maternalRecordId', 'td1Date', 'td2Date', 'td3Date')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (&$prenatal, $ageBrackets) {
                foreach ($rows as $row) {
                    $bracket = $ageBrackets[$row->maternalRecordId] ?? null;
                    if (! $bracket) {
                        continue;
                    }

                    if ($row->td1Date && $row->td2Date) {
                        $this->tally($prenatal['td2PlusFirstPregnancy'], $bracket);
                    }
                    if ($row->td3Date) {
                        $this->tally($prenatal['td2Plus'], $bracket);
                    }
                }
            });

        // Supplementation + deworming (note: FK column is snake_case here)
        DB::table('prenatal_supplementation_records')
            ->select('maternal_record_id', 'completed_ifa', 'completed_mm', 'completed_cc', 'received_deworming')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (&$prenatal, $ageBrackets) {
                foreach ($rows as $row) {
                    $bracket = $ageBrackets[$row->maternal_record_id] ?? null;
                    if (! $bracket) {
                        continue;
                    }

                    if ($row->completed_ifa) {
                        $this->tally($prenatal['ifaCompleted'], $bracket);
                    }
                    if ($row->completed_mm) {
                        $this->tally($prenatal['mmCompleted'], $bracket);
                    }
                    if ($row->completed_cc) {
                        $this->tally($prenatal['ccCompleted'], $bracket);
                    }
                    if ($row->received_deworming) {
                        $this->tally($prenatal['dewormed'], $bracket);
                    }
                }
            });

        // Anemia (via CBC) + GDM screening
        DB::table('prenatal_lab_screening_records')
            ->select('maternalRecordId', 'cbcDate', 'cbcResult', 'gdmDate', 'gdmResult')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (&$prenatal, $ageBrackets) {
                foreach ($rows as $row) {
                    $bracket = $ageBrackets[$row->maternalRecordId] ?? null;
                    if (! $bracket) {
                        continue;
                    }

                    if ($row->cbcDate) {
                        $this->tally($prenatal['anemiaScreened'], $bracket);
                        if ($row->cbcResult && str_contains(strtolower($row->cbcResult), 'anemi')) {
                            $this->tally($prenatal['anemiaDiagnosed'], $bracket);
                        }
                    }

                    if ($row->gdmDate) {
                        $this->tally($prenatal['gdmScreened'], $bracket);
                        if ($row->gdmResult && str_contains(strtolower($row->gdmResult), 'positive')) {
                            $this->tally($prenatal['gdmDiagnosed'], $bracket);
                        }
                    }
                }
            });

        // ---- Intrapartum (newborn sex is the breakdown dimension here) ----
        $intrapartum = [
            'totalDeliveries' => $emptySex,
            'attendantPhysician' => $emptySex,
            'attendantNurse' => $emptySex,
            'attendantMidwife' => $emptySex,
            'facilityPublic' => $emptySex,
            'facilityPrivate' => $emptySex,
            'deliveryVaginal' => $emptySex,
            'deliveryCesarean' => $emptySex,
            'deliveryCombined' => $emptySex,
            'outcomeFullTerm' => $emptySex,
            'outcomePreTerm' => $emptySex,
            'outcomeFetalDeath' => $emptySex,
            'outcomeAbortion' => $emptySex,
            'birthWeightNormal' => $emptySex,
            'birthWeightLow' => $emptySex,
            'birthWeightUnknown' => $emptySex,
        ];

        DB::table('intrapartum_records')
            ->select('sex', 'attendantAtBirth', 'placeOfDelivery', 'deliveryType', 'deliveryOutcome', 'weightClassification')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (&$intrapartum) {
                foreach ($rows as $row) {
                    $sexKey = $this->sexKey($row->sex);
                    if (! $sexKey) {
                        continue;
                    }

                    $this->tallySex($intrapartum['totalDeliveries'], $sexKey);

                    $attendant = strtolower((string) $row->attendantAtBirth);
                    if (str_contains($attendant, 'physician') || str_contains($attendant, 'doctor')) {
                        $this->tallySex($intrapartum['attendantPhysician'], $sexKey);
                    } elseif (str_contains($attendant, 'nurse')) {
                        $this->tallySex($intrapartum['attendantNurse'], $sexKey);
                    } elseif (str_contains($attendant, 'midwife')) {
                        $this->tallySex($intrapartum['attendantMidwife'], $sexKey);
                    }

                    $facility = strtolower((string) $row->placeOfDelivery);
                    if (str_contains($facility, 'public') || str_contains($facility, 'government')) {
                        $this->tallySex($intrapartum['facilityPublic'], $sexKey);
                    } elseif (str_contains($facility, 'private')) {
                        $this->tallySex($intrapartum['facilityPrivate'], $sexKey);
                    }

                    $type = strtolower((string) $row->deliveryType);
                    if (str_contains($type, 'combined')) {
                        $this->tallySex($intrapartum['deliveryCombined'], $sexKey);
                    } elseif (str_contains($type, 'cesarean') || str_contains($type, 'caesarean') || str_contains($type, 'c-section')) {
                        $this->tallySex($intrapartum['deliveryCesarean'], $sexKey);
                    } elseif (str_contains($type, 'vaginal')) {
                        $this->tallySex($intrapartum['deliveryVaginal'], $sexKey);
                    }

                    $outcome = strtolower((string) $row->deliveryOutcome);
                    if (str_contains($outcome, 'full')) {
                        $this->tallySex($intrapartum['outcomeFullTerm'], $sexKey);
                    } elseif (str_contains($outcome, 'pre-term') || str_contains($outcome, 'preterm')) {
                        $this->tallySex($intrapartum['outcomePreTerm'], $sexKey);
                    } elseif (str_contains($outcome, 'fetal death') || str_contains($outcome, 'stillbirth')) {
                        $this->tallySex($intrapartum['outcomeFetalDeath'], $sexKey);
                    } elseif (str_contains($outcome, 'abortion') || str_contains($outcome, 'miscarriage')) {
                        $this->tallySex($intrapartum['outcomeAbortion'], $sexKey);
                    }

                    $weightClass = strtolower((string) $row->weightClassification);
                    if (str_contains($weightClass, 'normal')) {
                        $this->tallySex($intrapartum['birthWeightNormal'], $sexKey);
                    } elseif (str_contains($weightClass, 'low')) {
                        $this->tallySex($intrapartum['birthWeightLow'], $sexKey);
                    } else {
                        $this->tallySex($intrapartum['birthWeightUnknown'], $sexKey);
                    }
                }
            });

        // ---- Postpartum (age bracket comes from the parent maternal record) ----
        $postpartum = [
            'pnc4Completed' => $emptyBrackets,
            'ifaCompleted' => $emptyBrackets,
            'vitACompleted' => $emptyBrackets,
            'bpMeasured' => $emptyBrackets,
            'highBpOrDanger' => $emptyBrackets,
            'referred' => $emptyBrackets,
        ];

        DB::table('postpartum_records')
            ->select('maternalRecordId', 'visit24hDate', 'visit1wDate', 'visit2_4wDate', 'visit4_6wDate',
                'completedIfa', 'completedVitA', 'bpSys24h', 'bpSys1w', 'bpSys2_4w', 'bpSys4_6w',
                'highBpGeneral', 'dangerSignsGeneral', 'referredGeneral')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (&$postpartum, $ageBrackets) {
                foreach ($rows as $row) {
                    $bracket = $ageBrackets[$row->maternalRecordId] ?? null;
                    if (! $bracket) {
                        continue;
                    }

                    if ($row->visit24hDate && $row->visit1wDate && $row->visit2_4wDate && $row->visit4_6wDate) {
                        $this->tally($postpartum['pnc4Completed'], $bracket);
                    }
                    if ($row->completedIfa) {
                        $this->tally($postpartum['ifaCompleted'], $bracket);
                    }
                    if ($row->completedVitA) {
                        $this->tally($postpartum['vitACompleted'], $bracket);
                    }

                    $bpMeasured = $row->bpSys24h || $row->bpSys1w || $row->bpSys2_4w || $row->bpSys4_6w;
                    if ($bpMeasured) {
                        $this->tally($postpartum['bpMeasured'], $bracket);
                    }
                    if ($row->highBpGeneral || $row->dangerSignsGeneral) {
                        $this->tally($postpartum['highBpOrDanger'], $bracket);
                    }
                    if ($row->referredGeneral) {
                        $this->tally($postpartum['referred'], $bracket);
                    }
                }
            });

        return [
            'prenatal' => $prenatal,
            'intrapartum' => $intrapartum,
            'postpartum' => $postpartum,
        ];
    }

    private function tallySex(array &$bucket, string $key): void
    {
        $bucket[$key]++;
        $bucket['total']++;
    }

    private function sexKey(?string $sex): ?string
    {
        if (! $sex) {
            return null;
        }

        $sex = strtolower($sex);
        if (str_starts_with($sex, 'm')) {
            return 'male';
        }
        if (str_starts_with($sex, 'f')) {
            return 'female';
        }

        return null;
    }

    private function maternalAgeBracket(?int $age, ?string $birthDate): ?string
    {
        if (! $age && $birthDate) {
            try {
                $age = Carbon::parse($birthDate)->age;
            } catch (\Throwable $e) {
                return null;
            }
        }

        if (! $age) {
            return null;
        }

        return match (true) {
            $age >= 10 && $age <= 14 => '10-14',
            $age >= 15 && $age <= 19 => '15-19',
            $age >= 20 && $age <= 49 => '20-49',
            default => null,
        };
    }

    /**
     * Builds Section C (Child Care and Services) figures for the M1 form.
     *
     * Data sources:
     *  - child_immunization_records         (0-11mo current/previous-year vaccination series)
     *  - child_immunization_school_records  (school & community-based immunization)
     *  - child_nutrition_records            (breastfeeding, Vit A, MNP/LNS-SQ, MAM/SAM)
     *  - child_sick_records                  (management of sick children)
     *
     * NOTE ON GAPS:
     *  - "Current year" vs. "previous year" cohorts (A.1 vs. A.2) are inferred
     *    from the child's dateOfBirth calendar year vs. today's year, since
     *    there's no explicit cohort flag in the schema.
     *  - Grade 1 vs. Grade 7 (A.3) is inferred from the free-text `gradeLevel`
     *    column via simple substring matching — tighten this if your app
     *    stores grade level differently (e.g. "Grade 1" vs "1").
     *  - Age-band rows (6-11mo / 12-59mo) parse the leading digits out of the
     *    string `ageMonths` column.
     */
    private function getChildCareData(): array
    {
        $emptySex = ['male' => 0, 'female' => 0, 'total' => 0];
        $currentYear = now()->year;

        // ---- A.1 / A.2: 0-11 months immunization (current vs. previous year) ----
        $imm0_11Keys = [
            'cpab', 'bcg24h', 'bcgLate', 'hepB24h', 'hepBLate',
            'dpt1', 'dpt2', 'dpt3', 'opv1', 'opv2', 'opv3', 'ipv1', 'ipv2',
            'pcv1', 'pcv2', 'pcv3', 'mmr1',
        ];
        $immPrevKeys = [
            'dpt1', 'dpt2', 'dpt3', 'opv1', 'opv2', 'opv3', 'ipv1', 'ipv2',
            'pcv1', 'pcv2', 'pcv3', 'mmr1', 'mmr2', 'fic', 'cic',
        ];
        $imm0_11 = array_fill_keys($imm0_11Keys, $emptySex);
        $immPrev = array_fill_keys($immPrevKeys, $emptySex);

        DB::table('child_immunization_records')
            ->select('sex', 'dateOfBirth', 'td2Mother', 'td3To5Mother',
                'bcgWithin24hDate', 'bcgLateDate', 'hepaBWithin24hDate', 'hepaBLateDate',
                'dpt1Date', 'dpt2Date', 'dpt3Date', 'opv1Date', 'opv2Date', 'opv3Date',
                'ipv1Date', 'ipv2Date', 'pcv1Date', 'pcv2Date', 'pcv3Date',
                'mmr1Date', 'mmr2Date', 'ficDate', 'cicDate')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (&$imm0_11, &$immPrev, $currentYear) {
                foreach ($rows as $row) {
                    $sexKey = $this->sexKey($row->sex);
                    if (! $sexKey) {
                        continue;
                    }

                    $birthYear = null;
                    if ($row->dateOfBirth) {
                        try {
                            $birthYear = Carbon::parse($row->dateOfBirth)->year;
                        } catch (\Throwable $e) {
                            // leave birthYear null, row skipped below
                        }
                    }

                    if ($birthYear === $currentYear) {
                        if ($row->td2Mother || $row->td3To5Mother) {
                            $this->tallySex($imm0_11['cpab'], $sexKey);
                        }
                        if ($row->bcgWithin24hDate) $this->tallySex($imm0_11['bcg24h'], $sexKey);
                        if ($row->bcgLateDate) $this->tallySex($imm0_11['bcgLate'], $sexKey);
                        if ($row->hepaBWithin24hDate) $this->tallySex($imm0_11['hepB24h'], $sexKey);
                        if ($row->hepaBLateDate) $this->tallySex($imm0_11['hepBLate'], $sexKey);
                        if ($row->dpt1Date) $this->tallySex($imm0_11['dpt1'], $sexKey);
                        if ($row->dpt2Date) $this->tallySex($imm0_11['dpt2'], $sexKey);
                        if ($row->dpt3Date) $this->tallySex($imm0_11['dpt3'], $sexKey);
                        if ($row->opv1Date) $this->tallySex($imm0_11['opv1'], $sexKey);
                        if ($row->opv2Date) $this->tallySex($imm0_11['opv2'], $sexKey);
                        if ($row->opv3Date) $this->tallySex($imm0_11['opv3'], $sexKey);
                        if ($row->ipv1Date) $this->tallySex($imm0_11['ipv1'], $sexKey);
                        if ($row->ipv2Date) $this->tallySex($imm0_11['ipv2'], $sexKey);
                        if ($row->pcv1Date) $this->tallySex($imm0_11['pcv1'], $sexKey);
                        if ($row->pcv2Date) $this->tallySex($imm0_11['pcv2'], $sexKey);
                        if ($row->pcv3Date) $this->tallySex($imm0_11['pcv3'], $sexKey);
                        if ($row->mmr1Date) $this->tallySex($imm0_11['mmr1'], $sexKey);
                    } elseif ($birthYear === $currentYear - 1) {
                        if ($row->dpt1Date) $this->tallySex($immPrev['dpt1'], $sexKey);
                        if ($row->dpt2Date) $this->tallySex($immPrev['dpt2'], $sexKey);
                        if ($row->dpt3Date) $this->tallySex($immPrev['dpt3'], $sexKey);
                        if ($row->opv1Date) $this->tallySex($immPrev['opv1'], $sexKey);
                        if ($row->opv2Date) $this->tallySex($immPrev['opv2'], $sexKey);
                        if ($row->opv3Date) $this->tallySex($immPrev['opv3'], $sexKey);
                        if ($row->ipv1Date) $this->tallySex($immPrev['ipv1'], $sexKey);
                        if ($row->ipv2Date) $this->tallySex($immPrev['ipv2'], $sexKey);
                        if ($row->pcv1Date) $this->tallySex($immPrev['pcv1'], $sexKey);
                        if ($row->pcv2Date) $this->tallySex($immPrev['pcv2'], $sexKey);
                        if ($row->pcv3Date) $this->tallySex($immPrev['pcv3'], $sexKey);
                        if ($row->mmr1Date) $this->tallySex($immPrev['mmr1'], $sexKey);
                        if ($row->mmr2Date) $this->tallySex($immPrev['mmr2'], $sexKey);
                        if ($row->ficDate) $this->tallySex($immPrev['fic'], $sexKey);
                        if ($row->cicDate) $this->tallySex($immPrev['cic'], $sexKey);
                    }
                }
            });

        // ---- A.3: School and Community-Based Immunization ----
        $schoolKeys = ['grade1Td', 'grade1Mr', 'grade7Td', 'grade7Mr', 'hpv1Sbi', 'hpv1Cbi', 'hpv2Cbi'];
        $schoolImm = array_fill_keys($schoolKeys, $emptySex);

        DB::table('child_immunization_school_records')
            ->select('sex', 'gradeLevel', 'tdDate', 'mrDate', 'hpv1SbiDate', 'hpv1CbiDate', 'hpv2CbiDate')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (&$schoolImm) {
                foreach ($rows as $row) {
                    $sexKey = $this->sexKey($row->sex);
                    if (! $sexKey) {
                        continue;
                    }

                    $grade = strtolower((string) $row->gradeLevel);
                    $isGrade1 = (bool) preg_match('/\bgrade\s*1\b|\b1st\b/', $grade);
                    $isGrade7 = (bool) preg_match('/\bgrade\s*7\b|\b7th\b/', $grade);

                    if ($isGrade1 && $row->tdDate) $this->tallySex($schoolImm['grade1Td'], $sexKey);
                    if ($isGrade1 && $row->mrDate) $this->tallySex($schoolImm['grade1Mr'], $sexKey);
                    if ($isGrade7 && $row->tdDate) $this->tallySex($schoolImm['grade7Td'], $sexKey);
                    if ($isGrade7 && $row->mrDate) $this->tallySex($schoolImm['grade7Mr'], $sexKey);
                    if ($row->hpv1SbiDate) $this->tallySex($schoolImm['hpv1Sbi'], $sexKey);
                    if ($row->hpv1CbiDate) $this->tallySex($schoolImm['hpv1Cbi'], $sexKey);
                    if ($row->hpv2CbiDate) $this->tallySex($schoolImm['hpv2Cbi'], $sexKey);
                }
            });

        // ---- Nutrition ----
        $nutritionKeys = [
            'breastfeedingInit', 'lbwIronComplete', 'vitA6to11', 'vitA12to59TwoDoses',
            'mnp6to11', 'mnp12to23', 'lns6to11', 'lns12to23',
        ];
        $nutrition2Keys = [
            'seen0to59', 'mamIdentified', 'samIdentified', 'mamEnrolled',
            'mamCured', 'mamNonCured', 'mamDefaulted', 'mamDied',
            'samAdmitted', 'samCured', 'samNonCured', 'samDefaulted', 'samDied',
        ];
        $nutrition = array_fill_keys($nutritionKeys, $emptySex);
        $nutrition2 = array_fill_keys($nutrition2Keys, $emptySex);

        DB::table('child_nutrition_records')
            ->select('sex', 'ageMonths', 'birthWeightStatus', 'breastfeedingDate', 'ironCompleted',
                'vitaA6to11', 'vitaA200Y1D1', 'vitaA200Y1D2', 'vitaA200Y2D1', 'vitaA200Y2D2',
                'vitaA200Y3D1', 'vitaA200Y3D2', 'vitaA200Y4D1', 'vitaA200Y4D2',
                'mnp6to11Completed', 'mnp12to23Completed', 'lns6to11Completed', 'lns12to23Completed',
                'mamIdentified', 'mamEnrolled', 'mamCured', 'mamNonCured', 'mamDefaulted', 'mamDied',
                'samIdentified', 'samAdmitted', 'samCured', 'samNonCured', 'samDefaulted', 'samDied')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (&$nutrition, &$nutrition2) {
                foreach ($rows as $row) {
                    $sexKey = $this->sexKey($row->sex);
                    if (! $sexKey) {
                        continue;
                    }

                    $ageMonths = $this->leadingInt($row->ageMonths);

                    if ($row->breastfeedingDate) {
                        $this->tallySex($nutrition['breastfeedingInit'], $sexKey);
                    }

                    $isLbw = $row->birthWeightStatus && str_contains(strtolower($row->birthWeightStatus), 'low');
                    if ($isLbw && (int) $row->ironCompleted === 1) {
                        $this->tallySex($nutrition['lbwIronComplete'], $sexKey);
                    }

                    if ($row->vitaA6to11) {
                        $this->tallySex($nutrition['vitA6to11'], $sexKey);
                    }

                    $twoDoses = ($row->vitaA200Y1D1 && $row->vitaA200Y1D2)
                        || ($row->vitaA200Y2D1 && $row->vitaA200Y2D2)
                        || ($row->vitaA200Y3D1 && $row->vitaA200Y3D2)
                        || ($row->vitaA200Y4D1 && $row->vitaA200Y4D2);
                    if ($twoDoses) {
                        $this->tallySex($nutrition['vitA12to59TwoDoses'], $sexKey);
                    }

                    if ($row->mnp6to11Completed) $this->tallySex($nutrition['mnp6to11'], $sexKey);
                    if ($row->mnp12to23Completed) $this->tallySex($nutrition['mnp12to23'], $sexKey);
                    if ($row->lns6to11Completed) $this->tallySex($nutrition['lns6to11'], $sexKey);
                    if ($row->lns12to23Completed) $this->tallySex($nutrition['lns12to23'], $sexKey);

                    if ($ageMonths !== null && $ageMonths >= 0 && $ageMonths <= 59) {
                        $this->tallySex($nutrition2['seen0to59'], $sexKey);
                    }

                    if ((int) $row->mamIdentified === 1) $this->tallySex($nutrition2['mamIdentified'], $sexKey);
                    if ((int) $row->samIdentified === 1) $this->tallySex($nutrition2['samIdentified'], $sexKey);
                    if ((int) $row->mamEnrolled === 1) $this->tallySex($nutrition2['mamEnrolled'], $sexKey);
                    if ((int) $row->mamCured === 1) $this->tallySex($nutrition2['mamCured'], $sexKey);
                    if ((int) $row->mamNonCured === 1) $this->tallySex($nutrition2['mamNonCured'], $sexKey);
                    if ((int) $row->mamDefaulted === 1) $this->tallySex($nutrition2['mamDefaulted'], $sexKey);
                    if ((int) $row->mamDied === 1) $this->tallySex($nutrition2['mamDied'], $sexKey);
                    if ((int) $row->samAdmitted === 1) $this->tallySex($nutrition2['samAdmitted'], $sexKey);
                    if ((int) $row->samCured === 1) $this->tallySex($nutrition2['samCured'], $sexKey);
                    if ((int) $row->samNonCured === 1) $this->tallySex($nutrition2['samNonCured'], $sexKey);
                    if ((int) $row->samDefaulted === 1) $this->tallySex($nutrition2['samDefaulted'], $sexKey);
                    if ((int) $row->samDied === 1) $this->tallySex($nutrition2['samDied'], $sexKey);
                }
            });

        // ---- Management of Sick Children ----
        $mgmtKeys = [
            'sick6to11Seen', 'vitA6to11Sick', 'sick12to59Seen', 'vitA12to59Sick',
            'diarrhea0to59Seen', 'orsOnly', 'orsZinc',
            'pneumonia0to59Seen', 'antibioticAny', 'amoxDrops', 'amoxClav', 'cefuroxime', 'otherAntibiotic',
        ];
        $mgmtSick = array_fill_keys($mgmtKeys, $emptySex);

        DB::table('child_sick_records')
            ->select('sex', 'ageMonths', 'vitaminA100IU', 'vitaminA200IU', 'diarrheaDateGiven',
                'orsOnly', 'orsAndZinc', 'pneumoniaDateGiven', 'amoxicillinDrops',
                'amoxicillinClavulanate', 'cefuroxime', 'pneumoniaOthers')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (&$mgmtSick) {
                foreach ($rows as $row) {
                    $sexKey = $this->sexKey($row->sex);
                    if (! $sexKey) {
                        continue;
                    }

                    $ageMonths = $this->leadingInt($row->ageMonths);

                    if ($ageMonths !== null && $ageMonths >= 6 && $ageMonths <= 11) {
                        $this->tallySex($mgmtSick['sick6to11Seen'], $sexKey);
                        if ($row->vitaminA100IU) {
                            $this->tallySex($mgmtSick['vitA6to11Sick'], $sexKey);
                        }
                    }
                    if ($ageMonths !== null && $ageMonths >= 12 && $ageMonths <= 59) {
                        $this->tallySex($mgmtSick['sick12to59Seen'], $sexKey);
                        if ($row->vitaminA200IU) {
                            $this->tallySex($mgmtSick['vitA12to59Sick'], $sexKey);
                        }
                    }

                    if ($row->diarrheaDateGiven) {
                        $this->tallySex($mgmtSick['diarrhea0to59Seen'], $sexKey);
                        if ($row->orsOnly) $this->tallySex($mgmtSick['orsOnly'], $sexKey);
                        if ($row->orsAndZinc) $this->tallySex($mgmtSick['orsZinc'], $sexKey);
                    }

                    if ($row->pneumoniaDateGiven) {
                        $this->tallySex($mgmtSick['pneumonia0to59Seen'], $sexKey);
                        $anyAntibiotic = $row->amoxicillinDrops || $row->amoxicillinClavulanate
                            || $row->cefuroxime || $row->pneumoniaOthers;
                        if ($anyAntibiotic) $this->tallySex($mgmtSick['antibioticAny'], $sexKey);
                        if ($row->amoxicillinDrops) $this->tallySex($mgmtSick['amoxDrops'], $sexKey);
                        if ($row->amoxicillinClavulanate) $this->tallySex($mgmtSick['amoxClav'], $sexKey);
                        if ($row->cefuroxime) $this->tallySex($mgmtSick['cefuroxime'], $sexKey);
                        if ($row->pneumoniaOthers) $this->tallySex($mgmtSick['otherAntibiotic'], $sexKey);
                    }
                }
            });

        return [
            'imm0_11' => $imm0_11,
            'immPrev' => $immPrev,
            'schoolImm' => $schoolImm,
            'nutrition' => $nutrition,
            'nutrition2' => $nutrition2,
            'mgmtSick' => $mgmtSick,
        ];
    }

    /**
     * Extracts the leading integer from a loosely-formatted numeric string
     * column (e.g. ageMonths stored as "8" or "8 months").
     */
    private function leadingInt(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        if (preg_match('/-?\d+/', $value, $matches)) {
            return (int) $matches[0];
        }

        return null;
    }

    private function ageBracket(?string $dob): ?string
    {
        if (! $dob) {
            return null;
        }

        try {
            $age = Carbon::parse($dob)->age;
        } catch (\Throwable $e) {
            return null;
        }

        return match (true) {
            $age >= 10 && $age <= 14 => '10-14',
            $age >= 15 && $age <= 19 => '15-19',
            $age >= 20 && $age <= 49 => '20-49',
            default => null,
        };
    }
}