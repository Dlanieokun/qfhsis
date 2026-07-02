<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyPlanningDropOut extends Model
{
    use HasFactory;

    // Explicitly definition needed due to standard pluralization mismatch
    protected $table = 'family_planning_drop_outs';

    protected $fillable = [
        'recordId', 'profileId', 'dropOutDate', 'reasonCode', 'remarks'
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
