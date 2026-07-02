<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassificationMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile_id', 'q1_age', 'q1_class', 'q2_age', 'q2_class',
        'q3_age', 'q3_class', 'q4_age', 'q4_class'
    ];

    public function householdProfile(): BelongsTo
    {
        return $this->belongsTo(HouseholdProfile::class, 'profile_id');
    }
}