<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvironmentalHealthRecord extends Model
{
    use HasFactory;

    protected $table = 'environmental_health_records';

    protected $fillable = [
        'householdHeadName',
        'userId',
        // Section 1 - Water Source
        'waterLevelI',
        'waterLevelII',
        'waterLevelIII',
        'waterSourceOthers',
        'waterLocatedInsideDwelling',
        'waterAvailable12Hours',
        'microbiologicalTestDate',
        'microbiologicalTestResult',
        'waterSafetyPlanOperational',
        // Section 2 - Sanitation
        'sanitationStatus',
        'unsanitaryToiletType',
        'toiletShared',
        'basicSanitationFacility',
        'disposalDate',
        'disposalInSitu',
        'disposalOffSiteDesludged',
        'disposalOffSiteSewer',
        'safelyManagedSanitationService',
        'safelyManagedDrinkingWater',
        'remarks',
        'isSynced',
        'newInsert',
        'updatedAt',
    ];

    protected $casts = [
        'waterLevelI' => 'boolean',
        'waterLevelII' => 'boolean',
        'waterLevelIII' => 'boolean',
        'waterLocatedInsideDwelling' => 'boolean',
        'waterAvailable12Hours' => 'boolean',
        'microbiologicalTestResult' => 'integer',
        'waterSafetyPlanOperational' => 'integer',
        'unsanitaryToiletType' => 'integer',
        'toiletShared' => 'integer',
        'basicSanitationFacility' => 'integer',
        'disposalInSitu' => 'boolean',
        'disposalOffSiteDesludged' => 'boolean',
        'disposalOffSiteSewer' => 'boolean',
        'safelyManagedSanitationService' => 'integer',
        'safelyManagedDrinkingWater' => 'integer',
        'isSynced' => 'boolean',
        'newInsert' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}