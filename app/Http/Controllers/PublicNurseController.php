<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

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
    public function publicNurse(): Response
    {
        return Inertia::render('fhsis/public-nurse', [
            'familyPlanning' => $this->familyPlanning(),
            'maternalCare' => $this->maternalCare(),
            'childCare' => $this->childCare(),
            'oralHealth' => $this->oralHealth(),
            'nonCommunicableDisease' => $this->nonCommunicableDisease(),
            'geriatricHealth' => $this->geriatricHealth(),
            'infectiousDisease' => $this->infectiousDisease(),
            'wash' => $this->wash(),
        ]);
    }

    /* =====================================================================
     * Family Planning
     * ===================================================================*/

    private function familyPlanning(): array
    {
        $records = DB::table('family_planning_records as fp')
            ->leftJoin('household_profiles as hp', 'hp.id', '=', 'fp.profileId')
            ->select(
                'fp.*',
                'hp.memberLastName',
                'hp.memberFirstName',
                'hp.memberMiddleName'
            )
            ->get();

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

    private function maternalCare(): array
    {
        $records = DB::table('maternal_care_records')->get();
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

    private function childCare(): array
    {
        return [
            'immunization' => DB::table('child_immunization_records')->get()
                ->map(fn ($r) => $this->mapChildImmunization($r))->values()->all(),
            'immunizationSchool' => DB::table('child_immunization_school_records')->get()
                ->map(fn ($r) => $this->mapChildImmunizationSchool($r))->values()->all(),
            'managementOfSick' => DB::table('child_sick_records')->get()
                ->map(fn ($r) => $this->mapChildManagementSick($r))->values()->all(),
            'nutrition' => DB::table('child_nutrition_records')->get()
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

    private function oralHealth(): array
    {
        return DB::table('oral_health_care')->get()->map(function ($r) {
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

    private function nonCommunicableDisease(): array
    {
        return [
            'philpenRiskAssessment' => DB::table('philpen_risk_assessments')->get()
                ->map(fn ($r) => $this->mapPhilpen($r))->values()->all(),
            'eyeScreening' => DB::table('eyes_screenings')->get()
                ->map(fn ($r) => $this->mapEyeScreening($r))->values()->all(),
            'cervicalCancer' => DB::table('cervical_cancer_screenings')->get()
                ->map(fn ($r) => $this->mapCervicalCancer($r))->values()->all(),
            'mentalHealth' => DB::table('mental_health_records')->get()
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

    private function geriatricHealth(): array
    {
        return DB::table('geriatric_screening_records')->get()->map(function ($r) {
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

    private function infectiousDisease(): array
    {
        return [
            'filariasis' => DB::table('filariasis_registry_table')->get()->values()
                ->map(fn ($r, $i) => $this->mapFilariasis($r, $i + 1))->all(),
            'schistosomiasis' => DB::table('schistosomiasis_registry')->get()->values()
                ->map(fn ($r, $i) => $this->mapSchistosomiasis($r, $i + 1))->all(),
            'sth' => DB::table('sth_registry_records')->get()->values()
                ->map(fn ($r, $i) => $this->mapSth($r, $i + 1))->all(),
            'leprosy' => DB::table('leprosy_registry')->get()->values()
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