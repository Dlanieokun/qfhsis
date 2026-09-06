<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildImmunizationRecord extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'child_immunization_records';

    /**
     * The attributes that are mass assignable.
     *
     * Matches database/migrations/2026_06_21_235555_create_child_immunization_records_table.php
     * exactly (camelCase throughout, profileId as the household_profiles FK) —
     * the previous version of this model listed a completely different,
     * unrelated snake_case schema (household_profile_id, child_name, etc.)
     * that doesn't exist on this table.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'profileId', 'userId',

        // Demographics
        'registrationDate', 'familySerialNumber', 'childName', 'dateOfBirth',
        'ageMonths', 'sex', 'motherName', 'address',

        // CPAB
        'td2Mother', 'td3To5Mother',

        // BCG
        'bcgWithin24hAge', 'bcgWithin24hDate', 'bcgLateAge', 'bcgLateDate',

        // Hepatitis B
        'hepaBWithin24hAge', 'hepaBWithin24hDate', 'hepaBLateAge', 'hepaBLateDate',

        // DPT-HiB-HepB
        'dpt1Age', 'dpt1Date', 'dpt2Age', 'dpt2Date', 'dpt3Age', 'dpt3Date',

        // OPV
        'opv1Age', 'opv1Date', 'opv2Age', 'opv2Date', 'opv3Age', 'opv3Date',

        // IPV
        'ipv1Age', 'ipv1Date', 'ipv2Age', 'ipv2Date',

        // PCV
        'pcv1Age', 'pcv1Date', 'pcv2Age', 'pcv2Date', 'pcv3Age', 'pcv3Date',

        // MMR
        'mmr1Age', 'mmr1Date', 'mmr2Age', 'mmr2Date',

        // FIC
        'ficBcg', 'ficDpt3', 'ficOpv3', 'ficMmr2', 'ficDate',

        // CIC
        'cicBcg', 'cicDpt3', 'cicOpv3', 'cicMmr2', 'cicDate',

        'remarks', 'isSynced', 'newInsert', 'updatedAt',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'td2Mother' => 'boolean',
        'td3To5Mother' => 'boolean',
        'ficBcg' => 'boolean',
        'ficDpt3' => 'boolean',
        'ficOpv3' => 'boolean',
        'ficMmr2' => 'boolean',
        'cicBcg' => 'boolean',
        'cicDpt3' => 'boolean',
        'cicOpv3' => 'boolean',
        'cicMmr2' => 'boolean',
        'isSynced' => 'boolean',
        'newInsert' => 'boolean',
    ];

    /**
     * The household profile (child) this immunization record belongs to.
     */
    public function householdProfile(): BelongsTo
    {
        return $this->belongsTo(HouseholdProfile::class, 'profileId');
    }

    /**
     * The health worker who recorded this immunization.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}