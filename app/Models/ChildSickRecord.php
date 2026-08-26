<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildSickRecord extends Model
{
    use HasFactory;

    protected $table = 'child_sick_records';

    protected $fillable = [
        'userId',
        'profileId',
        // Section 1 - Basic Information
        'dateRegistration',
        'familySerialNumber',
        'childName',
        'dateOfBirth',
        'ageMonths',
        'sex',
        'motherName',
        'address',
        // Section 2 - Vitamin A Supplementation
        'vitaminADateGiven',
        'vitaminA100IU',
        'vitaminA200IU',
        // Section 3 - Diagnosis & Management
        'diagnosisMeasles',
        'diagnosisPersistentDiarrhea',
        'diarrheaDateGiven',
        'orsOnly',
        'orsAndZinc',
        'pneumoniaDateGiven',
        'amoxicillinDrops',
        'amoxicillinClavulanate',
        'cefuroxime',
        'pneumoniaOthers',
        'pneumoniaOthersSpec',
        // Section 4 - Remarks
        'remarks',
        'isSynced',
        'newInsert',
        'updatedAt',
    ];

    protected $casts = [
        'vitaminA100IU' => 'boolean',
        'vitaminA200IU' => 'boolean',
        'diagnosisMeasles' => 'boolean',
        'diagnosisPersistentDiarrhea' => 'boolean',
        'orsOnly' => 'boolean',
        'orsAndZinc' => 'boolean',
        'amoxicillinDrops' => 'boolean',
        'amoxicillinClavulanate' => 'boolean',
        'cefuroxime' => 'boolean',
        'pneumoniaOthers' => 'boolean',
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