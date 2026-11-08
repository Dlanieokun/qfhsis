<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use App\Models\FhsisReport;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    /**
     * Cache key holding the current "generation" of dashboard counts.
     * Bumping it (clearCache) invalidates every user's cached counts at once.
     */
    private const CACHE_VERSION_KEY = 'dashboard_module_counts_version';

    /**
     * Cache TTL in seconds (10 minutes). Counts are per user + month, so keep it short.
     */
    private const CACHE_TTL = 600;

    /**
     * How each dashboard module's table links back to household_profiles,
     * which is where the address (barangay / municipality / province / region) lives.
     *
     *  - 'direct'   table has its own address columns
     *  - 'profile'  table has a column pointing at household_profiles.id
     *  - 'maternal' table points at maternal_care_records.id, which points at household_profiles
     *
     * For 'profile' tables, the FK column is detected at runtime (profileId / profile_id).
     */
    private const MODULE_TABLES = [
        'household_profiles'                => ['table' => 'household_profiles', 'relation' => 'direct'],
        'maternal_care_records'             => ['table' => 'maternal_care_records', 'relation' => 'profile'],
        'prenatal_8anc_records'             => ['table' => 'prenatal_8anc_records', 'relation' => 'maternal', 'column' => 'maternalRecordId'],
        'prenatal_immunization_records'     => ['table' => 'prenatal_immunization_records', 'relation' => 'maternal', 'column' => 'maternalRecordId'],
        'prenatal_supplementation_records'  => ['table' => 'prenatal_supplementation_records', 'relation' => 'maternal', 'column' => 'maternal_record_id'],
        'prenatal_lab_screening_records'    => ['table' => 'prenatal_lab_screening_records', 'relation' => 'maternal', 'column' => 'maternalRecordId'],
        'intrapartum_records'               => ['table' => 'intrapartum_records', 'relation' => 'maternal', 'column' => 'maternalRecordId'],
        'postpartum_records'                => ['table' => 'postpartum_records', 'relation' => 'maternal', 'column' => 'maternalRecordId'],
        'child_immunization_records'        => ['table' => 'child_immunization_records', 'relation' => 'profile'],
        'child_immunization_school_records' => ['table' => 'child_immunization_school_records', 'relation' => 'profile'],
        'child_nutrition_records'           => ['table' => 'child_nutrition_records', 'relation' => 'profile'],
        'child_sick_records'                => ['table' => 'child_sick_records', 'relation' => 'profile'],
        'drop_outs'                         => ['table' => 'family_planning_drop_outs', 'relation' => 'profile'],
        'filariasis_registry_table'         => ['table' => 'filariasis_registry_table', 'relation' => 'profile'],
        'schistosomiasis_registry'          => ['table' => 'schistosomiasis_registry', 'relation' => 'profile'],
        'sth_registry_records'              => ['table' => 'sth_registry_records', 'relation' => 'profile'],
        'leprosy_registry'                  => ['table' => 'leprosy_registry', 'relation' => 'profile'],
        'rabies_records'                    => ['table' => 'rabies_records', 'relation' => 'profile'],
        'philpen_risk_assessments'          => ['table' => 'philpen_risk_assessments', 'relation' => 'profile'],
        'cervical_cancer_screenings'        => ['table' => 'cervical_cancer_screenings', 'relation' => 'profile'],
        'eyes_screenings'                   => ['table' => 'eyes_screenings', 'relation' => 'profile'],
        'oral_health_care'                  => ['table' => 'oral_health_care', 'relation' => 'profile'],
        'geriatric_screening_records'       => ['table' => 'geriatric_screening_records', 'relation' => 'profile'],
        'mental_health_records'             => ['table' => 'mental_health_records', 'relation' => 'profile'],
        'environmental_health_records'      => ['table' => 'environmental_health_records', 'relation' => 'profile'],
    ];

    /**
     * Display the dashboard.
     */
    public function index(): Response
    {
        /** @var User $user */
        $user = auth()->user();
        $now = now();

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

        // Counts for the current month/year within the user's assigned location.
        $cacheKey = sprintf(
            'dashboard_module_counts:v2.%d:u%d:%s',
            (int) Cache::get(self::CACHE_VERSION_KEY, 0),
            $user->id,
            $now->format('Y-m')
        );

        $moduleCounts = Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn() => $this->getModuleCounts($user, $now)
        );

        return Inertia::render('dashboard', [
            'reports' => $reports,
            'moduleCounts' => $moduleCounts,
            'period' => [
                'month' => $now->month,
                'year' => $now->year,
                'label' => $now->format('F Y'),
            ],
            'location' => $this->locationLabel($user),
            'auth' => [
                'user' => [
                    'name' => $user->name,
                    'role' => $user->role ?? 'User',
                    'assigned_facility' => $user->assigned_facility ?? null,
                ],
            ],
        ]);
    }

    /**
     * Get counts for all modules, limited to records created in the given
     * month/year and belonging to the user's assigned location.
     *
     * @return array<string, int>
     */
    private function getModuleCounts(User $user, Carbon $now): array
    {
        $counts = [];

        foreach (self::MODULE_TABLES as $key => $def) {
            $counts[$key] = $this->safeCount($key, function () use ($key, $def, $user, $now) {
                $query = $this->scopedQuery($def, $user);
                if ($query === null) {
                    return 0;
                }
                // Household profiles are the base registry: count every one in
                // the user's area, regardless of when it was entered.
                if ($key !== 'household_profiles') {
                    $this->constrainToMonth($query, $def['table'], $now);
                }
                return $query->count();
            });
        }

        $fp = ['table' => 'family_planning_records', 'relation' => 'profile'];

        // New acceptors: family planning clients registered this month.
        $counts['new_acceptor'] = $this->safeCount('new_acceptor', function () use ($fp, $user, $now) {
            $query = $this->scopedQuery($fp, $user);
            if ($query === null) {
                return 0;
            }
            $this->constrainToMonth($query, $fp['table'], $now);
            return $query->count();
        });

        // Other acceptors: clients registered before this month.
        $counts['other_acceptor'] = $this->safeCount('other_acceptor', function () use ($fp, $user, $now) {
            $query = $this->scopedQuery($fp, $user);
            if ($query === null) {
                return 0;
            }
            return $query->where('created_at', '<', $now->copy()->startOfMonth())->count();
        });

        // Current acceptors: everyone registered up to the end of this month
        // who hasn't been discontinued (when the schema tracks that).
        $counts['current_acceptors'] = $this->safeCount('current_acceptors', function () use ($fp, $user, $now) {
            $query = $this->scopedQuery($fp, $user);
            if ($query === null) {
                return 0;
            }
            $query->where('created_at', '<=', $now->copy()->endOfMonth());
            if (Schema::hasColumn($fp['table'], 'date_discontinued')) {
                $query->whereNull('date_discontinued');
            }
            return $query->count();
        });

        return $counts;
    }

    /**
     * Run a count, logging and returning 0 if the table/columns don't match.
     */
    private function safeCount(string $key, callable $fn): int
    {
        try {
            return (int) $fn();
        } catch (\Throwable $e) {
            \Log::error("Dashboard: error counting {$key}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Build a query on the module's table restricted to the user's location.
     * Returns null when the table can't be tied to a location but the user
     * has one assigned (so other areas' data is never shown).
     */
    private function scopedQuery(array $def, User $user): ?Builder
    {
        $table = $def['table'];
        $query = DB::table($table);

        if (!$this->userHasAssignedLocation($user)) {
            // National / admin accounts with no assigned area see everything.
            return $query;
        }

        switch ($def['relation']) {
            case 'direct':
                $this->constrainToUserLocation($query, $user, $table);
                return $query;

            case 'profile':
                $column = $def['column'] ?? $this->profileColumn($table);
                if ($column === null) {
                    return null;
                }
                return $query->whereIn($column, function ($sub) use ($user) {
                    $sub->select('id')->from('household_profiles');
                    $this->constrainToUserLocation($sub, $user);
                });

            case 'maternal':
                $column = $def['column'];
                if (!Schema::hasColumn($table, $column)) {
                    return null;
                }
                return $query->whereIn($column, function ($sub) use ($user) {
                    $sub->select('id')->from('maternal_care_records')
                        ->whereIn('profileId', function ($sub2) use ($user) {
                            $sub2->select('id')->from('household_profiles');
                            $this->constrainToUserLocation($sub2, $user);
                        });
                });
        }

        return null;
    }

    /**
     * Find the column pointing at household_profiles.id on a table.
     */
    private function profileColumn(string $table): ?string
    {
        foreach (['profileId', 'profile_id'] as $candidate) {
            if (Schema::hasColumn($table, $candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Limit a query to rows created in the given month and year.
     */
    private function constrainToMonth(Builder $query, string $table, Carbon $now): void
    {
        if (Schema::hasColumn($table, 'created_at')) {
            $query->whereBetween("{$table}.created_at", [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ]);
        }
    }

    /**
     * Apply the user's narrowest assigned level
     * (barangay > municipality > province > region). This is the same rule
     * the mobile sync uses, so web and app show the same records.
     */
    private function constrainToUserLocation($query, User $user, string $table = 'household_profiles'): void
    {
        $barangays = $this->userBarangays($user);

        if (!empty($barangays) && Schema::hasColumn($table, 'barangay')) {
            $query->whereIn('barangay', $barangays);
        } elseif (!empty($user->municipality) && Schema::hasColumn($table, 'municipality')) {
            $query->where('municipality', $user->municipality);
        } elseif (!empty($user->province) && Schema::hasColumn($table, 'province')) {
            $query->where('province', $user->province);
        } elseif (!empty($user->region) && Schema::hasColumn($table, 'region')) {
            $query->where('region', $user->region);
        }
    }

    private function userHasAssignedLocation(User $user): bool
    {
        return !empty($this->userBarangays($user))
            || !empty($user->municipality)
            || !empty($user->province)
            || !empty($user->region);
    }

    /**
     * users.barangay is cast to an array, but tolerate a raw JSON string too.
     *
     * @return array<int, string>
     */
    private function userBarangays(User $user): array
    {
        $value = $user->barangay;
        if (is_string($value) && $value !== '') {
            $value = json_decode($value, true);
        }
        return is_array($value) ? array_values(array_filter($value)) : [];
    }

    /**
     * Human-readable label of the user's assigned area for the header.
     */
    private function locationLabel(User $user): ?string
    {
        $parts = [];
        $barangays = $this->userBarangays($user);
        if (!empty($barangays)) {
            $parts[] = count($barangays) > 3
                ? count($barangays) . ' barangays'
                : implode(', ', $barangays);
        }
        foreach (['municipality', 'province', 'region'] as $level) {
            if (!empty($user->{$level})) {
                $parts[] = $user->{$level};
            }
        }
        return empty($parts) ? null : implode(' · ', $parts);
    }

    /**
     * Invalidate every user's cached dashboard counts.
     * Called by model observers after records change.
     */
    public static function clearCache(): void
    {
        Cache::forever(self::CACHE_VERSION_KEY, (int) Cache::get(self::CACHE_VERSION_KEY, 0) + 1);
    }
}
