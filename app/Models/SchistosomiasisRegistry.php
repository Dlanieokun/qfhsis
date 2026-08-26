<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchistosomiasisRegistry extends Model
{
    use HasFactory;

    protected $table = 'schistosomiasis_registry';

    protected $fillable = [
        'userId',
        'profileId',
        'date_of_registration',
        'family_serial_number',
        'name',
        'address',
        'residency',
        'date_of_birth',
        'age',
        'age_group',
        'sex',
        'history_of_exposure',
        'screened',
        'date_screened',
        'with_signs_symptoms',
        'signs_symptoms',
        'signs_symptoms_other_specify',
        'clinical_first_treatment_given',
        'clinical_first_treatment_date',
        'clinical_retreatment',
        'clinical_retreatment_date',
        'clinical_cured',
        'clinical_cured_date',
        'diagnostic_test',
        'date_of_diagnosis',
        'diagnostic_result',
        'date_confirmed',
        'complicated',
        'confirmed_first_treatment_given',
        'confirmed_first_treatment_date',
        'confirmed_retreatment',
        'confirmed_retreatment_date',
        'confirmed_cured',
        'confirmed_cured_date',
        'date_referred_to_hospital',
        'mda_given',
        'mda_date_given',
        'remarks',
        'isSynced',
        'newInsert',
        'updatedAt',
    ];

    protected $casts = [
        'signs_symptoms' => 'array',
        'isSynced' => 'boolean',
        'newInsert' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function householdProfile(): BelongsTo
    {
        return $this->belongsTo(HouseholdProfile::class, 'profileId');
    }
}