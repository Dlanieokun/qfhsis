<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HouseholdProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'sitio', 'barangay', 'municipality', 'region', 'hhNumber', 'respondent',
        'socioStatus', 'waterSource', 'toiletType', 'familyNumber', 'memberLastName',
        'memberMiddleName', 'memberFirstName', 'relationship', 'sex', 'dob',
        'philhealthId', 'philType', 'philCategory', 'hpn', 'dm', 'tb',
        'fpMethod', 'fpMethodUsed', 'education', 'religion'
    ];

    public function classificationMetrics(): HasMany
    {
        return $this->hasMany(ClassificationMetric::class, 'profile_id');
    }

    public function familyPlanningRecords(): HasMany
    {
        return $this->hasMany(FamilyPlanningRecord::class, 'profileId');
    }

    public function familyPlanningDropOuts(): HasMany
    {
        return $this->hasMany(FamilyPlanningDropOut::class, 'profileId');
    }

    public function familyPlanningFollowUps(): HasMany
    {
        return $this->hasMany(FamilyPlanningFollowUp::class, 'profileId');
    }

    public function maternalCareRecords(): HasMany
    {
        return $this->hasMany(MaternalCareRecord::class, 'profileId');
    }
}