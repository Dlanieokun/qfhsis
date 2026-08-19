<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Builds the data behind the M2 (8PAA) report — resources/js/pages/fhsis/M28PAA.tsx.
 *
 * NOTE: The original version of this controller was written against a schema
 * that doesn't exist anywhere else in this app (numeric barangay_id/province_id
 * foreign keys, a "gender" column, a health_facilities table, *_given boolean
 * columns, etc.) which meant every query in it would fail against the real
 * database. This version follows the same conventions used throughout
 * PhoController / PhoReportController:
 *  - Locations are matched by descriptive text (household_profiles.region /
 *    province / municipality / barangay), resolved from the regCode/provCode/
 *    citymunCode/brgyCode the frontend sends.
 *  - Sex is stored in a `sex` column ('M'/'F' or 'Male'/'Female').
 *  - Immunization dates live in columns like dpt1Date, opv1Date, etc.
 */
class M28PAAController extends Controller
{
    /**
     * Fetch M28PAA report data.
     *
     * Query params:
     * - year (required)
     * - month (optional, 1-12) — omit for a year-to-date report
     * - region, province, municipality, barangay (optional codes, e.g. regCode/provCode/citymunCode/brgyCode)
     */
    public function m28paaReport(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:1900|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
            'region' => 'nullable|string',
            'province' => 'nullable|string',
            'municipality' => 'nullable|string',
            'barangay' => 'nullable|string',
        ]);

        $year = (int) $validated['year'];
        $month = isset($validated['month']) ? (int) $validated['month'] : null;
        $desc = $this->resolveLocationDescriptions($validated);

        return response()->json([
            'status' => 'success',
            'filters' => $validated,
            'data' => [
                'sectionA' => $this->aggregateSectionA($year, $month, $desc),
                'sectionB' => $this->aggregateSectionB($year, $month, $desc),
                'sectionC' => $this->aggregateSectionC($year, $month, $desc),
            ],
        ]);
    }

    /* =====================================================================
     * SECTION A. CHILD CARE AND SERVICES (Immunization)
     * ===================================================================*/

    private function aggregateSectionA(int $year, ?int $month, array $desc): array
    {
        $sexEmpty = ['male' => 0, 'female' => 0, 'total' => 0];

        $a1Keys = ['cpab', 'bcg24h', 'bcgLate', 'hepB24h', 'hepBLate', 'dpt1', 'dpt2', 'dpt3',
            'opv1', 'opv2', 'opv3', 'ipv1', 'ipv2', 'pcv1', 'pcv2', 'pcv3', 'mmr1'];
        $a3Keys = ['dpt1', 'dpt2', 'dpt3', 'opv1', 'opv2', 'opv3', 'ipv1', 'ipv2',
            'pcv1', 'pcv2', 'pcv3', 'mmr1', 'mmr2', 'fic', 'cic'];
        $a4Keys = ['grade1Td', 'grade1Mr', 'grade7Td', 'grade7Mr', 'hpv1Sbi', 'hpv1Cbi', 'hpv2Cbi'];

        $a1 = array_fill_keys($a1Keys, $sexEmpty);
        $a3 = array_fill_keys($a3Keys, $sexEmpty);
        $a4 = array_fill_keys($a4Keys, $sexEmpty);

        $bump = function (array &$bucket, string $key, ?string $sex) use ($sexEmpty) {
            if (! isset($bucket[$key])) {
                $bucket[$key] = $sexEmpty;
            }
            $bucket[$key]['total']++;
            if ($sk = $this->sexKey($sex)) {
                $bucket[$key][$sk]++;
            }
        };

        // A.1 (current year) and A.3 (previous year) both live on the same
        // table; a child is bucketed into A.1 or A.3 by their birth year.
        $immQuery = DB::table('child_immunization_records');
        $this->applyProfileLocationFilter($immQuery, 'profileId', $desc);
        $immRecords = $immQuery
            ->select(
                'sex', 'dateOfBirth', 'td2Mother', 'td3To5Mother',
                'bcgWithin24hDate', 'bcgLateDate', 'hepaBWithin24hDate', 'hepaBLateDate',
                'dpt1Date', 'dpt2Date', 'dpt3Date', 'opv1Date', 'opv2Date', 'opv3Date',
                'ipv1Date', 'ipv2Date', 'pcv1Date', 'pcv2Date', 'pcv3Date',
                'mmr1Date', 'mmr2Date', 'ficDate', 'cicDate', 'created_at'
            )
            ->get();

        foreach ($immRecords as $rec) {
            if (! $this->timestampInPeriod($rec->created_at, $year, $month)) {
                continue;
            }

            $sex = $rec->sex;
            $birthYear = null;
            if (! empty($rec->dateOfBirth)) {
                try {
                    $birthYear = Carbon::parse($rec->dateOfBirth)->year;
                } catch (\Throwable $e) {
                    // ignore unparsable dates
                }
            }

            if ($birthYear === $year) {
                if ($rec->td2Mother || $rec->td3To5Mother) $bump($a1, 'cpab', $sex);
                if ($rec->bcgWithin24hDate) $bump($a1, 'bcg24h', $sex);
                if ($rec->bcgLateDate) $bump($a1, 'bcgLate', $sex);
                if ($rec->hepaBWithin24hDate) $bump($a1, 'hepB24h', $sex);
                if ($rec->hepaBLateDate) $bump($a1, 'hepBLate', $sex);
                if ($rec->dpt1Date) $bump($a1, 'dpt1', $sex);
                if ($rec->dpt2Date) $bump($a1, 'dpt2', $sex);
                if ($rec->dpt3Date) $bump($a1, 'dpt3', $sex);
                if ($rec->opv1Date) $bump($a1, 'opv1', $sex);
                if ($rec->opv2Date) $bump($a1, 'opv2', $sex);
                if ($rec->opv3Date) $bump($a1, 'opv3', $sex);
                if ($rec->ipv1Date) $bump($a1, 'ipv1', $sex);
                if ($rec->ipv2Date) $bump($a1, 'ipv2', $sex);
                if ($rec->pcv1Date) $bump($a1, 'pcv1', $sex);
                if ($rec->pcv2Date) $bump($a1, 'pcv2', $sex);
                if ($rec->pcv3Date) $bump($a1, 'pcv3', $sex);
                if ($rec->mmr1Date) $bump($a1, 'mmr1', $sex);
            } elseif ($birthYear === $year - 1) {
                if ($rec->dpt1Date) $bump($a3, 'dpt1', $sex);
                if ($rec->dpt2Date) $bump($a3, 'dpt2', $sex);
                if ($rec->dpt3Date) $bump($a3, 'dpt3', $sex);
                if ($rec->opv1Date) $bump($a3, 'opv1', $sex);
                if ($rec->opv2Date) $bump($a3, 'opv2', $sex);
                if ($rec->opv3Date) $bump($a3, 'opv3', $sex);
                if ($rec->ipv1Date) $bump($a3, 'ipv1', $sex);
                if ($rec->ipv2Date) $bump($a3, 'ipv2', $sex);
                if ($rec->pcv1Date) $bump($a3, 'pcv1', $sex);
                if ($rec->pcv2Date) $bump($a3, 'pcv2', $sex);
                if ($rec->pcv3Date) $bump($a3, 'pcv3', $sex);
                if ($rec->mmr1Date) $bump($a3, 'mmr1', $sex);
                if ($rec->mmr2Date) $bump($a3, 'mmr2', $sex);
                if ($rec->ficDate) $bump($a3, 'fic', $sex);
                if ($rec->cicDate) $bump($a3, 'cic', $sex);
            }
        }

        // A.4 School and Community-Based Immunization
        $schoolQuery = DB::table('child_immunization_school_records');
        $this->applyProfileLocationFilter($schoolQuery, 'profileId', $desc);
        $schoolRecords = $schoolQuery
            ->select('sex', 'gradeLevel', 'tdDate', 'mrDate', 'hpv1SbiDate', 'hpv1CbiDate', 'hpv2CbiDate', 'created_at')
            ->get();

        foreach ($schoolRecords as $rec) {
            if (! $this->timestampInPeriod($rec->created_at, $year, $month)) {
                continue;
            }

            $sex = $rec->sex;
            $grade = strtolower((string) $rec->gradeLevel);
            $isGrade1 = (bool) preg_match('/\bgrade\s*1\b|\b1st\b/', $grade);
            $isGrade7 = (bool) preg_match('/\bgrade\s*7\b|\b7th\b/', $grade);

            if ($isGrade1 && $rec->tdDate) $bump($a4, 'grade1Td', $sex);
            if ($isGrade1 && $rec->mrDate) $bump($a4, 'grade1Mr', $sex);
            if ($isGrade7 && $rec->tdDate) $bump($a4, 'grade7Td', $sex);
            if ($isGrade7 && $rec->mrDate) $bump($a4, 'grade7Mr', $sex);
            if ($rec->hpv1SbiDate) $bump($a4, 'hpv1Sbi', $sex);
            if ($rec->hpv1CbiDate) $bump($a4, 'hpv1Cbi', $sex);
            if ($rec->hpv2CbiDate) $bump($a4, 'hpv2Cbi', $sex);
        }

        return ['a1' => $a1, 'a3' => $a3, 'a4' => $a4];
    }

    /* =====================================================================
     * SECTION B. NON-COMMUNICABLE DISEASES
     * ===================================================================*/

    private function aggregateSectionB(int $year, ?int $month, array $desc): array
    {
        $sexEmpty = ['male' => 0, 'female' => 0, 'total' => 0];

        $riskAssessed2059 = $sexEmpty;
        $riskAssessed60plus = $sexEmpty;
        $identifiedHtn2059 = $sexEmpty;
        $identifiedHtn60plus = $sexEmpty;
        $identifiedDm2059 = $sexEmpty;
        $identifiedDm60plus = $sexEmpty;

        $bump = function (array &$bucket, ?string $sex) {
            $bucket['total']++;
            if ($sk = $this->sexKey($sex)) {
                $bucket[$sk]++;
            }
        };

        $query = DB::table('philpen_risk_assessments');
        $this->applyProfileLocationFilter($query, 'profile_id', $desc);
        $records = $query
            ->select('sex', 'age_group', 'hypertension_result', 'diabetes_result', 'date_assessment', 'created_at')
            ->get();

        foreach ($records as $rec) {
            if (! $this->timestampInPeriod($rec->created_at, $year, $month)) {
                continue;
            }

            $sex = $rec->sex;
            $isSenior = $rec->age_group === 'B';

            if ($isSenior) {
                $bump($riskAssessed60plus, $sex);
            } else {
                $bump($riskAssessed2059, $sex);
            }

            if ((int) $rec->hypertension_result === 1) {
                $isSenior ? $bump($identifiedHtn60plus, $sex) : $bump($identifiedHtn2059, $sex);
            }
            if ((int) $rec->diabetes_result === 1) {
                $isSenior ? $bump($identifiedDm60plus, $sex) : $bump($identifiedDm2059, $sex);
            }
        }

        return [
            'lifestyle2059' => $riskAssessed2059,
            'lifestyle60plus' => $riskAssessed60plus,
            'cvd2059' => $identifiedHtn2059,
            'cvd60plus' => $identifiedHtn60plus,
            'dm2059' => $identifiedDm2059,
            'dm60plus' => $identifiedDm60plus,
        ];
    }

    /* =====================================================================
     * SECTION C. VITAL STATISTICS
     * ===================================================================*/

    private function aggregateSectionC(int $year, ?int $month, array $desc): array
    {
        return [
            'mortality' => $this->getMortalityData($year, $month, $desc),
            'natality' => $this->getNatalityData($year, $month, $desc),
        ];
    }

    private function getMortalityData(int $year, ?int $month, array $desc): array
    {
        $ageEmpty = ['10-14' => 0, '15-19' => 0, '20-49' => 0, 'total' => 0];
        $sexEmpty = ['male' => 0, 'female' => 0, 'total' => 0];

        $maternalTotal = $ageEmpty;
        $direct = $ageEmpty;
        $directResident = $ageEmpty;
        $directNonResident = $ageEmpty;
        $indirect = $ageEmpty;
        $indirectResident = $ageEmpty;
        $indirectNonResident = $ageEmpty;

        $bumpAge = function (array &$bucket, ?string $ageGroup) {
            $bracket = match ($ageGroup) {
                'A' => '10-14',
                'B' => '15-19',
                'C' => '20-49',
                default => null,
            };
            if (! $bracket) {
                return;
            }
            $bucket[$bracket]++;
            $bucket['total']++;
        };

        $maternalQuery = DB::table('maternal_deaths');
        $this->applyProfileLocationFilter($maternalQuery, 'profile_id', $desc);
        $maternalRecords = $maternalQuery
            ->select('age_group', 'place_of_occurrence', 'cause_of_death', 'created_at')
            ->get();

        foreach ($maternalRecords as $rec) {
            if (! $this->timestampInPeriod($rec->created_at, $year, $month)) {
                continue;
            }

            $bumpAge($maternalTotal, $rec->age_group);

            $isResident = $rec->place_of_occurrence === 'A';
            $isDirect = $rec->cause_of_death === 'A';
            $isIndirect = $rec->cause_of_death === 'B';

            if ($isDirect) {
                $bumpAge($direct, $rec->age_group);
                $isResident ? $bumpAge($directResident, $rec->age_group) : $bumpAge($directNonResident, $rec->age_group);
            } elseif ($isIndirect) {
                $bumpAge($indirect, $rec->age_group);
                $isResident ? $bumpAge($indirectResident, $rec->age_group) : $bumpAge($indirectNonResident, $rec->age_group);
            }
        }

        $infant = $sexEmpty;
        $infantQuery = DB::table('infant_deaths');
        $this->applyProfileLocationFilter($infantQuery, 'profile_id', $desc);
        $infantRecords = $infantQuery->select('sex', 'created_at')->get();

        foreach ($infantRecords as $rec) {
            if (! $this->timestampInPeriod($rec->created_at, $year, $month)) {
                continue;
            }
            $infant['total']++;
            if ($sk = $this->sexKey($rec->sex)) {
                $infant[$sk]++;
            }
        }

        return [
            'maternalTotal' => $maternalTotal,
            'direct' => $direct,
            'directResident' => $directResident,
            'directNonResident' => $directNonResident,
            'indirect' => $indirect,
            'indirectResident' => $indirectResident,
            'indirectNonResident' => $indirectNonResident,
            'infant' => $infant,
        ];
    }

    private function getNatalityData(int $year, ?int $month, array $desc): array
    {
        $sexEmpty = ['male' => 0, 'female' => 0, 'total' => 0];

        $liveBirths = $sexEmpty;
        $adolescentTotal = $sexEmpty;
        $adolescentUnder10 = $sexEmpty;
        $adolescent10to14 = $sexEmpty;
        $adolescent15to19 = $sexEmpty;
        $repeatAdolescentTotal = $sexEmpty;
        $repeat10to14 = $sexEmpty;
        $repeat15to19 = $sexEmpty;

        $bump = function (array &$bucket, ?string $sex) {
            $bucket['total']++;
            if ($sk = $this->sexKey($sex)) {
                $bucket[$sk]++;
            }
        };

        // Join intrapartum outcomes back to the mother's record so we can
        // bracket adolescent births by her age and check parity (gravida).
        $query = DB::table('intrapartum_records')
            ->join('maternal_care_records', 'intrapartum_records.maternalRecordId', '=', 'maternal_care_records.id')
            ->select(
                'intrapartum_records.sex',
                'intrapartum_records.deliveryOutcome',
                'intrapartum_records.created_at as intrapartum_created_at',
                'maternal_care_records.age',
                'maternal_care_records.gravidaPara',
                'maternal_care_records.profileId'
            );
        $this->applyProfileLocationFilter($query, 'maternal_care_records.profileId', $desc);
        $records = $query->get();

        foreach ($records as $rec) {
            if (! $this->timestampInPeriod($rec->intrapartum_created_at, $year, $month)) {
                continue;
            }

            $outcome = strtolower((string) $rec->deliveryOutcome);
            $isFetalLoss = str_contains($outcome, 'fetal death') || str_contains($outcome, 'stillbirth')
                || str_contains($outcome, 'abortion') || str_contains($outcome, 'miscarriage');
            if ($isFetalLoss) {
                continue; // only live births count toward natality
            }

            $sex = $rec->sex;
            $bump($liveBirths, $sex);

            $age = $rec->age !== null ? (int) $rec->age : null;
            if ($age === null || $age >= 20) {
                continue;
            }

            $bump($adolescentTotal, $sex);
            if ($age < 10) {
                $bump($adolescentUnder10, $sex);
            } elseif ($age <= 14) {
                $bump($adolescent10to14, $sex);
            } else {
                $bump($adolescent15to19, $sex);
            }

            preg_match('/G\s*(\d+)/i', (string) $rec->gravidaPara, $gMatch);
            $gravida = isset($gMatch[1]) ? (int) $gMatch[1] : null;
            $isRepeat = $gravida !== null && $gravida >= 2;

            if ($isRepeat) {
                $bump($repeatAdolescentTotal, $sex);
                if ($age >= 10 && $age <= 14) {
                    $bump($repeat10to14, $sex);
                } elseif ($age >= 15 && $age <= 19) {
                    $bump($repeat15to19, $sex);
                }
            }
        }

        return [
            'liveBirths' => $liveBirths,
            'adolescentTotal' => $adolescentTotal,
            'adolescentUnder10' => $adolescentUnder10,
            'adolescent10to14' => $adolescent10to14,
            'adolescent15to19' => $adolescent15to19,
            'repeatAdolescentTotal' => $repeatAdolescentTotal,
            'repeat10to14' => $repeat10to14,
            'repeat15to19' => $repeat15to19,
        ];
    }

    /* =====================================================================
     * Helpers
     * ===================================================================*/

    /**
     * Resolves region/province/municipality/barangay codes from the request
     * into the descriptive text stored on household_profiles.
     */
    private function resolveLocationDescriptions(array $validated): array
    {
        return [
            'region' => ! empty($validated['region'])
                ? optional(DB::table('regions')->where('regCode', $validated['region'])->first())->regDesc
                : null,
            'province' => ! empty($validated['province'])
                ? optional(DB::table('provinces')->where('provCode', $validated['province'])->first())->provDesc
                : null,
            'municipality' => ! empty($validated['municipality'])
                ? optional(DB::table('municipalities')->where('citymunCode', $validated['municipality'])->first())->citymunDesc
                : null,
            'barangay' => ! empty($validated['barangay'])
                ? optional(DB::table('barangays')->where('brgyCode', $validated['barangay'])->first())->brgyDesc
                : null,
        ];
    }

    /**
     * Scopes a query (on a table that references household_profiles via the
     * given foreign-key column) to the resolved region/province/municipality/
     * barangay description filters.
     */
    private function applyProfileLocationFilter($query, string $column, array $desc): void
    {
        if (! $desc['region'] && ! $desc['province'] && ! $desc['municipality'] && ! $desc['barangay']) {
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
     * Whether a created_at timestamp falls within the requested year (and,
     * if given, month).
     */
    private function timestampInPeriod($createdAt, int $year, ?int $month): bool
    {
        if (empty($createdAt)) {
            return false;
        }

        try {
            $date = Carbon::parse($createdAt);
        } catch (\Throwable $e) {
            return false;
        }

        if ((int) $date->year !== $year) {
            return false;
        }

        if ($month !== null && (int) $date->month !== $month) {
            return false;
        }

        return true;
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
}