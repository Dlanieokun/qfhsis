<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use App\Models\FhsisReport;
use App\Models\HouseholdProfile;
use App\Models\MaternalCareRecord;
use App\Models\Prenatal8AncRecord;
use App\Models\PrenatalImmunizationRecord;
use App\Models\PrenatalSupplementationRecord;
use App\Models\PrenatalLabScreeningRecord;
use App\Models\IntrapartumRecord;
use App\Models\PostpartumRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Cache key for dashboard module counts.
     */
    private const CACHE_KEY = 'dashboard_module_counts';

    /**
     * Cache TTL in seconds (24 hours).
     */
    private const CACHE_TTL = 86400;

    /**
     * Display the dashboard.
     */
    public function index(): Response
    {
        // Get all reports
        $reports = FhsisReport::query()
            ->select([
                'id',
                'reporting_year',
                'reporting_quarter',
                'total_pregnant_tracked',
                'completed_4_anc_visits',
                'fully_immunized_children',
                'infants_exclusive_breastfed',
                'status',
            ])
            ->orderBy('reporting_year', 'desc')
            ->orderBy('reporting_quarter', 'desc')
            ->get();

        // Get module counts with caching
        $moduleCounts = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn() => $this->getModuleCounts()
        );

        return Inertia::render('dashboard', [
            'reports' => $reports,
            'moduleCounts' => $moduleCounts,
            'auth' => [
                'user' => [
                    'name' => auth()->user()->name,
                    'role' => auth()->user()->role ?? 'User',
                    'assigned_facility' => auth()->user()->assigned_facility ?? null,
                ],
            ],
        ]);
    }

    /**
     * Get counts for all modules.
     * Called by cache layer to populate module counts.
     * 
     * @return array<string, int>
     */
    private function getModuleCounts(): array
    {
        return [
            'household_profiles' => HouseholdProfile::count(),
            'maternal_care_records' => MaternalCareRecord::count(),
            'prenatal_8anc_records' => Prenatal8AncRecord::count(),
            'prenatal_immunization_records' => PrenatalImmunizationRecord::count(),
            'prenatal_supplementation_records' => PrenatalSupplementationRecord::count(),
            'prenatal_lab_screening_records' => PrenatalLabScreeningRecord::count(),
            'intrapartum_records' => IntrapartumRecord::count(),
            'postpartum_records' => PostpartumRecord::count(),
            'child_immunization_records' => DB::table('child_immunization_records')->count(),
            'child_immunization_school_records' => DB::table('child_immunization_school_records')->count(),
            'child_nutrition_records' => DB::table('child_nutrition_records')->count(),
            'child_sick_records' => DB::table('child_sick_records')->count(),
            'new_acceptor' => DB::table('family_planning_records')
                ->whereIn('method_code', ['IUD', 'IMPLANT', 'PILLS', 'INJECTION', 'BARRIER'])
                ->whereRaw('YEAR(created_at) = YEAR(NOW())')
                ->count(),
            'other_acceptor' => DB::table('family_planning_records')
                ->whereRaw('YEAR(created_at) < YEAR(NOW())')
                ->count(),
            'drop_outs' => DB::table('family_planning_drop_outs')->count(),
            'current_acceptors' => DB::table('family_planning_records')
                ->whereNull('date_discontinued')
                ->count(),
            'filariasis_registry_table' => DB::table('filariasis_registry')->count(),
            'schistosomiasis_registry' => DB::table('schistosomiasis_registry')->count(),
            'sth_registry_records' => DB::table('sth_registry_records')->count(),
            'leprosy_registry' => DB::table('leprosy_registry')->count(),
            'rabies_records' => DB::table('rabies_records')->count(),
            'philpen_risk_assessments' => DB::table('philpen_risk_assessments')->count(),
            'cervical_cancer_screenings' => DB::table('cervical_cancer_screenings')->count(),
            'eyes_screenings' => DB::table('eyes_screenings')->count(),
            'oral_health_care' => DB::table('oral_health_care')->count(),
            'geriatric_screening_records' => DB::table('geriatric_screening_records')->count(),
            'mental_health_records' => DB::table('mental_health_records')->count(),
            'environmental_health_records' => DB::table('environmental_health_records')->count(),
        ];
    }

    /**
     * Manually clear the dashboard cache.
     * Call this after batch operations that modify record counts.
     * 
     * Used by Model Observers to keep cache fresh.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}