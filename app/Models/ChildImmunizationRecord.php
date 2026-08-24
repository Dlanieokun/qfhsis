<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildImmunizationRecord extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'child_immunization_records';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'household_profile_id',
        'child_name',
        'date_of_birth',
        'age_in_months',
        'cpab_date',
        'bcg_date',
        'dpt_hib_hepb_dose_1_date',
        'dpt_hib_hepb_dose_2_date',
        'dpt_hib_hepb_dose_3_date',
        'opv_dose_1_date',
        'opv_dose_2_date',
        'opv_dose_3_date',
        'ipv_date',
        'pcv_dose_1_date',
        'pcv_dose_2_date',
        'pcv_dose_3_date',
        'mmr_dose_1_date',
        'mmr_dose_2_date',
        'hpv_dose_1_date',
        'hpv_dose_2_date',
        'hpv_dose_3_date',
        'fic_date',
        'cic_date',
        'status',
        'remarks',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'cpab_date' => 'date',
        'bcg_date' => 'date',
        'dpt_hib_hepb_dose_1_date' => 'date',
        'dpt_hib_hepb_dose_2_date' => 'date',
        'dpt_hib_hepb_dose_3_date' => 'date',
        'opv_dose_1_date' => 'date',
        'opv_dose_2_date' => 'date',
        'opv_dose_3_date' => 'date',
        'ipv_date' => 'date',
        'pcv_dose_1_date' => 'date',
        'pcv_dose_2_date' => 'date',
        'pcv_dose_3_date' => 'date',
        'mmr_dose_1_date' => 'date',
        'mmr_dose_2_date' => 'date',
        'hpv_dose_1_date' => 'date',
        'hpv_dose_2_date' => 'date',
        'hpv_dose_3_date' => 'date',
        'fic_date' => 'date',
        'cic_date' => 'date',
    ];

    /**
     * Get the household profile that owns this child immunization record.
     */
    public function householdProfile(): BelongsTo
    {
        return $this->belongsTo(HouseholdProfile::class, 'household_profile_id');
    }
}