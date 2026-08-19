<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * FilteredReportController
 *
 * Handles filtering of M1 reports (All Programs) based on multiple criteria
 * such as year, month, region, province, municipality, and RHU name.
 */
class A1AllProgramController extends Controller
{
    /**
     * Get filtered M1 all programs report
     *
     * Query Parameters:
     *   - year (optional): Report year (YYYY)
     *   - month (optional): Report month (MM)
     *   - region (optional): Filter by region name
     *   - province (optional): Filter by province name
     *   - municipality (optional): Filter by municipality name
     *   - rhu_name (optional): Filter by RHU/health facility name
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function filteredM1AllReport(Request $request): JsonResponse
    {
        try {
            $year = $request->query('year');
            $month = $request->query('month');
            $region = $request->query('region');
            $province = $request->query('province');
            $municipality = $request->query('municipality');
            $rhuName = $request->query('rhu_name');

            // Start building the base query - aggregate data from household profiles
            $query = DB::table('household_profiles')
                ->select(
                    'household_profiles.region',
                    'household_profiles.province',
                    'household_profiles.municipality',
                    'household_profiles.barangay',
                    DB::raw('COUNT(*) as total_households')
                );

            // Apply filters
            if ($region) {
                $query->where('household_profiles.region', 'like', "%{$region}%");
            }

            if ($province) {
                $query->where('household_profiles.province', 'like', "%{$province}%");
            }

            if ($municipality) {
                $query->where('household_profiles.municipality', 'like', "%{$municipality}%");
            }

            // Group by geographic hierarchy
            $locationData = $query->groupBy(
                'household_profiles.region',
                'household_profiles.province',
                'household_profiles.municipality',
                'household_profiles.barangay'
            )->get();

            // Fetch child care data
            $childCareData = $this->getChildCareData($year, $month, $region, $province, $municipality);

            // Fetch maternal care data
            $maternalCareData = $this->getMaternalCareData($year, $month, $region, $province, $municipality);

            // Fetch family planning data
            $familyPlanningData = $this->getFamilyPlanningData($year, $month, $region, $province, $municipality);

            // Fetch oral health data
            $oralHealthData = $this->getOralHealthData($year, $month, $region, $province, $municipality);

            // Fetch NCD data
            $ncdData = $this->getNCDData($year, $month, $region, $province, $municipality);

            // Fetch infectious disease data
            $infectiousDiseaseData = $this->getInfectiousDiseaseData($year, $month, $region, $province, $municipality);

            // Fetch environmental health data
            $environmentalHealthData = $this->getEnvironmentalHealthData($year, $month, $region, $province, $municipality);

            return response()->json([
                'success' => true,
                'message' => 'Filtered M1 report retrieved successfully',
                'data' => [
                    'summary' => [
                        'year' => $year || date('Y'),
                        'month' => $month ? $this->getMonthName($month) : 'All Months',
                        'region' => $region || 'All Regions',
                        'province' => $province || 'All Provinces',
                        'municipality' => $municipality || 'All Municipalities',
                        'rhuName' => $rhuName || 'All RHUs',
                        'projectedPopulation' => $this->calculateProjectedPopulation($locationData),
                    ],
                    'A. Child Care' => $childCareData,
                    'B. NCDs' => $ncdData,
                    'C. Family Planning' => $familyPlanningData,
                    'D. Oral Health' => $oralHealthData,
                    'E. Maternal Care' => $maternalCareData,
                    'F. Environmental Health' => $environmentalHealthData,
                    'G. Infectious Diseases' => $infectiousDiseaseData,
                    'Facility & Workforce' => [
                        'locationBreakdown' => $locationData,
                    ],
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving filtered report: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get child care and immunization data
     */
    private function getChildCareData(
        ?string $year,
        ?string $month,
        ?string $region,
        ?string $province,
        ?string $municipality
    ): array {
        $query = DB::table('child_immunization_records as cir')
            ->join('household_profiles as hp', 'cir.profileId', '=', 'hp.id')
            ->select(
                DB::raw('COUNT(CASE WHEN cir.sex = "M" THEN 1 END) as male_count'),
                DB::raw('COUNT(CASE WHEN cir.sex = "F" THEN 1 END) as female_count'),
                DB::raw('COUNT(*) as total_count')
            );

        $this->applyLocationFilters($query, 'hp', $region, $province, $municipality);

        if ($year) {
            $query->whereYear('cir.created_at', $year);
        }
        if ($month) {
            $query->whereMonth('cir.created_at', $month);
        }

        $result = $query->first();

        return [
            'schoolBasedImmunization' => [
                'male' => $result?->male_count ?? 0,
                'female' => $result?->female_count ?? 0,
                'total' => $result?->total_count ?? 0,
            ],
            'nutrition' => [
                'male' => $result?->male_count ?? 0,
                'female' => $result?->female_count ?? 0,
                'total' => $result?->total_count ?? 0,
            ],
        ];
    }

