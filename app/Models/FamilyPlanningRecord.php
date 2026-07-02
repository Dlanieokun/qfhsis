<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamilyPlanningRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'profileId', 'registrationDate', 'familySerialNumber', 'address',
        'age', 'birthDate', 'ageGroupCategory', 'clientType', 'commoditySource',
        'previousMethod'
    ];

    public function householdProfile(): BelongsTo
    {
        return $this->belongsTo(HouseholdProfile::class, 'profileId');
    }

    public function dropOuts(): HasMany
    {
        return $this->hasMany(FamilyPlanningDropOut::class, 'recordId');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FamilyPlanningFollowUp::class, 'recordId');
    }
}