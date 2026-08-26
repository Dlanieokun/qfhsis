<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CervicalCancerScreening extends Model
{
    use HasFactory;

    protected $table = 'cervical_cancer_screenings';

    protected $fillable = [
        'profile_id',
        'userId',
        'date_assessment',
        'family_serial',
        'client_name',
        'address',
        'date_of_birth',
        'age',
        'cervical_screening_done',
        'cervical_result',
        'cervical_linked_to_care',
        'breast_risk_assessment',
        'breast_age_risk_class',
        'breast_exam_type',
        'breast_result',
        'breast_linked_to_care',
        'remarks',
        'isSynced',
        'newInsert',
        'updatedAt',
    ];

    protected $casts = [
        'isSynced' => 'boolean',
        'newInsert' => 'boolean',
    ];

    public function householdProfile(): BelongsTo
    {
        return $this->belongsTo(HouseholdProfile::class, 'profile_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}