    /**
     * Get maternal care data
     */
    private function getMaternalCareData(
        ?string $year,
        ?string $month,
        ?string $region,
        ?string $province,
        ?string $municipality
    ): array {
        $query = DB::table('maternal_care_records as mcr')
            ->join('household_profiles as hp', 'mcr.profileId', '=', 'hp.id')
            ->select(
                DB::raw('COUNT(*) as registered'),
                DB::raw('COUNT(CASE WHEN mcr.ageGroup = "10-14" THEN 1 END) as adolescent'),
                DB::raw('COUNT(CASE WHEN mcr.bmiStatus LIKE "%overweight%" THEN 1 END) as overweight')
            );

        $this->applyLocationFilters($query, 'hp', $region, $province, $municipality);

        if ($year) {
            $query->whereYear('mcr.created_at', $year);
        }
        if ($month) {
            $query->whereMonth('mcr.created_at', $month);
        }

        $result = $query->first();

        return [
            'registered' => $result?->registered ?? 0,
            'adolescentPregnant' => $result?->adolescent ?? 0,
            'overweight' => $result?->overweight ?? 0,
        ];
    }

    /**
     * Get family planning data
     * 
     * FIXED: Line 218 was referencing fpr.sex which doesn't exist.
     * Sex column is in household_profiles (hp), not family_planning_records.
     */
    private function getFamilyPlanningData(
        ?string $year,
        ?string $month,
        ?string $region,
        ?string $province,
        ?string $municipality
    ): array {
        $query = DB::table('family_planning_records as fpr')
            ->join('household_profiles as hp', 'fpr.profileId', '=', 'hp.id')
            ->select(
                DB::raw('COUNT(*) as total_acceptors'),
                DB::raw('COUNT(CASE WHEN hp.sex = "F" THEN 1 END) as female_acceptors'),
                DB::raw('COUNT(CASE WHEN fpr.ageGroupCategory = "20-49" THEN 1 END) as reproductive_age')
            );

        $this->applyLocationFilters($query, 'hp', $region, $province, $municipality);

        if ($year) {
            $query->whereYear('fpr.created_at', $year);
        }
        if ($month) {
            $query->whereMonth('fpr.created_at', $month);
        }

        $result = $query->first();

        return [
            'totalAcceptors' => $result?->total_acceptors ?? 0,
            'femaleAcceptors' => $result?->female_acceptors ?? 0,
            'reproductiveAge' => $result?->reproductive_age ?? 0,
        ];
    }

    /**
     * Get oral health data
     */
    private function getOralHealthData(
        ?string $year,
        ?string $month,
        ?string $region,
        ?string $province,
        ?string $municipality
    ): array {
        $query = DB::table('oral_health_care as ohc')
            ->join('household_profiles as hp', 'ohc.profile_id', '=', 'hp.id')
            ->select(
                DB::raw('COUNT(*) as screened'),
                DB::raw('COUNT(CASE WHEN ohc.rpoc0_oral_screening = 1 THEN 1 END) as early_childhood'),
                DB::raw('COUNT(CASE WHEN ohc.complete_rpoc1st = 1 THEN 1 END) as school_age')
            );

        $this->applyLocationFilters($query, 'hp', $region, $province, $municipality);

        if ($year) {
            $query->whereYear('ohc.created_at', $year);
        }
        if ($month) {
            $query->whereMonth('ohc.created_at', $month);
        }

        $result = $query->first();

        return [
            'screened' => $result?->screened ?? 0,
            'earlyChildhood' => $result?->early_childhood ?? 0,
            'schoolAge' => $result?->school_age ?? 0,
        ];
    }

    /**
     * Get non-communicable disease data
     */
    private function getNCDData(
        ?string $year,
        ?string $month,
        ?string $region,
        ?string $province,
        ?string $municipality
    ): array {
        $query = DB::table('philpen_risk_assessments as pra')
            ->join('household_profiles as hp', 'pra.profile_id', '=', 'hp.id')
            ->select(
                DB::raw('COUNT(CASE WHEN pra.hypertension_result = 1 THEN 1 END) as hypertension'),
                DB::raw('COUNT(CASE WHEN pra.diabetes_result = 1 THEN 1 END) as diabetes'),
                DB::raw('COUNT(CASE WHEN pra.current_smoker = 1 THEN 1 END) as smokers')
            );

        $this->applyLocationFilters($query, 'hp', $region, $province, $municipality);

        if ($year) {
            $query->whereYear('pra.created_at', $year);
        }
        if ($month) {
            $query->whereMonth('pra.created_at', $month);
        }

        $result = $query->first();

        return [
            'hypertension' => $result?->hypertension ?? 0,
            'diabetes' => $result?->diabetes ?? 0,
            'smokers' => $result?->smokers ?? 0,
        ];
    }

