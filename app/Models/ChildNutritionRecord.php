<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildNutritionRecord extends Model
{
    use HasFactory;

    protected $table = 'child_nutrition_records';

    protected $fillable = [
        'profileId',
        'userId',
        // Section 1: Client Identification
        'dateRegistration',
        'familySerialNumber',
        'childName',
        'dateOfBirth',
        'ageMonths',
        'sex',
        'motherName',
        'address',
        // Section 2: Newborn Assessment
        'lengthAtBirth',
        'weightAtBirth',
        'birthWeightStatus',
        'breastfeedingDate',
        'placeOfDelivery',
        // Section 3: Iron Supplementation
        'iron1Month',
        'iron2Months',
        'iron3Months',
        'ironCompleted',
        'ironCompletedDate',
        // Section 4: Vitamin A Supplementation
        'vitaA6to11',
        'vitaA200Y1D1',
        'vitaA200Y1D2',
        'vitaA200Y2D1',
        'vitaA200Y2D2',
        'vitaA200Y3D1',
        'vitaA200Y3D2',
        'vitaA200Y4D1',
        'vitaA200Y4D2',
        // Section 5: MNP Supplementation
        'mnp6to11Provided',
        'mnp6to11Completed',
        'mnp6to11Remarks',
        'mnp12to23Provided',
        'mnp12to23Completed',
        'mnp12to23Remarks',
        // Section 6: LNS-SQ Supplementation
        'lns6to11Provided',
        'lns6to11Completed',
        'lns6to11Remarks',
        'lns12to23Provided',
        'lns12to23Completed',
        'lns12to23Remarks',
        // Section 7: MAM (SFP)
        'mamIdentified',
        'mamEnrolled',
        'mamCured',
        'mamNonCured',
        'mamDefaulted',
        'mamDied',
        // Section 8: SAM (OTC)
        'samIdentified',
        'samAdmitted',
        'samCured',
        'samNonCured',
        'samDefaulted',
        'samDied',
        // Section 9: Remarks
        'remarks',
        'isSynced',
        'newInsert',
        'updatedAt',
    ];

    protected $casts = [
        'ironCompleted' => 'integer',
        'mamIdentified' => 'integer',
        'mamEnrolled' => 'integer',
        'mamCured' => 'integer',
        'mamNonCured' => 'integer',
        'mamDefaulted' => 'integer',
        'mamDied' => 'integer',
        'samIdentified' => 'integer',
        'samAdmitted' => 'integer',
        'samCured' => 'integer',
        'samNonCured' => 'integer',
        'samDefaulted' => 'integer',
        'samDied' => 'integer',
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