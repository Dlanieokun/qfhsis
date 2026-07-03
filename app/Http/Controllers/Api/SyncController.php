<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema; // Added for safe table column checking
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    //  Shared table mapping (Android JSON key → DB table name)
    // ─────────────────────────────────────────────────────────────
    private array $tablesMapping = [
        // Philippine Locations (Reference Data)
        'regions'                         => 'regions',
        'provinces'                       => 'provinces',
        'municipalities'                  => 'municipalities',
        'barangays'                       => 'barangays',

        // Household & Family Planning
        'householdProfiles'               => 'household_profiles',
        'familyPlanningRecords'           => 'family_planning_records',
        'maternalCareRecords'             => 'maternal_care_records',
        'classificationEntities'          => 'classification_metrics',
        'followUpEntities'                => 'family_planning_follow_ups',
        'dropOutEntities'                 => 'family_planning_drop_outs',
        
        // Maternal Care
        'prenatal8AncEntities'            => 'prenatal_8anc_records',
        'prenatalImmunizationEntities'    => 'prenatal_immunization_records',
        'prenatalSupplementationEntities' => 'prenatal_supplementation_records',
        'prenatalLabScreeningEntities'    => 'prenatal_lab_screening_records',
        'intrapartumEntities'             => 'intrapartum_records',
        'postpartumEntities'              => 'postpartum_records',
        
        // Child Health Tables
        'childImmunizationRecords'        => 'child_immunization_records',
        'childImmunizationSchoolRecords'  => 'child_immunization_school_records',
        'childNutritionRecords'           => 'child_nutrition_records',
        'childSickRecords'                => 'child_sick_records',

        // Oral Health Care & NCD
        'oralHealthCareRecords'           => 'oral_health_care',
        'philpenRiskAssessments'          => 'philpen_risk_assessments',
        'eyesScreenings'                  => 'eyes_screenings',
        'cervicalCancerScreenings'        => 'cervical_cancer_screenings',
        'geriatricScreeningRecords'       => 'geriatric_screening_records',

        // Infectious Disease (IDPCS)
        'filariasisRegistryRecords'       => 'filariasis_registry_table',
        'leprosyRegistryRecords'          => 'leprosy_registry',
        'rabiesRecords'                   => 'rabies_records',
        'schistosomiasisRegistryRecords'  => 'schistosomiasis_registry',
        'sthRegistryRecords'              => 'sth_registry_records',

        // --- NEW: Mental & Environmental Health ---
        'mentalHealthRecords'             => 'mental_health_records',
        'environmentalHealthRecords'      => 'environmental_health_records',
    ];

    // ─────────────────────────────────────────────────────────────
    //  PUSH  –  Android  →  Laravel
    // ─────────────────────────────────────────────────────────────
    public function syncFromAndroid(Request $request)
    {
        $skippedRecords = [];

        try {
            foreach ($this->tablesMapping as $jsonKey => $dbTableName) {

                // 1. SAFETY SHIELD: Skip reference lookup tables IMMEDIATELY.
                if (in_array($dbTableName, ['regions', 'provinces', 'municipalities', 'barangays'])) {
                    continue;
                }

                // 2. Extract the data payload for this specific table
                $records = $request->input($jsonKey);

                // 3. DEFENSIVE GUARD: Ensure the payload is actually an array.
                if (!is_array($records)) {
                    continue;
                }

                DB::transaction(function () use ($dbTableName, $records, &$skippedRecords) {
                    foreach ($records as $record) {

                        // GUARD: skip anything that isn't a proper associative record
                        if (!is_array($record)) {
                            Log::warning("Sync push: skipped non-array record for table {$dbTableName}", [
                                'record' => $record,
                            ]);
                            $skippedRecords[] = ['table' => $dbTableName, 'reason' => 'not_an_array'];
                            continue;
                        }

                        // Determine primary key name for the current table (Handle exceptions)
                        $pkName = 'id';
                        $jsonPkName = 'id';
                        
                        if ($dbTableName === 'geriatric_screening_records') {
                            $pkName = 'record_no';
                            $jsonPkName = array_key_exists('record_no', $record) ? 'record_no' : 'recordNo';
                        } elseif ($dbTableName === 'mental_health_records') {
                            $pkName = 'recordNo'; // Matches Laravel migration column
                            $jsonPkName = 'recordNo';
                        }

                        // GUARD: every record must carry a usable primary key id.
                        if (!array_key_exists($jsonPkName, $record) || $record[$jsonPkName] === null || $record[$jsonPkName] === '') {
                            Log::warning("Sync push: skipped record with missing PK for table {$dbTableName}", [
                                'record' => $record,
                            ]);
                            $skippedRecords[] = ['table' => $dbTableName, 'reason' => 'missing_id'];
                            continue;
                        }

                        // ==========================================
                        // SPECIAL DATA FORMATTING / TYPE CONVERSIONS
                        // ==========================================

                        // classification_metrics: millisecond timestamp → datetime
                        if ($dbTableName === 'classification_metrics') {
                            $timestamp = now();
                            if (isset($record['created_at_timestamp'])) {
                                $timestamp = date('Y-m-d H:i:s', $record['created_at_timestamp'] / 1000);
                                unset($record['created_at_timestamp']);
                            }
                            $record['created_at'] = $timestamp;
                            $record['updated_at'] = now();
                        }

                        // JSON Array casting (Query Builder requires json_encode for arrays)
                        if ($dbTableName === 'philpen_risk_assessments') {
                            if (isset($record['monthly_meds']) && is_array($record['monthly_meds'])) {
                                $record['monthly_meds'] = json_encode($record['monthly_meds']);
                            }
                            // FIXED: Added array-to-string JSON conversion for monthly_diabetic_meds
                            if (isset($record['monthly_diabetic_meds']) && is_array($record['monthly_diabetic_meds'])) {
                                $record['monthly_diabetic_meds'] = json_encode($record['monthly_diabetic_meds']);
                            }
                        }
                        if ($dbTableName === 'schistosomiasis_registry' && isset($record['signs_symptoms']) && is_array($record['signs_symptoms'])) {
                            $record['signs_symptoms'] = json_encode($record['signs_symptoms']);
                        }

                        // Boolean → Integer blocks
                        if ($dbTableName === 'prenatal_8anc_records') {
                            foreach (['completed8Anc', 'highBp', 'dangerSigns', 'highBpReferred'] as $key) {
                                if (isset($record[$key])) $record[$key] = $record[$key] ? 1 : 0;
                            }
                        }
                        if ($dbTableName === 'prenatal_supplementation_records') {
                            foreach (['received_deworming', 'completed_ifa', 'completed_mm', 'completed_cc'] as $key) {
                                if (isset($record[$key])) $record[$key] = $record[$key] ? 1 : 0;
                            }
                        }
                        if ($dbTableName === 'postpartum_records') {
                            foreach (['dsBleeding', 'dsVision', 'dsAbdominal', 'dsFever', 'dsBreathing'] as $field) {
                                if (isset($record[$field])) $record[$field] = $record[$field] ? 1 : 0;
                            }
                        }
                        if ($dbTableName === 'child_immunization_records') {
                            $boolFields = ['td2Mother', 'td3To5Mother', 'ficBcg', 'ficDpt3', 'ficOpv3', 'ficMmr2', 'cicBcg', 'cicDpt3', 'cicOpv3', 'cicMmr2'];
                            foreach ($boolFields as $key) {
                                if (isset($record[$key])) $record[$key] = $record[$key] ? 1 : 0;
                            }
                        }
                        if ($dbTableName === 'child_sick_records') {
                            $boolFields = ['vitaminA100IU', 'vitaminA200IU', 'diagnosisMeasles', 'diagnosisPersistentDiarrhea', 'orsOnly', 'orsAndZinc', 'amoxicillinDrops', 'amoxicillinClavulanate', 'cefuroxime', 'pneumoniaOthers'];
                            foreach ($boolFields as $key) {
                                if (isset($record[$key])) $record[$key] = $record[$key] ? 1 : 0;
                            }
                        }
                        if ($dbTableName === 'oral_health_care') {
                            $boolFields = ['rpoc0_oral_screening', 'rpoc0_risk_assessment', 'rpoc0_oral_hygiene', 'rpoc0_counseling', 'rpoc0_fluoride_varnish'];
                            foreach ($boolFields as $key) {
                                if (isset($record[$key])) $record[$key] = $record[$key] ? 1 : 0;
                            }
                        }
                        if ($dbTableName === 'geriatric_screening_records') {
                            $boolFields = ['care_plan_provided', 'ppv_received_at60'];
                            foreach ($boolFields as $key) {
                                if (isset($record[$key])) $record[$key] = $record[$key] ? 1 : 0;
                            }
                        }
                        if ($dbTableName === 'filariasis_registry_table') {
                            $boolFields = ['nbe_performed', 'rdt_performed', 'has_lymphedema', 'has_elephantiasis', 'has_hydrocele'];
                            foreach ($boolFields as $key) {
                                if (isset($record[$key])) $record[$key] = $record[$key] ? 1 : 0;
                            }
                        }
                        if ($dbTableName === 'rabies_records') {
                            $boolFields = ['injury_scratch', 'injury_abrasion', 'injury_laceration', 'injury_punctured', 'injury_avulsed', 'injury_others', 'condition_epilepsy', 'condition_dm', 'condition_hypertension', 'condition_asthma', 'condition_alcoholic', 'condition_egg_allergy'];
                            foreach ($boolFields as $key) {
                                if (isset($record[$key])) $record[$key] = $record[$key] ? 1 : 0;
                            }
                        }
                        // --- NEW: Mental Health Booleans ---
                        if ($dbTableName === 'mental_health_records') {
                            if (isset($record['screenedMhgap'])) {
                                $record['screenedMhgap'] = $record['screenedMhgap'] ? 1 : 0;
                            }
                        }
                        // --- NEW: Environmental Health Booleans ---
                        if ($dbTableName === 'environmental_health_records') {
                            $envBoolFields = [
                                'waterLevelI', 'waterLevelII', 'waterLevelIII', 
                                'waterLocatedInsideDwelling', 'waterAvailable12Hours', 
                                'disposalInSitu', 'disposalOffSiteDesludged', 'disposalOffSiteSewer'
                            ];
                            foreach ($envBoolFields as $key) {
                                if (isset($record[$key])) $record[$key] = $record[$key] ? 1 : 0;
                            }
                        }

                        // Auto-timestamp fallback for tables that skip classification_metrics block
                        if (!in_array($dbTableName, ['classification_metrics'])) {
                            if (!isset($record['created_at'])) {
                                $record['created_at'] = now();
                            }
                            $record['updated_at'] = now();
                        }

                        // ==========================================
                        // INSERT NEW vs UPDATE EXISTING
                        // ==========================================
                        // Multiple Android devices sync independently, so a given
                        // primary key value arriving here might:
                        //   (a) already exist on the server (this record was synced
                        //       before, by this device or another one) → UPDATE it, or
                        //   (b) be brand new to the server → INSERT it.
                        //
                        // `updateOrInsert()` looks correct for this, but it performs a
                        // SELECT followed by an INSERT/UPDATE as two separate queries.
                        // When two devices push a new record with the same id at
                        // roughly the same time, both requests can see "no existing
                        // row" and both attempt an INSERT — the second one then fails
                        // with a duplicate-key error and the whole sync request blows
                        // up instead of gracefully updating. We handle both cases
                        // explicitly and recover from that race by falling back to an
                        // UPDATE if the INSERT collides.
                        $pkValue = $record[$jsonPkName];

                        $recordExists = DB::table($dbTableName)
                            ->where($pkName, $pkValue)
                            ->exists();

                        if ($recordExists) {
                            // Already on the server — just update it.
                            DB::table($dbTableName)
                                ->where($pkName, $pkValue)
                                ->update($record);
                        } else {
                            // Not on the server yet — insert it as a new row.
                            try {
                                DB::table($dbTableName)->insert($record);
                            } catch (\Illuminate\Database\QueryException $e) {
                                // 23000 = integrity constraint violation (duplicate PK).
                                // This means another device's concurrent push just
                                // inserted a record with the same id — treat this as
                                // an update instead of failing the whole sync.
                                if ($e->getCode() === '23000') {
                                    DB::table($dbTableName)
                                        ->where($pkName, $pkValue)
                                        ->update($record);
                                } else {
                                    throw $e;
                                }
                            }
                        }
                    }
                });
            }

            $response = [
                'status'  => 'success',
                'message' => 'Database synchronized successfully on server backend.',
            ];

            if (!empty($skippedRecords)) {
                $response['warnings'] = 'Some records were skipped; see server logs for details.';
                $response['skipped_count'] = count($skippedRecords);
            }

            return response()->json($response, 200);

        } catch (\Exception $e) {
            Log::error('Sync push fatal error', ['error' => $e->getMessage()]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Sync push error: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  PULL  –  Laravel  →  Android
    // ─────────────────────────────────────────────────────────────
    public function syncToAndroid(Request $request)
    {
        $lastSyncedAt = $request->query('last_synced_at');

        try {
            $payload = [];

            foreach ($this->tablesMapping as $jsonKey => $dbTableName) {
                $query = DB::table($dbTableName);

                if ($lastSyncedAt) {
                    if (Schema::hasColumn($dbTableName, 'updated_at')) {
                        $query->where('updated_at', '>', $lastSyncedAt);
                    } else {
                        $payload[$jsonKey] = [];
                        continue;
                    }
                }

                $records = $query->get()->map(function ($row) use ($dbTableName) {
                    $record = (array) $row;

                    // Parse JSON strings back to Arrays for Android
                    if ($dbTableName === 'philpen_risk_assessments') {
                        if (isset($record['monthly_meds'])) {
                            $record['monthly_meds'] = json_decode($record['monthly_meds'], true);
                        }
                        // FIXED: Added string-to-array JSON conversion back for Android pull compatibility
                        if (isset($record['monthly_diabetic_meds'])) {
                            $record['monthly_diabetic_meds'] = json_decode($record['monthly_diabetic_meds'], true);
                        }
                    }
                    if ($dbTableName === 'schistosomiasis_registry' && isset($record['signs_symptoms'])) {
                        $record['signs_symptoms'] = json_decode($record['signs_symptoms'], true);
                    }

                    // Map Boolean integer flags back to booleans
                    if ($dbTableName === 'household_profiles') {
                        foreach (['hpn', 'dm', 'tb', 'fpMethod'] as $key) {
                            if (array_key_exists($key, $record)) $record[$key] = (bool) $record[$key];
                        }
                    }
                    if ($dbTableName === 'prenatal_8anc_records') {
                        foreach (['completed8Anc', 'highBp', 'dangerSigns', 'highBpReferred'] as $key) {
                            if (array_key_exists($key, $record)) $record[$key] = (bool) $record[$key];
                        }
                    }
                    if ($dbTableName === 'prenatal_supplementation_records') {
                        foreach (['received_deworming', 'completed_ifa', 'completed_mm', 'completed_cc'] as $key) {
                            if (array_key_exists($key, $record)) $record[$key] = (bool) $record[$key];
                        }
                    }
                    if ($dbTableName === 'postpartum_records') {
                        foreach (['dsBleeding', 'dsVision', 'dsAbdominal', 'dsFever', 'dsBreathing'] as $field) {
                            if (array_key_exists($field, $record)) $record[$field] = (bool) $record[$field];
                        }
                    }
                    if ($dbTableName === 'child_immunization_records') {
                        $boolFields = ['td2Mother', 'td3To5Mother', 'ficBcg', 'ficDpt3', 'ficOpv3', 'ficMmr2', 'cicBcg', 'cicDpt3', 'cicOpv3', 'cicMmr2'];
                        foreach ($boolFields as $key) {
                            if (array_key_exists($key, $record)) $record[$key] = (bool) $record[$key];
                        }
                    }
                    if ($dbTableName === 'child_sick_records') {
                        $boolFields = ['vitaminA100IU', 'vitaminA200IU', 'diagnosisMeasles', 'diagnosisPersistentDiarrhea', 'orsOnly', 'orsAndZinc', 'amoxicillinDrops', 'amoxicillinClavulanate', 'cefuroxime', 'pneumoniaOthers'];
                        foreach ($boolFields as $key) {
                            if (array_key_exists($key, $record)) $record[$key] = (bool) $record[$key];
                        }
                    }
                    if ($dbTableName === 'oral_health_care') {
                        $boolFields = ['rpoc0_oral_screening', 'rpoc0_risk_assessment', 'rpoc0_oral_hygiene', 'rpoc0_counseling', 'rpoc0_fluoride_varnish'];
                        foreach ($boolFields as $key) {
                            if (array_key_exists($key, $record)) $record[$key] = (bool) $record[$key];
                        }
                    }
                    if ($dbTableName === 'geriatric_screening_records') {
                        $boolFields = ['care_plan_provided', 'ppv_received_at60'];
                        foreach ($boolFields as $key) {
                            if (array_key_exists($key, $record)) $record[$key] = (bool) $record[$key];
                        }
                    }
                    if ($dbTableName === 'filariasis_registry_table') {
                        $boolFields = ['nbe_performed', 'rdt_performed', 'has_lymphedema', 'has_elephantiasis', 'has_hydrocele'];
                        foreach ($boolFields as $key) {
                            if (array_key_exists($key, $record)) $record[$key] = (bool) $record[$key];
                        }
                    }
                    if ($dbTableName === 'rabies_records') {
                        $boolFields = ['injury_scratch', 'injury_abrasion', 'injury_laceration', 'injury_punctured', 'injury_avulsed', 'injury_others', 'condition_epilepsy', 'condition_dm', 'condition_hypertension', 'condition_asthma', 'condition_alcoholic', 'condition_egg_allergy'];
                        foreach ($boolFields as $key) {
                            if (array_key_exists($key, $record)) $record[$key] = (bool) $record[$key];
                        }
                    }
                    
                    // --- NEW: Mental Health Booleans ---
                    if ($dbTableName === 'mental_health_records') {
                        if (array_key_exists('screenedMhgap', $record)) {
                            $record['screenedMhgap'] = (bool) $record['screenedMhgap'];
                        }
                    }
                    
                    // --- NEW: Environmental Health Booleans ---
                    if ($dbTableName === 'environmental_health_records') {
                        $envBoolFields = [
                            'waterLevelI', 'waterLevelII', 'waterLevelIII', 
                            'waterLocatedInsideDwelling', 'waterAvailable12Hours', 
                            'disposalInSitu', 'disposalOffSiteDesludged', 'disposalOffSiteSewer'
                        ];
                        foreach ($envBoolFields as $key) {
                            if (array_key_exists($key, $record)) $record[$key] = (bool) $record[$key];
                        }
                    }

                    // Timestamp handling
                    if ($dbTableName === 'classification_metrics' && isset($record['created_at'])) {
                        $record['created_at_timestamp'] = strtotime($record['created_at']) * 1000;
                    }

                    return $record;
                })->toArray();

                $payload[$jsonKey] = $records;
            }

            return response()->json([
                'status'    => 'success',
                'synced_at' => now()->toISOString(),
                'data'      => $payload,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Sync pull error: ' . $e->getMessage(),
            ], 500);
        }
    }
}