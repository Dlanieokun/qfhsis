<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema; // Added for safe table column checking
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        // --- NEW: Vital Statistics & Morbidity ---
        'infantDeathRecords'              => 'infant_deaths',
        'maternalDeathRecords'            => 'maternal_deaths',
        'morbidityRecords'                => 'morbidity_records',
    ];

    // ─────────────────────────────────────────────────────────────
    //  Location-scoped tables (PULL filtering)
    // ─────────────────────────────────────────────────────────────
    //  Any DB table listed here is restricted, on pull, to rows that
    //  belong to the logged-in user's assigned Geographic Location
    //  Descriptions — users.region / province / municipality / barangay
    //  (the plain place-name columns). household_profiles only stores
    //  those same descriptive names (see its migration — it has no
    //  *_code columns at all), so matching happens on names, not codes.
    //
    //  'relation' explains how a table gets back to household_profiles:
    //    - 'direct'   the table IS household_profiles.
    //    - 'profile'  the table has a column pointing straight at
    //                 household_profiles.id (e.g. profileId).
    //    - 'maternal' the table has a column pointing at
    //                 maternal_care_records.id, which itself points at
    //                 household_profiles via its own profileId.
    //
    //  Tables NOT listed here — child_sick_records, geriatric_screening_records,
    //  filariasis_registry_table, leprosy_registry, rabies_records,
    //  schistosomiasis_registry, sth_registry_records, mental_health_records,
    //  morbidity_records — have no household_profiles linkage column in their
    //  current schema, so there's nothing to scope them by; they're still pulled
    //  in full until a link column is added. (morbidity_records carries a
    //  householdId string, but it isn't a foreign key into household_profiles.id,
    //  so it can't be scoped the same way infant_deaths/maternal_deaths are below.
    //  environmental_health_records now has profileId and IS location-scoped below.)
    private array $locationScopedTables = [
        'household_profiles' => ['relation' => 'direct'],

        'family_planning_records'           => ['relation' => 'profile', 'column' => 'profileId'],
        'classification_metrics'            => ['relation' => 'profile', 'column' => 'profile_id'],
        'family_planning_follow_ups'        => ['relation' => 'profile', 'column' => 'profileId'],
        'family_planning_drop_outs'         => ['relation' => 'profile', 'column' => 'profileId'],
        'maternal_care_records'              => ['relation' => 'profile', 'column' => 'profileId'],
        'child_immunization_records'        => ['relation' => 'profile', 'column' => 'profileId'],
        'child_immunization_school_records' => ['relation' => 'profile', 'column' => 'profileId'],
        'child_nutrition_records'           => ['relation' => 'profile', 'column' => 'profileId'],
        'oral_health_care'                  => ['relation' => 'profile', 'column' => 'profile_id'],
        'philpen_risk_assessments'          => ['relation' => 'profile', 'column' => 'profile_id'],
        'eyes_screenings'                    => ['relation' => 'profile', 'column' => 'profile_id'],
        'cervical_cancer_screenings'        => ['relation' => 'profile', 'column' => 'profile_id'],

        'prenatal_immunization_records'     => ['relation' => 'maternal', 'column' => 'maternalRecordId'],
        'prenatal_8anc_records'              => ['relation' => 'maternal', 'column' => 'maternalRecordId'],
        'postpartum_records'                 => ['relation' => 'maternal', 'column' => 'maternalRecordId'],
        'intrapartum_records'                => ['relation' => 'maternal', 'column' => 'maternalRecordId'],
        'prenatal_lab_screening_records'     => ['relation' => 'maternal', 'column' => 'maternalRecordId'],
        'prenatal_supplementation_records'  => ['relation' => 'maternal', 'column' => 'maternal_record_id'],

        'infant_deaths'                      => ['relation' => 'profile', 'column' => 'profile_id'],
        'maternal_deaths'                    => ['relation' => 'profile', 'column' => 'profile_id'],

        'environmental_health_records'       => ['relation' => 'profile', 'column' => 'profileId'],
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

                DB::transaction(function () use ($dbTableName, $records, &$skippedRecords, &$request) {
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
                        // FOREIGN KEY PRE-VALIDATION
                        // ==========================================
                        // Tables that carry a profile_id FK into household_profiles
                        // must have a valid server-side profile_id before we attempt
                        // any insert or update. Two failure modes arrive here:
                        //
                        //   a) profile_id = 0 — Room's default int value. The Android
                        //      record was saved locally before a profileId was ever
                        //      assigned (e.g. the user saved the form before selecting
                        //      a household, or the field was simply never set in the
                        //      fragment). There is no way to resolve 0 to a real server
                        //      row, so we skip and warn.
                        //
                        //   b) profile_id = <Android-local auto-increment> — the profile
                        //      was pushed in the same batch and the array_walk_recursive
                        //      swap should have rewritten this value to the real server ID.
                        //      If it still doesn't match (e.g. the profile push failed, or
                        //      this record was left over from a prior sync session where the
                        //      profile landed under a different server ID), we skip and warn
                        //      rather than letting MySQL throw a cryptic FK error.
                        //
                        // The $locationScopedTables map already catalogues which tables
                        // have a profile_id relation and what the column is called, so we
                        // reuse it here instead of duplicating the list.
                        $profileFkColumn = null;
                        if (
                            array_key_exists($dbTableName, $this->locationScopedTables)
                            && isset($this->locationScopedTables[$dbTableName]['relation'])
                            && $this->locationScopedTables[$dbTableName]['relation'] === 'profile'
                        ) {
                            $profileFkColumn = $this->locationScopedTables[$dbTableName]['column'];
                        }

                        if ($profileFkColumn !== null && array_key_exists($profileFkColumn, $record)) {
                            $fkValue = $record[$profileFkColumn];
                            if (
                                $fkValue === null
                                || $fkValue === ''
                                || (int) $fkValue <= 0
                                || !DB::table('household_profiles')->where('id', $fkValue)->exists()
                            ) {
                                Log::warning("Sync push: skipped record — profile_id {$fkValue} not found in household_profiles", [
                                    'table'          => $dbTableName,
                                    'profile_id'     => $fkValue,
                                    'fk_column'      => $profileFkColumn,
                                    'hint'           => $fkValue == 0
                                        ? 'profile_id is 0 (Room default) — the Android record was created without a valid household profile reference'
                                        : 'profile_id does not match any server household_profiles row — the household may not have been uploaded yet',
                                ]);
                                $skippedRecords[] = [
                                    'table'      => $dbTableName,
                                    'reason'     => 'invalid_profile_fk',
                                    'profile_id' => $fkValue,
                                ];
                                continue;
                            }
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

                        // ── cervical_cancer_screenings ──────────────────────────────────
                        // Three fixes needed for this table:
                        //
                        // 1. isSynced: strip it — the server owns this column's value
                        //    (set to false on insert; only updated by the pull path).
                        //    Leaving it in would either overwrite the server's value or
                        //    cause an "Unknown column" error on tables that don't have it.
                        //
                        // 2. updated_at → updatedAt: Android's @SerializedName was "updated_at"
                        //    but the migration created the column as `updatedAt` (camelCase).
                        //    After fixing the entity's @SerializedName to "updatedAt", the key
                        //    arriving here is "updatedAt". We must NOT let the generic
                        //    auto-timestamp block below rename/overwrite it with a datetime
                        //    string — the column stores epoch milliseconds, not a datetime.
                        //    We handle it explicitly here and exclude it from the generic block.
                        //
                        // 3. The generic "auto-timestamp fallback" below would also try to
                        //    write to "updated_at" (Laravel's managed timestamp), which does
                        //    exist in this table via $table->timestamps() — so we still let
                        //    that run for created_at / updated_at (the Laravel timestamps),
                        //    but we guard the custom updatedAt column separately.
                        if ($dbTableName === 'cervical_cancer_screenings') {
                            // Strip isSynced — server owns it
                            unset($record['isSynced']);

                            // The Android entity now sends "updatedAt" (epoch ms).
                            // Cast to int to be safe and leave it in the record so it
                            // lands in the `updatedAt` DB column.
                            if (isset($record['updatedAt'])) {
                                $record['updatedAt'] = (int) $record['updatedAt'];
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
                        // Android entities carry a `newInsert` boolean flag that
                        // distinguishes records that have never been sent to the
                        // server (newInsert = true, created locally and not yet
                        // uploaded) from records that already exist on the server
                        // and were subsequently edited (newInsert = false / absent).
                        //
                        // When newInsert is TRUE  → always INSERT.  The record is
                        //   brand-new; it must not silently become an UPDATE that
                        //   overwrites a different row sharing the same id on the
                        //   server.  If a concurrent push from another device beats
                        //   us to the INSERT (duplicate-key collision, SQLSTATE
                        //   23000), we fall back to UPDATE so the sync doesn't blow
                        //   up — the collision means the record already landed.
                        //
                        // When newInsert is FALSE / absent → check server existence
                        //   and UPDATE if found, INSERT if not (the classic upsert
                        //   path used by the full Sync push).
                        //
                        // Strip `newInsert` from the payload before any DB write —
                        // it's an Android-only housekeeping field and has no column
                        // in any server table.
                        $isNewInsert = !empty($record['newInsert']);
                        unset($record['newInsert']);

                        $pkValue = $record[$jsonPkName];

                        // --- NEW: Vital Statistics & Morbidity column translation ---
                        // InfantDeathRecord / MaternalDeathRecord / MorbidityRecord send
                        // camelCase JSON keys (profileId, dateOfRegistration, ageGroup,
                        // householdId, isSynced, ...), matching the Java field names —
                        // same as every other table here. But infant_deaths,
                        // maternal_deaths and morbidity_records were migrated using
                        // Laravel's default snake_case column convention instead of
                        // mirroring those field names, so the raw JSON keys don't match
                        // any real column. Translate before insert/update.
                        if (in_array($dbTableName, ['infant_deaths', 'maternal_deaths', 'morbidity_records'])) {
                            $record = $this->translateVitalStatsColumns($dbTableName, $record, true);
                        }

                        if ($isNewInsert) {
                            if ($dbTableName === 'household_profiles') {
                                // The Android app assigns its own local auto-increment ID.
                                // We must NOT forward that ID to the server — instead we
                                // strip it and let MySQL generate an authoritative one.
                                // We then rewrite every occurrence of the old Android-local
                                // ID in $request so that all child tables processed later
                                // in the same push (familyPlanningRecords, maternalCareRecords,
                                // etc.) automatically carry the correct server profile ID.
                                $syncId = $pkValue;                 // Android-local ID
                                unset($record[$jsonPkName]);        // Drop it; server will assign

                                $newId = DB::table($dbTableName)->insertGetId($record);

                                // Walk every scalar value in the full request payload and
                                // swap the Android-local profile ID for the server-assigned one.
                                // Both int and string representations are matched to handle
                                // JSON decoding inconsistencies across Android API versions.
                                $allData = $request->all();
                                array_walk_recursive($allData, function (&$value) use ($syncId, $newId) {
                                    if ($value === $syncId || $value === (string) $syncId) {
                                        $value = $newId;
                                    }
                                });
                                $request->replace($allData);

                            } else {
                                // For every other table, the Android-generated PK is also
                                // local-only and must not dictate the server's row ID.
                                // Dropping both the JSON key and the DB column name covers
                                // tables whose key name differs between Android JSON and the
                                // DB schema (e.g. geriatric_screening_records: recordNo / record_no).
                                unset($record[$jsonPkName], $record[$pkName]);

                                try {
                                    DB::table($dbTableName)->insert($record);
                                } catch (\Illuminate\Database\QueryException $e) {
                                    // SQLSTATE 23000 covers two distinct violation types:
                                    //   • Foreign key constraint failure  (ER_NO_REFERENCED_ROW_2, errno 1452)
                                    //   • Duplicate / unique key violation (ER_DUP_ENTRY,          errno 1062)
                                    //
                                    // The original comment said "without a specified PK a 23000 can
                                    // only be a unique constraint" — that was wrong. A bad profile_id
                                    // or any other FK field produces 23000 too. Log the actual sub-type
                                    // so the warning in storage/logs/laravel.log is actionable.
                                    if ($e->getCode() === '23000') {
                                        $msg     = $e->getMessage();
                                        $isFk    = stripos($msg, 'foreign key') !== false
                                               || stripos($msg, '1452')         !== false;
                                        $logType = $isFk ? 'foreign_key_violation' : 'unique_constraint_violation';

                                        Log::warning("Sync push: {$logType} on {$dbTableName}", [
                                            'error' => $msg,
                                        ]);
                                    } else {
                                        throw $e;
                                    }
                                }
                            }
                        } else {
                            // Edited record — upsert: update if the row already
                            // exists on the server, insert if it somehow doesn't.
                            $recordExists = DB::table($dbTableName)
                                ->where($pkName, $pkValue)
                                ->exists();

                            if ($recordExists) {
                                DB::table($dbTableName)
                                    ->where($pkName, $pkValue)
                                    ->update($record);
                            } else {
                                try {
                                    DB::table($dbTableName)->insert($record);
                                } catch (\Illuminate\Database\QueryException $e) {
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
    //  UPLOAD  –  Android → Laravel  (delta: new + updated rows only)
    // ─────────────────────────────────────────────────────────────
    //  The Android "Upload" button sends only the locally unsynced
    //  (new or edited) rows for each table, rather than a full table
    //  dump like the "Sync" button's push does. The per-record logic
    //  in syncFromAndroid() already does an upsert (insert if the id
    //  doesn't exist yet on the server, update if it does) rather than
    //  a full replace, so it's already correct for a partial payload —
    //  reuse it instead of duplicating all the per-table type-casting
    //  above. Kept as its own method/route so upload vs. sync pushes
    //  stay distinguishable in logs and can diverge later if needed.
    public function uploadFromAndroid(Request $request)
    {
        Log::info('Sync upload received (delta push of unsynced records).');
        return $this->syncFromAndroid($request);
    }

    /**
     * Restrict a pull query so it only returns rows belonging to the
     * logged-in user's assigned Geographic Location Descriptions —
     * barangay / municipality / province / region place-names on the
     * users table (NOT the *_code columns). For tables that don't carry
     * those columns themselves, the scope is propagated in via a
     * household_profiles (or maternal_care_records → household_profiles)
     * subquery instead. Tables not listed in $locationScopedTables, or a
     * user with no location assigned at all (e.g. a national/admin
     * account), are left unfiltered.
     */
    private function applyLocationScope($query, string $dbTableName, $user): void
    {
        if (!$user || !array_key_exists($dbTableName, $this->locationScopedTables)) {
            return;
        }

        // Nothing to narrow by — leave unfiltered rather than issuing a
        // whereIn() against an unrelated set, which would wipe out rows
        // that simply have no profile link (e.g. profileId = -1) instead
        // of correctly leaving them alone for an unscoped account.
        if (!$this->userHasAssignedLocation($user)) {
            return;
        }

        $relation = $this->locationScopedTables[$dbTableName];

        switch ($relation['relation']) {
            case 'direct':
                // The table IS household_profiles — filter it in place.
                $this->constrainToUserLocation($query, $user);
                break;

            case 'profile':
                // Table has a column pointing straight at household_profiles.id.
                $column = $relation['column'];
                if (Schema::hasColumn($dbTableName, $column)) {
                    $query->whereIn($column, function ($sub) use ($user) {
                        $sub->select('id')->from('household_profiles');
                        $this->constrainToUserLocation($sub, $user);
                    });
                }
                break;

            case 'maternal':
                // Table has a column pointing at maternal_care_records.id,
                // which itself points at household_profiles via profileId.
                $column = $relation['column'];
                if (Schema::hasColumn($dbTableName, $column)) {
                    $query->whereIn($column, function ($sub) use ($user) {
                        $sub->select('id')->from('maternal_care_records')
                            ->whereIn('profileId', function ($sub2) use ($user) {
                                $sub2->select('id')->from('household_profiles');
                                $this->constrainToUserLocation($sub2, $user);
                            });
                    });
                }
                break;
        }
    }

    /**
     * Add the actual barangay/municipality/province/region where-clause to
     * a household_profiles query (or subquery), using the narrowest level
     * the user actually has assigned (barangay > municipality > province
     * > region).
     */
    private function constrainToUserLocation($query, $user): void
    {
        // A user can be assigned to more than one barangay (e.g. a midwife
        // covering several barangays), so it's stored as JSON on the users
        // table while the other levels are single values.
        $userBarangays = $this->decodeJsonList($user->barangay ?? null);

        if (!empty($userBarangays) && Schema::hasColumn('household_profiles', 'barangay')) {
            $query->whereIn('barangay', $userBarangays);
        } elseif (!empty($user->municipality) && Schema::hasColumn('household_profiles', 'municipality')) {
            $query->where('municipality', $user->municipality);
        } elseif (!empty($user->province) && Schema::hasColumn('household_profiles', 'province')) {
            $query->where('province', $user->province);
        } elseif (!empty($user->region) && Schema::hasColumn('household_profiles', 'region')) {
            $query->where('region', $user->region);
        }
    }

    /**
     * Whether this user has any Geographic Location Description assigned
     * at all. Accounts with none (e.g. national/admin users) get an
     * unfiltered pull rather than one that matches nothing.
     */
    private function userHasAssignedLocation($user): bool
    {
        return !empty($this->decodeJsonList($user->barangay ?? null))
            || !empty($user->municipality)
            || !empty($user->province)
            || !empty($user->region);
    }

    /**
     * users.barangay is stored as JSON (a user can be assigned more than
     * one barangay) and may come back from Eloquent already decoded into
     * an array (if the model casts it) or as a raw JSON string — handle
     * either case.
     */
    private function decodeJsonList($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /**
     * Apply the per-table JSON-decoding / boolean-casting / timestamp
     * conversions needed to turn a raw DB row back into the shape Android
     * expects. Pulled out of the pull loop so it can run per-row against a
     * cursor() stream instead of a fully-materialized collection.
     */
    private function transformPulledRow(string $dbTableName, array $record): array
    {
        // ──────────────────────────────────────────────────────────
        // FIX: isSynced is common to every single table/entity and is
        // declared as a Java primitive `boolean` on the Android side
        // (e.g. HouseholdProfile.isSynced, FamilyPlanningRecord.isSynced,
        // etc.). syncToAndroid() reads rows via DB::table() — the plain
        // query builder, not an Eloquent model with a boolean cast — so
        // MySQL's TINYINT(1) column comes back here as a raw PHP int
        // and would otherwise be json_encode()'d as 0/1. Gson's default
        // boolean deserializer only accepts true/false and throws:
        //   JsonSyntaxException: IllegalStateException: Expected a
        //   boolean but was NUMBER
        // the moment it hits that field. Cast it once, generically, for
        // every table instead of needing a per-table entry like the
        // other boolean columns below.
        // --- NEW: Vital Statistics & Morbidity column translation (reverse) ---
        // Undo the push-side translation so Android sees the camelCase keys
        // its InfantDeathRecord / MaternalDeathRecord / MorbidityRecord
        // entities actually declare, instead of this table's raw snake_case
        // column names. Must run before the generic boolean casts below.
        if (in_array($dbTableName, ['infant_deaths', 'maternal_deaths', 'morbidity_records'])) {
            $record = $this->translateVitalStatsColumns($dbTableName, $record, false);
        }

        if (array_key_exists('isSynced', $record)) {
            $record['isSynced'] = (bool) $record['isSynced'];
        }

        // FIX: InfantDeathRecord.synced / MaternalDeathRecord.synced are also
        // Java `boolean` primitives (named `synced`, not `isSynced`), so they
        // hit the exact same Gson "Expected a boolean but was NUMBER" issue
        // described above and need the same TINYINT(1) → bool cast.
        if (array_key_exists('synced', $record)) {
            $record['synced'] = (bool) $record['synced'];
        }

        // ──────────────────────────────────────────────────────────
        // FIX: newInsert has the exact same problem as isSynced above.
        // Every Android entity declares `newInsert` as a Java primitive
        // `boolean` (e.g. HouseholdProfile.newInsert). MySQL stores it
        // as TINYINT(1); DB::table() returns it as a PHP int (0 or 1);
        // json_encode() serialises that as a JSON number; Gson then
        // throws:
        //   JsonSyntaxException: IllegalStateException: Expected a
        //   boolean but was NUMBER at path $.data.householdProfiles[0].newInsert
        // Cast it here, generically for every table, so the pull
        // response always sends true/false rather than 0/1.
        if (array_key_exists('newInsert', $record)) {
            $record['newInsert'] = (bool) $record['newInsert'];
        }

        // ──────────────────────────────────────────────────────────
        // FIX: Normalize timestamps to epoch milliseconds.
        //
        // MySQL/Eloquent stores created_at/updated_at as datetime
        // strings, e.g. "2026-07-12 19:12:19". Every Room entity on the
        // Android side declares its `updated_at` (and, for
        // classification_metrics, `created_at_timestamp`) field as a
        // Java `long`. Sending the raw datetime string caused Gson to
        // throw:
        //   JsonSyntaxException: NumberFormatException: For input
        //   string: "2026-07-12 19:12:19"
        // Converting here — once, generically, for every table — fixes
        // that at the source instead of needing special-casing per
        // entity.
        foreach (['created_at', 'updated_at'] as $tsField) {
            if (!empty($record[$tsField]) && !is_numeric($record[$tsField])) {
                $record[$tsField] = strtotime($record[$tsField]) * 1000;
            }
        }

        // classification_metrics: Android expects this value under the
        // JSON key "created_at_timestamp" (see ClassificationEntity's
        // @SerializedName("created_at_timestamp")), not "created_at".
        if ($dbTableName === 'classification_metrics' && isset($record['created_at'])) {
            $record['created_at_timestamp'] = $record['created_at'];
        }

        // Parse JSON strings back to Arrays for Android
        if ($dbTableName === 'philpen_risk_assessments') {
            if (isset($record['monthly_meds'])) {
                $record['monthly_meds'] = json_decode($record['monthly_meds'], true);
            }
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

        // ── cervical_cancer_screenings (pull) ───────────────────────────────
        // 1. isSynced / newInsert — already cast generically above via the
        //    array_key_exists('isSynced') / array_key_exists('newInsert') blocks,
        //    so no repeat needed here.
        //
        // 2. updatedAt — this column stores epoch milliseconds (unsignedBigInteger
        //    in the migration), NOT a datetime string, so the generic datetime→ms
        //    cast in the foreach(['created_at','updated_at']) loop above does NOT
        //    apply to it. DB::table() will return it as a plain integer already;
        //    just ensure it's cast to int in case the driver returns a string.
        //
        // 3. The standard Laravel `created_at` / `updated_at` datetime columns
        //    (from $table->timestamps()) are handled correctly by the generic loop.
        if ($dbTableName === 'cervical_cancer_screenings') {
            if (array_key_exists('updatedAt', $record)) {
                $record['updatedAt'] = (int) $record['updatedAt'];
            }
        }

        return $record;
    }

    /**
     * infant_deaths, maternal_deaths and morbidity_records were migrated
     * using Laravel's default snake_case column convention, but their
     * Android entities (InfantDeathRecord, MaternalDeathRecord,
     * MorbidityRecord) use camelCase field names — unlike most other tables
     * in this controller, where the DB columns were written to mirror the
     * Android field names directly instead. Translate keys between the two
     * shapes here rather than relying on either side matching by accident.
     *
     * $toDb = true  : camelCase Android field → snake_case DB column (push)
     * $toDb = false : snake_case DB column → camelCase Android field (pull)
     *
     * NOTE: morbidity_records' migration mirrors the Android MorbidityRecord
     * field names (camelCase) for everything except user_id, so only userId is
     * translated. Android-only fields with no server column (isSynced,
     * householdId, createdAt, updatedAt) are dropped on push by the
     * known-column filter below.
     */
    private function translateVitalStatsColumns(string $dbTableName, array $record, bool $toDb): array
    {
        $maps = [
            'infant_deaths' => [
                'profileId'          => 'profile_id',
                'dateOfRegistration' => 'date_of_registration',
                'fullName'           => 'full_name',
                'completeAddress'    => 'complete_address',
                'syncTimestamp'      => 'sync_timestamp',
            ],
            'maternal_deaths' => [
                'profileId'          => 'profile_id',
                'dateOfRegistration' => 'date_of_registration',
                'fullName'           => 'full_name',
                'completeAddress'    => 'complete_address',
                'ageGroup'           => 'age_group',
                'placeOfOccurrence'  => 'place_of_occurrence',
                'causeOfDeath'       => 'cause_of_death',
                'syncTimestamp'      => 'sync_timestamp',
            ],
            'morbidity_records' => [
                // Only user_id is snake_case in the current migration (it's a
                // foreignId). diseaseName, reportYear, reportMonth, icdCode,
                // region/province/municipality/barangay and all the age-group
                // columns are camelCase and match the Android field names
                // exactly, so they pass straight through with no mapping.
                // Mapping them to snake_case here made them "unknown columns"
                // that the filter below silently dropped.
                'userId'       => 'user_id',
                // isSynced is device-local and has no server column (it's
                // dropped by the filter below). createdAt/updatedAt are also
                // not mapped — the auto-timestamp fallback sets created_at /
                // updated_at server-side.
            ],
        ];

        $map = $toDb ? $maps[$dbTableName] : array_flip($maps[$dbTableName]);

        $translated = [];
        foreach ($record as $key => $value) {
            $translated[$map[$key] ?? $key] = $value;
        }

        // Drop anything that isn't a real column on morbidity_records
        // (isSynced, householdId, createdAt, updatedAt, ...) so the
        // insert/update doesn't fail with an "Unknown column" error.
        if ($toDb && $dbTableName === 'morbidity_records') {
            static $knownColumns = null;
            if ($knownColumns === null) {
                $knownColumns = Schema::getColumnListing('morbidity_records');
            }
            $translated = array_intersect_key($translated, array_flip($knownColumns));
        }

        return $translated;
    }

    // ─────────────────────────────────────────────────────────────
    //  PULL  –  Laravel  →  Android
    // ─────────────────────────────────────────────────────────────
    //
    //  Streams the response instead of building one giant
    //  ['householdProfiles' => [...], 'familyPlanningRecords' => [...], ...]
    //  array and json_encode()-ing it in one shot. The old approach held
    //  every row of every table in PHP memory twice over (once as the
    //  fetched Collection, again as the mapped array) before encoding —
    //  which is what was blowing past memory_limit on larger/full pulls.
    //  cursor() fetches one row at a time, so peak memory now stays
    //  roughly constant regardless of how much data is being pulled.
    public function syncToAndroid(Request $request)
    {
        $lastSyncedAt = $request->query('last_synced_at');

        // Requires the '/sync/pull' route to sit behind the 'auth:sanctum'
        // middleware (see routes/api.php) so we know who's pulling.
        $user = $request->user();

        $tablesMapping = $this->tablesMapping;
        $controller = $this;

        $response = new StreamedResponse(
            function () use ($lastSyncedAt, $user, $tablesMapping, $controller) {
                echo '{"status":"success","synced_at":' . json_encode(now()->toISOString()) . ',"data":{';

                $firstTable = true;
                foreach ($tablesMapping as $jsonKey => $dbTableName) {
                    echo ($firstTable ? '' : ',') . json_encode($jsonKey) . ':[';
                    $firstTable = false;

                    try {
                        $query = DB::table($dbTableName);

                        $skipTable = false;
                        if ($lastSyncedAt) {
                            if (Schema::hasColumn($dbTableName, 'updated_at')) {
                                $query->where('updated_at', '>', $lastSyncedAt);
                            } else {
                                $skipTable = true;
                            }
                        }

                        if (!$skipTable) {
                            $controller->applyLocationScope($query, $dbTableName, $user);

                            $firstRow = true;
                            foreach ($query->cursor() as $row) {
                                $record = $controller->transformPulledRow($dbTableName, (array) $row);
                                echo ($firstRow ? '' : ',') . json_encode($record);
                                $firstRow = false;
                            }
                        }
                    } catch (\Exception $e) {
                        // Log and move on rather than aborting the whole
                        // stream — a partial/valid-JSON response for the
                        // remaining tables is more useful to the Android
                        // client than a hard failure mid-stream.
                        Log::error("Sync pull error on table {$dbTableName}", ['error' => $e->getMessage()]);
                    }

                    echo ']';

                    if (ob_get_level() > 0) {
                        @ob_flush();
                    }
                    @flush();
                }

                echo '}}';
            },
            200,
            ['Content-Type' => 'application/json']
        );

        return $response;
    }
}