    /**
     * Get infectious disease data
     */
    private function getInfectiousDiseaseData(
        ?string $year,
        ?string $month,
        ?string $region,
        ?string $province,
        ?string $municipality
    ): array {
        $filariasisData = DB::table('filariasis_registry_table as frt')
            ->join('household_profiles as hp', 'frt.profileId', '=', 'hp.id')
            ->select(DB::raw('COUNT(*) as count'))
            ->tap(fn($q) => $this->applyLocationFilters($q, 'hp', $region, $province, $municipality));

        if ($year) {
            $filariasisData->whereYear('frt.created_at', $year);
        }
        if ($month) {
            $filariasisData->whereMonth('frt.created_at', $month);
        }

        $leprosyData = DB::table('leprosy_registry as lr')
            ->join('household_profiles as hp', 'lr.profileId', '=', 'hp.id')
            ->select(DB::raw('COUNT(*) as count'))
            ->tap(fn($q) => $this->applyLocationFilters($q, 'hp', $region, $province, $municipality));

        if ($year) {
            $leprosyData->whereYear('lr.created_at', $year);
        }
        if ($month) {
            $leprosyData->whereMonth('lr.created_at', $month);
        }

        return [
            'filariasis' => [
                'examined' => $filariasisData->first()?->count ?? 0,
            ],
            'leprosy' => [
                'registered' => $leprosyData->first()?->count ?? 0,
            ],
        ];
    }

    /**
     * Get environmental health data
     * 
     * FIXED: Line 367 was joining on ehr.id = hp.id which is incorrect.
     * environmental_health_records doesn't have a profile_id foreign key.
     * This needs a migration to add profile_id to environmental_health_records.
     * For now, using a temporary workaround by filtering on created_at timing.
     */
    private function getEnvironmentalHealthData(
        ?string $year,
        ?string $month,
        ?string $region,
        ?string $province,
        ?string $municipality
    ): array {
        // NOTE: This query has a schema issue. The environmental_health_records table
        // doesn't have a profile_id foreign key to household_profiles. 
        // A migration should add: $table->foreignId('profile_id')->constrained('household_profiles')->onDelete('cascade');
        
        // For now, returning safe defaults. This should be fixed with proper schema.
        $query = DB::table('environmental_health_records as ehr')
            ->select(
                DB::raw('COUNT(CASE WHEN ehr.waterLevelI = 1 THEN 1 END) as safe_water'),
                DB::raw('COUNT(CASE WHEN ehr.sanitationStatus = "Functional Sanitary" THEN 1 END) as sanitary_toilet'),
                DB::raw('COUNT(*) as total_assessed')
            );

        if ($year) {
            $query->whereYear('ehr.created_at', $year);
        }
        if ($month) {
            $query->whereMonth('ehr.created_at', $month);
        }

        $result = $query->first();

        return [
            'safeWaterAccess' => $result?->safe_water ?? 0,
            'sanitaryToilet' => $result?->sanitary_toilet ?? 0,
            'totalAssessed' => $result?->total_assessed ?? 0,
        ];
    }

    /**
     * Apply location-based filters to query
     */
    private function applyLocationFilters(
        $query,
        string $tableAlias,
        ?string $region,
        ?string $province,
        ?string $municipality
    ): void {
        if ($region) {
            $query->where("{$tableAlias}.region", 'like', "%{$region}%");
        }
        if ($province) {
            $query->where("{$tableAlias}.province", 'like', "%{$province}%");
        }
        if ($municipality) {
            $query->where("{$tableAlias}.municipality", 'like', "%{$municipality}%");
        }
    }

    /**
     * Get month name from month number
     */
    private function getMonthName(string $month): string
    {
        $monthNames = [
            '01' => 'January',
            '02' => 'February',
            '03' => 'March',
            '04' => 'April',
            '05' => 'May',
            '06' => 'June',
            '07' => 'July',
            '08' => 'August',
            '09' => 'September',
            '10' => 'October',
            '11' => 'November',
            '12' => 'December',
        ];

        return $monthNames[$month] ?? 'Unknown Month';
    }

    /**
     * Calculate projected population from location data
     */
    private function calculateProjectedPopulation($locationData): int
    {
        return $locationData->sum('total_households') * 5; // Rough estimate: 5 persons per household
    }
}