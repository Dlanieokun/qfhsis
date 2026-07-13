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
     * PHO View showing the M1/Q1/M2/A1 report tabs with location data.
     */
    public function pho(): Response
    {
        $regions = DB::table('regions')->select('regCode', 'regDesc')->get();
        $provinces = DB::table('provinces')->select('provCode', 'provDesc', 'regCode')->get();
        $municipalities = DB::table('municipalities')->select('citymunCode', 'citymunDesc', 'provCode')->get();
        $barangays = DB::table('barangays')->select('brgyCode', 'brgyDesc', 'citymunCode')->get();

        return Inertia::render('fhsis/pho', array_merge(
            $this->getM1ReportData(),
            [
                'regions' => $regions,
                'provinces' => $provinces,
                'municipalities' => $municipalities,
                'barangays' => $barangays,
            ]
        ));
    }

    public function nurse(): Response
    {
        return Inertia::render('fhsis/PublicNurse');
    }

    /**
     * Assembles every M1 section (A–G) into a single array. Shared by the
     * pho() Inertia view and by PublicNurseController::m1AllDownload(),
     * which renders this same data as an .xlsx export.
     */
    public function getM1ReportData(): array
    {
        return [
            'familyPlanning' => $this->getFamilyPlanningData(),
            'maternalCare' => $this->getMaternalCareData(),
            'childCare' => $this->getChildCareData(),
            'oralHealth' => $this->getOralHealthData(),
            'nonCommunicableDisease' => $this->getNonCommunicableDiseaseData(),
            'environmentalHealth' => $this->getEnvironmentalHealthData(),
            'infectiousDisease' => $this->getInfectiousDiseaseData(),
        ];
    }

    /**
     * Builds the Section A (Family Planning) figures for the M1 form from
     * household_profiles.fpMethodUsed / dob.
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

        // Supplementation + deworming
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

        // ---- Intrapartum ----
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

        // ---- Postpartum ----
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
     */
    private function getChildCareData(): array
    {
        $emptySex = ['male' => 0, 'female' => 0, 'total' => 0];
        $currentYear = now()->year;

        // ---- A.1 / A.2: 0-11 months immunization ----
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
                            // skip
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
     * Builds Section D (Oral Health Care Services) figures for the M1 form
     */
    private function getOralHealthData(): array
    {
        $emptySex = ['male' => 0, 'female' => 0, 'total' => 0];

        $brackets = ['children1_4', 'children5_9', 'adolescents10_19', 'adults20_59', 'seniors60plus', 'pregnant'];

        $firstVisit = array_fill_keys($brackets, $emptySex);
        $firstVisitFacility = array_fill_keys($brackets, $emptySex);
        $firstVisitNonFacility = array_fill_keys($brackets, $emptySex);
        $completed2Visits = array_fill_keys($brackets, $emptySex);
        $completed2VisitsFacility = array_fill_keys($brackets, $emptySex);
        $completed2VisitsNonFacility = array_fill_keys($brackets, $emptySex);
        $infantFirstVisit = $emptySex;

        DB::table('oral_health_care')
            ->select('sex', 'age_months', 'age_group1st', 'age_group2nd',
                'rpoc0_oral_screening', 'rpoc0_risk_assessment', 'rpoc0_oral_hygiene',
                'rpoc0_counseling', 'rpoc0_fluoride_varnish',
                'oral_screening1st', 'oral_screening2nd',
                'complete_rpoc1st', 'complete_rpoc2nd',
                'service_location1st', 'service_location2nd')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (
                &$firstVisit, &$firstVisitFacility, &$firstVisitNonFacility,
                &$completed2Visits, &$completed2VisitsFacility, &$completed2VisitsNonFacility,
                &$infantFirstVisit
            ) {
                foreach ($rows as $row) {
                    $sexKey = $this->sexKey($row->sex);
                    if (! $sexKey) {
                        continue;
                    }

                    $ageMonths = $this->leadingInt($row->age_months);
                    $anyInfantService = $row->rpoc0_oral_screening || $row->rpoc0_risk_assessment
                        || $row->rpoc0_oral_hygiene || $row->rpoc0_counseling || $row->rpoc0_fluoride_varnish;
                    if ($ageMonths !== null && $ageMonths >= 0 && $ageMonths <= 11 && $anyInfantService) {
                        $this->tallySex($infantFirstVisit, $sexKey);
                    }

                    $bracket = $this->oralHealthBracket($row->age_group1st);
                    if ($bracket && $row->oral_screening1st) {
                        $this->tallySex($firstVisit[$bracket], $sexKey);
                        if ($row->service_location1st === 'A') {
                            $this->tallySex($firstVisitFacility[$bracket], $sexKey);
                        } elseif ($row->service_location1st === 'B') {
                            $this->tallySex($firstVisitNonFacility[$bracket], $sexKey);
                        }
                    }

                    $bracket2 = $this->oralHealthBracket($row->age_group2nd ?: $row->age_group1st);
                    if ($bracket2 && (int) $row->complete_rpoc2nd === 1) {
                        $this->tallySex($completed2Visits[$bracket2], $sexKey);
                        if ($row->service_location2nd === 'A') {
                            $this->tallySex($completed2VisitsFacility[$bracket2], $sexKey);
                        } elseif ($row->service_location2nd === 'B') {
                            $this->tallySex($completed2VisitsNonFacility[$bracket2], $sexKey);
                        }
                    }
                }
            });

        return [
            'infantFirstVisit' => $infantFirstVisit,
            'firstVisit' => $firstVisit,
            'firstVisitFacility' => $firstVisitFacility,
            'firstVisitNonFacility' => $firstVisitNonFacility,
            'completed2Visits' => $completed2Visits,
            'completed2VisitsFacility' => $completed2VisitsFacility,
            'completed2VisitsNonFacility' => $completed2VisitsNonFacility,
        ];
    }

    private function oralHealthBracket(?string $ageGroupCode): ?string
    {
        return match ($ageGroupCode) {
            'A' => 'children1_4',
            'B' => 'children5_9',
            'C' => 'adolescents10_19',
            'D' => 'adults20_59',
            'E' => 'seniors60plus',
            'F', 'G', 'H' => 'pregnant',
            default => null,
        };
    }

    /**
     * Builds Section E (Non-Communicable Diseases) figures for the M1 form.
     */
    private function getNonCommunicableDiseaseData(): array
    {
        $emptySex = ['male' => 0, 'female' => 0, 'total' => 0];

        $lifestyleKeys = [
            'currentSmoker', 'smokerTobacco', 'smokerVaporized', 'smokerBoth',
            'providedBti', 'bingeAlcohol', 'insufficientPa', 'unhealthyDiet',
            'overweight', 'obese',
        ];
        $lifestyle2059 = array_fill_keys($lifestyleKeys, $emptySex);
        $lifestyle60plus = array_fill_keys($lifestyleKeys, $emptySex);

        $cvd2059 = $emptySex;
        $cvd60plus = $emptySex;
        $dm2059 = $emptySex;
        $dm60plus = $emptySex;

        DB::table('philpen_risk_assessments')
            ->select('sex', 'age_group', 'current_smoker', 'provided_bti', 'binge_alcohol',
                'insufficient_pa', 'unhealthy_diet', 'bmi_category', 'hypertension_result', 'diabetes_result')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (&$lifestyle2059, &$lifestyle60plus, &$cvd2059, &$cvd60plus, &$dm2059, &$dm60plus) {
                foreach ($rows as $row) {
                    $sexKey = $this->sexKey($row->sex);
                    if (! $sexKey) {
                        continue;
                    }

                    $isSenior = $row->age_group === 'B';
                    $lifestyle = $isSenior ? $lifestyle60plus : $lifestyle2059;

                    $smoker = (int) $row->current_smoker;
                    if ($smoker > 0) {
                        $this->tallySex($lifestyle['currentSmoker'], $sexKey);
                        if ($smoker === 1) $this->tallySex($lifestyle['smokerTobacco'], $sexKey);
                        if ($smoker === 2) $this->tallySex($lifestyle['smokerVaporized'], $sexKey);
                        if ($smoker === 3) $this->tallySex($lifestyle['smokerBoth'], $sexKey);
                    }
                    if ((int) $row->provided_bti === 1) $this->tallySex($lifestyle['providedBti'], $sexKey);
                    if ((int) $row->binge_alcohol === 1) $this->tallySex($lifestyle['bingeAlcohol'], $sexKey);
                    if ((int) $row->insufficient_pa === 1) $this->tallySex($lifestyle['insufficientPa'], $sexKey);
                    if ((int) $row->unhealthy_diet === 1) $this->tallySex($lifestyle['unhealthyDiet'], $sexKey);
                    if ((int) $row->bmi_category === 1) $this->tallySex($lifestyle['overweight'], $sexKey);
                    if ((int) $row->bmi_category === 2) $this->tallySex($lifestyle['obese'], $sexKey);

                    if ((int) $row->hypertension_result === 1) {
                        // $this->tallySex($isSenior ? $cvd60plus : $cvd2059, $sexKey);
                    }
                    if ((int) $row->diabetes_result === 1) {
                        // $this->tallySex($isSenior ? $dm60plus : $dm2059, $sexKey);
                    }
                }
            });

        // ---- Blindness Prevention (eyes_screenings) ----
        $eyeKeys = ['screened0_9', 'screened10_19', 'screened20_59', 'screened60plus', 'identified', 'referred'];
        $blindness = array_fill_keys($eyeKeys, $emptySex);

        DB::table('eyes_screenings')
            ->select('sex', 'age_group', 'screened', 'eye_disease_code', 'date_referred')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (&$blindness) {
                foreach ($rows as $row) {
                    $sexKey = $this->sexKey($row->sex);
                    if (! $sexKey || (int) $row->screened !== 1) {
                        continue;
                    }

                    $bucket = match ($row->age_group) {
                        'A' => 'screened0_9',
                        'B' => 'screened10_19',
                        'C' => 'screened20_59',
                        'D' => 'screened60plus',
                        default => null,
                    };
                    if ($bucket) {
                        $this->tallySex($blindness[$bucket], $sexKey);
                    }

                    if ($row->eye_disease_code && $row->eye_disease_code !== '0') {
                        $this->tallySex($blindness['identified'], $sexKey);
                    }
                    if ($row->date_referred) {
                        $this->tallySex($blindness['referred'], $sexKey);
                    }
                }
            });

        // ---- Mental Health (mhGAP screening) ----
        $mentalHealthKeys = ['screened0_9', 'screened10_19', 'screened20_59', 'screened60plus'];
        $mentalHealth = array_fill_keys($mentalHealthKeys, $emptySex);

        DB::table('mental_health_records')
            ->select('sex', 'ageGroup', 'screenedMhgap')
            ->orderBy('recordNo')
            ->chunk(300, function ($rows) use (&$mentalHealth) {
                foreach ($rows as $row) {
                    $sexKey = $this->sexKey($row->sex);
                    if (! $sexKey || ! $row->screenedMhgap) {
                        continue;
                    }

                    $bucket = match ($row->ageGroup) {
                        'A' => 'screened0_9',
                        'B' => 'screened10_19',
                        'C' => 'screened20_59',
                        'D' => 'screened60plus',
                        default => null,
                    };
                    if ($bucket) {
                        $this->tallySex($mentalHealth[$bucket], $sexKey);
                    }
                }
            });

        // ---- Cervical & Breast Cancer (cervical_cancer_screenings) ----
        $cervical = [
            'screened' => 0, 'via' => 0, 'papSmear' => 0, 'hpvDna' => 0, 'assessedOnly' => 0,
            'suspicious' => 0, 'linkedToCare' => 0, 'linkedTreated' => 0, 'linkedReferred' => 0,
        ];
        $breast = [
            'seen' => 0, 'highRiskOrSymptomatic' => 0, 'providedCbe' => 0, 'providedMammogram' => 0,
            'remarkableCbe' => 0, 'remarkableMammogram' => 0, 'linkedToCare' => 0,
            'asymptomaticScreened' => 0,
        ];

        DB::table('cervical_cancer_screenings')
            ->select('cervical_screening_done', 'cervical_result', 'cervical_linked_to_care',
                'breast_risk_assessment', 'breast_exam_type', 'breast_result', 'breast_linked_to_care')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (&$cervical, &$breast) {
                foreach ($rows as $row) {
                    $doneCode = (int) $row->cervical_screening_done;
                    if ($doneCode > 0) {
                        $cervical['screened']++;
                        if ($doneCode === 3) $cervical['hpvDna']++;
                        if ($doneCode === 2) $cervical['papSmear']++;
                        if ($doneCode === 1) $cervical['via']++;
                        if ($doneCode === 0) $cervical['assessedOnly']++;
                    }
                    if ((int) $row->cervical_result === 1) {
                        $cervical['suspicious']++;
                    }
                    $linkCode = (int) $row->cervical_linked_to_care;
                    if ($linkCode > 0) {
                        $cervical['linkedToCare']++;
                        if ($linkCode === 1) $cervical['linkedTreated']++;
                        if ($linkCode === 2) $cervical['linkedReferred']++;
                    }

                    $riskCode = (int) $row->breast_risk_assessment;
                    if ($riskCode > 0) {
                        $breast['seen']++;
                        $breast['highRiskOrSymptomatic']++;
                    } elseif ($riskCode === 0) {
                        $breast['seen']++;
                        $breast['asymptomaticScreened']++;
                    }

                    $examType = strtolower((string) $row->breast_exam_type);
                    if (str_contains($examType, 'cbe')) $breast['providedCbe']++;
                    if (str_contains($examType, 'mammo')) $breast['providedMammogram']++;

                    $breastResult = (int) $row->breast_result;
                    if ($breastResult >= 2) {
                        if (str_contains($examType, 'cbe')) $breast['remarkableCbe']++;
                        if (str_contains($examType, 'mammo')) $breast['remarkableMammogram']++;
                    }
                    if ((int) $row->breast_linked_to_care === 1) {
                        $breast['linkedToCare']++;
                    }
                }
            });

        return [
            'lifestyle2059' => $lifestyle2059,
            'lifestyle60plus' => $lifestyle60plus,
            'cvd2059' => $cvd2059,
            'cvd60plus' => $cvd60plus,
            'dm2059' => $dm2059,
            'dm60plus' => $dm60plus,
            'blindness' => $blindness,
            'mentalHealth' => $mentalHealth,
            'cervical' => $cervical,
            'breast' => $breast,
        ];
    }

    /**
     * Builds Section F (Environmental Health and Sanitation) figures for the M1 form
     */
    private function getEnvironmentalHealthData(): array
    {
        $water = [
            'levelI' => 0, 'levelII' => 0, 'levelIII' => 0, 'safelyManaged' => 0, 'total' => 0,
        ];
        $sanitation = [
            'pourFlushSeptic' => 0, 'pourFlushSewer' => 0, 'vip' => 0,
            'basicSanitationFacility' => 0, 'safelyManagedSanitation' => 0, 'total' => 0,
        ];

        DB::table('environmental_health_records')
            ->select('waterLevelI', 'waterLevelII', 'waterLevelIII', 'safelyManagedDrinkingWater',
                'sanitationStatus', 'unsanitaryToiletType', 'basicSanitationFacility', 'safelyManagedSanitationService')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (&$water, &$sanitation) {
                foreach ($rows as $row) {
                    $water['total']++;
                    $sanitation['total']++;

                    if ($row->waterLevelI) $water['levelI']++;
                    if ($row->waterLevelII) $water['levelII']++;
                    if ($row->waterLevelIII) $water['levelIII']++;
                    if ((int) $row->safelyManagedDrinkingWater === 1) $water['safelyManaged']++;

                    $status = strtolower((string) $row->sanitationStatus);
                    if (str_contains($status, 'sewer')) {
                        $sanitation['pourFlushSewer']++;
                    } elseif (str_contains($status, 'septic') || str_contains($status, 'functional')) {
                        $sanitation['pourFlushSeptic']++;
                    }
                    if ((int) $row->unsanitaryToiletType === 0 && ! str_contains($status, 'sewer') && ! str_contains($status, 'septic')) {
                        if (str_contains($status, 'sanitary')) {
                            $sanitation['vip']++;
                        }
                    }
                    if ((int) $row->basicSanitationFacility === 1) $sanitation['basicSanitationFacility']++;
                    if ((int) $row->safelyManagedSanitationService === 1) $sanitation['safelyManagedSanitation']++;
                }
            });

        return [
            'water' => $water,
            'sanitation' => $sanitation,
        ];
    }

    /**
     * Builds Section G (Infectious Disease Prevention and Control Services) figures for the M1 form.
     */
    private function getInfectiousDiseaseData(): array
    {
        $emptySex = ['male' => 0, 'female' => 0, 'total' => 0];

        // ---- Filariasis ----
        $filariasis = [
            'examinedNbe' => $emptySex, 'examinedRdt' => $emptySex,
            'positiveNbe' => $emptySex, 'positiveRdt' => $emptySex,
            'lymphedema' => $emptySex, 'elephantiasis' => $emptySex, 'hydrocele' => $emptySex,
            'receivedMda' => $emptySex,
        ];

        DB::table('filariasis_registry_table')
            ->select('sex', 'nbe_performed', 'rdt_performed', 'blood_test_result',
                'has_lymphedema', 'has_elephantiasis', 'has_hydrocele',
                'albendazole_date_given', 'dec_date_given', 'ivermectin_date_given')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (&$filariasis) {
                foreach ($rows as $row) {
                    $sexKey = $this->sexKey($row->sex);
                    if (! $sexKey) {
                        continue;
                    }

                    $positive = $row->blood_test_result && str_contains(strtolower($row->blood_test_result), 'positive');
                    if ($row->nbe_performed) {
                        $this->tallySex($filariasis['examinedNbe'], $sexKey);
                        if ($positive) $this->tallySex($filariasis['positiveNbe'], $sexKey);
                    }
                    if ($row->rdt_performed) {
                        $this->tallySex($filariasis['examinedRdt'], $sexKey);
                        if ($positive) $this->tallySex($filariasis['positiveRdt'], $sexKey);
                    }
                    if ($row->has_lymphedema) $this->tallySex($filariasis['lymphedema'], $sexKey);
                    if ($row->has_elephantiasis) $this->tallySex($filariasis['elephantiasis'], $sexKey);
                    if ($row->has_hydrocele) $this->tallySex($filariasis['hydrocele'], $sexKey);
                    if ($row->albendazole_date_given || $row->dec_date_given || $row->ivermectin_date_given) {
                        $this->tallySex($filariasis['receivedMda'], $sexKey);
                    }
                }
            });

        // ---- Rabies ----
        $rabies = ['animalBites' => $emptySex, 'rabiesDeaths' => $emptySex];

        DB::table('rabies_records')
            ->select('sex', 'pvrv_outcome', 'pcev_outcome')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (&$rabies) {
                foreach ($rows as $row) {
                    $sexKey = $this->sexKey($row->sex);
                    if (! $sexKey) {
                        continue;
                    }

                    $this->tallySex($rabies['animalBites'], $sexKey);

                    $outcome = strtolower((string) ($row->pvrv_outcome ?: $row->pcev_outcome));
                    if (str_contains($outcome, 'died') || str_contains($outcome, 'death')) {
                        $this->tallySex($rabies['rabiesDeaths'], $sexKey);
                    }
                }
            });

        // ---- Schistosomiasis ----
        $schisto = [
            'patientsSeen' => $emptySex, 'suspectedCases' => $emptySex, 'suspectedTreated' => $emptySex,
            'confirmedComplicated' => $emptySex, 'confirmedNonComplicated' => $emptySex,
            'confirmedTreated' => $emptySex, 'confirmedCured' => $emptySex, 'referredToHospital' => $emptySex,
            'mdaGiven' => $emptySex,
        ];

        DB::table('schistosomiasis_registry')
            ->select('sex', 'screened', 'with_signs_symptoms', 'clinical_first_treatment_given',
                'clinical_retreatment', 'clinical_cured', 'complicated',
                'confirmed_first_treatment_given', 'confirmed_retreatment', 'confirmed_cured',
                'date_referred_to_hospital', 'mda_given')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (&$schisto) {
                foreach ($rows as $row) {
                    $sexKey = $this->sexKey($row->sex);
                    if (! $sexKey) {
                        continue;
                    }

                    if ($row->screened) $this->tallySex($schisto['patientsSeen'], $sexKey);
                    if ($row->with_signs_symptoms) {
                        $this->tallySex($schisto['suspectedCases'], $sexKey);
                        if ($row->clinical_first_treatment_given || $row->clinical_retreatment) {
                            $this->tallySex($schisto['suspectedTreated'], $sexKey);
                        }
                    }

                    $complicated = $row->complicated && strtolower($row->complicated) !== 'no';
                    if ($complicated) {
                        $this->tallySex($schisto['confirmedComplicated'], $sexKey);
                    } elseif ($row->confirmed_first_treatment_given || $row->confirmed_retreatment) {
                        $this->tallySex($schisto['confirmedNonComplicated'], $sexKey);
                    }
                    if ($row->confirmed_first_treatment_given || $row->confirmed_retreatment) {
                        $this->tallySex($schisto['confirmedTreated'], $sexKey);
                    }
                    if ($row->confirmed_cured) $this->tallySex($schisto['confirmedCured'], $sexKey);
                    if ($row->date_referred_to_hospital) $this->tallySex($schisto['referredToHospital'], $sexKey);
                    if ($row->mda_given) $this->tallySex($schisto['mdaGiven'], $sexKey);
                }
            });

        // ---- Soil-Transmitted Helminthiasis (STH) ----
        $sth = [
            'screened' => $emptySex, 'suspectedResident' => $emptySex, 'suspectedNonResident' => $emptySex,
            'confirmedResident' => $emptySex, 'confirmedNonResident' => $emptySex,
            'treatedResident' => $emptySex, 'treatedNonResident' => $emptySex,
            'januaryMda' => $emptySex, 'julyMda' => $emptySex,
        ];

        DB::table('sth_registry_records')
            ->select('sex', 'residency', 'screened', 'screening_result', 'treatment_given',
                'january_mda_date', 'july_mda_date')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (&$sth) {
                foreach ($rows as $row) {
                    $sexKey = $this->sexKey($row->sex);
                    if (! $sexKey) {
                        continue;
                    }

                    if ($row->screened) $this->tallySex($sth['screened'], $sexKey);

                    $isResident = $row->residency && strtolower($row->residency) !== 'non-resident' && (int) $row->residency !== 0;
                    $isSuspectedOrConfirmed = $row->screening_result && strtolower($row->screening_result) !== 'negative';
                    if ($isSuspectedOrConfirmed) {
                        if ($isResident) {
                            $this->tallySex($sth['suspectedResident'], $sexKey);
                            $this->tallySex($sth['confirmedResident'], $sexKey);
                        } else {
                            $this->tallySex($sth['suspectedNonResident'], $sexKey);
                            $this->tallySex($sth['confirmedNonResident'], $sexKey);
                        }
                    }
                    if ($row->treatment_given && strtolower($row->treatment_given) !== 'none') {
                        if ($isResident) {
                            $this->tallySex($sth['treatedResident'], $sexKey);
                        } else {
                            $this->tallySex($sth['treatedNonResident'], $sexKey);
                        }
                    }
                    if ($row->january_mda_date) $this->tallySex($sth['januaryMda'], $sexKey);
                    if ($row->july_mda_date) $this->tallySex($sth['julyMda'], $sexKey);
                }
            });

        // ---- Leprosy ----
        $leprosy = [
            'registered' => $emptySex, 'newlyDetected' => $emptySex, 'confirmed' => $emptySex,
            'completedMdt' => $emptySex, 'treated' => $emptySex, 'grade2Disability' => $emptySex,
        ];

        DB::table('leprosy_registry')
            ->select('sex', 'confirmed_case', 'case_history', 'completed_fixed_mdt',
                'fixed_mdt_completed_date', 'treatment_start_date', 'grade2_disability')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (&$leprosy) {
                foreach ($rows as $row) {
                    $sexKey = $this->sexKey($row->sex);
                    if (! $sexKey) {
                        continue;
                    }

                    $this->tallySex($leprosy['registered'], $sexKey);

                    $history = strtolower((string) $row->case_history);
                    if ($history === 'new' || $history === '0') {
                        $this->tallySex($leprosy['newlyDetected'], $sexKey);
                    }
                    if ($row->confirmed_case && strtolower($row->confirmed_case) !== 'no') {
                        $this->tallySex($leprosy['confirmed'], $sexKey);
                    }
                    if ($row->completed_fixed_mdt && strtolower($row->completed_fixed_mdt) !== 'no') {
                        $this->tallySex($leprosy['completedMdt'], $sexKey);
                    }
                    if ($row->treatment_start_date) {
                        $this->tallySex($leprosy['treated'], $sexKey);
                    }
                    if ($row->grade2_disability && strtolower($row->grade2_disability) !== 'no') {
                        $this->tallySex($leprosy['grade2Disability'], $sexKey);
                    }
                }
            });

        return [
            'filariasis' => $filariasis,
            'rabies' => $rabies,
            'schistosomiasis' => $schisto,
            'sth' => $sth,
            'leprosy' => $leprosy,
        ];
    }

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