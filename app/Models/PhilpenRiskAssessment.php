<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhilpenRiskAssessment extends Model
{
    use HasFactory;

    protected $table = 'philpen_risk_assessments';

    protected $fillable = [
        'profile_id',
        'userId',
        'date_assessment',
        'family_serial',
        'name',
        'address',
        'date_of_birth',
        'age',
        'age_group',
        'sex',
        'current_smoker',
        'bti_ask',
        'bti_advise',
        'bti_assess',
        'bti_assist',
        'bti_arrange',
        'provided_bti',
        'binge_alcohol',
        'insufficient_pa',
        'unhealthy_diet',
        'bmi_category',
        'screening_date1',
        'screening_date2',
        'bp_systolic1',
        'bp_diastolic1',
        'bp_systolic2',
        'bp_diastolic2',
        'hypertension_result',
        'meds_initial',
        'meds_changed',
        'monthly_meds',
        'diabetes_result',
        'antidiabetic_meds',
        'monthly_diabetic_meds',
        'remarks',
        'isSynced',
        'newInsert',
        'updatedAt',
    ];

    protected $casts = [
        'monthly_meds' => 'array',
        'monthly_diabetic_meds' => 'array',
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