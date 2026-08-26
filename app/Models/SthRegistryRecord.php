<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SthRegistryRecord extends Model
{
    use HasFactory;

    protected $table = 'sth_registry_records';

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
        'age_classification',
        'sex',
        'screened',
        'date_of_screening',
        'screening_result',
        'date_of_result',
        'treatment_given',
        'treatment_date_given',
        'january_mda_date',
        'january_mda_modality',
        'july_mda_date',
        'july_mda_modality',
        'remarks',
        'isSynced',
        'newInsert',
        'updatedAt',
    ];

    protected $casts = [
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