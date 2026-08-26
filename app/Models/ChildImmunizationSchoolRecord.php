<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildImmunizationSchoolRecord extends Model
{
    use HasFactory;

    protected $table = 'child_immunization_school_records';

    protected $fillable = [
        'profileId',
        'userId',
        // Demographics
        'registrationDate',
        'familySerialNumber',
        'childName',
        'dateOfBirth',
        'ageYears',
        'sex',
        'address',
        'gradeLevel',
        // School-Based Immunization (SBI)
        'tdDate',
        'mrDate',
        'hpv1SbiDate',
        // Community-Based Immunization (CBI)
        'hpv1CbiDate',
        'hpv2CbiDate',
        // HPV Fully Immunized Female (FIF)
        'hpvCompleted',
        'hpvCompletedDate',
        'remarks',
        'isSynced',
        'newInsert',
        'updatedAt',
    ];

    protected $casts = [
        'hpvCompleted' => 'integer',
        'isSynced' => 'boolean',
        'newInsert' => 'boolean',
    ];

    public function householdProfile(): BelongsTo
    {
        return $this->belongsTo(HouseholdProfile::class, 'profileId');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}