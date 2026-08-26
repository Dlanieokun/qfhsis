<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeprosyRegistry extends Model
{
    use HasFactory;

    protected $table = 'leprosy_registry';

    protected $fillable = [
        'userId',
        'profile_id',
        'date_of_registration',
        'name',
        'address',
        'date_of_birth',
        'age',
        'age_group',
        'sex',
        'confirmed_case',
        'date_of_diagnosis',
        'case_history',
        'previous_facility',
        'clinical_classification',
        'treatment_start_date',
        'months_treated_prior',
        'reclassified',
        'date_of_reclassification',
        'updated_classification',
        'treatment_outcome',
        'completed_fixed_mdt',
        'fixed_mdt_completed_date',
        'beyond_fixed_mdt',
        'beyond_fixed_mdt_completed_date',
        'grade2_disability',
        'remarks',
        'isSynced',
        'newInsert',
        'updatedAt',
    ];

    protected $casts = [
        'isSynced' => 'boolean',
        'newInsert' => 'boolean',
    ];

    public function householdProfile(): BelongsTo
    {
        return $this->belongsTo(HouseholdProfile::class, 'profile_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}