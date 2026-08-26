<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentalHealthRecord extends Model
{
    use HasFactory;

    protected $table = 'mental_health_records';

    /**
     * Custom primary key (see migration: $table->id('recordNo')).
     */
    protected $primaryKey = 'recordNo';

    protected $fillable = [
        'userId',
        'profileId',
        'dateOfAssessment',
        'familySerialNumber',
        'name',
        'address',
        'dateOfBirth',
        'age',
        'ageGroup',
        'sex',
        'screenedMhgap',
        'isSynced',
        'newInsert',
        'updatedAt',
    ];

    protected $casts = [
        'screenedMhgap' => 'boolean',
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