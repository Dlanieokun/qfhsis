<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RabiesRecord extends Model
{
    use HasFactory;

    protected $table = 'rabies_records';

    protected $fillable = [
        'userId',
        'profileId',
        'name',
        'age',
        'sex',
        'civil_status',
        'address',
        'birthdate',
        'birth_place',
        'contact_no',
        'philhealth_no',
        'weight_kg',
        'blood_pressure',
        'date_of_bite',
        'time_of_bite',
        'place_of_bite',
        'injury_scratch',
        'injury_abrasion',
        'injury_laceration',
        'injury_punctured',
        'injury_avulsed',
        'injury_others',
        'injury_others_specify',
        'wound_status',
        'wound_washing',
        'biting_animal',
        'biting_animal_others_specify',
        'ownership_status',
        'animal_status_at_bite',
        'animal_status_at_consult',
        'animal_died_date',
        'animal_vaccination',
        'animal_vaccination_date',
        'condition_epilepsy',
        'condition_dm',
        'condition_hypertension',
        'condition_asthma',
        'condition_alcoholic',
        'condition_egg_allergy',
        'pvrv_day0_date',
        'pvrv_day0_batch',
        'pvrv_day3_date',
        'pvrv_day3_batch',
        'pvrv_day7_date',
        'pvrv_day7_batch',
        'pvrv_day28_date',
        'pvrv_day28_batch',
        'pvrv_outcome',
        'pcev_day0_date',
        'pcev_day0_batch',
        'pcev_day3_date',
        'pcev_day3_batch',
        'pcev_day7_date',
        'pcev_day7_batch',
        'pcev_day28_date',
        'pcev_day28_batch',
        'pcev_outcome',
        'erig',
        'hrig',
        'tetanus_toxoid_date',
        'ats_dose',
        'ats_date',
        'impression',
        'isSynced',
        'newInsert',
        'updatedAt',
    ];

    protected $casts = [
        'injury_scratch' => 'boolean',
        'injury_abrasion' => 'boolean',
        'injury_laceration' => 'boolean',
        'injury_punctured' => 'boolean',
        'injury_avulsed' => 'boolean',
        'injury_others' => 'boolean',
        'condition_epilepsy' => 'boolean',
        'condition_dm' => 'boolean',
        'condition_hypertension' => 'boolean',
        'condition_asthma' => 'boolean',
        'condition_alcoholic' => 'boolean',
        'condition_egg_allergy' => 'boolean',
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