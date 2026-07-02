<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyPlanningFollowUp extends Model
{
    use HasFactory;

    // Explicitly definition needed due to standard pluralization mismatch
    protected $table = 'family_planning_follow_ups';

    protected $fillable = [
        'recordId', 'profileId', 'monthName', 'scheduledDate', 'actualDate'
    ];

    public function familyPlanningRecord(): BelongsTo
    {
        return $this->belongsTo(FamilyPlanningRecord::class, 'recordId');
    }

    public function householdProfile(): BelongsTo
    {
        return $this->belongsTo(HouseholdProfile::class, 'profileId');
    }
}