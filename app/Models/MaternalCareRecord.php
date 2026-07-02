<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MaternalCareRecord extends Model
{
    use HasFactory;

    protected $table = 'maternal_care_records';

    protected $fillable = [
        'profileId', 'registrationDate', 'familySerialNumber', 'patientName',
        'homeAddress', 'age', 'ageGroup', 'birthDate', 'ImpDate', 'gravidaPara',
        'eddDate', 'weightKg', 'heightCm', 'bmiValue', 'bmiStatus'
    ];

    public function householdProfile(): BelongsTo
    {
        return $this->belongsTo(HouseholdProfile::class, 'profileId');
    }

    public function prenatal8Anc(): HasOne
    {
        return $this->hasOne(Prenatal8AncRecord::class, 'maternalRecordId');
    }

    public function prenatalImmunization(): HasOne
    {
        return $this->hasOne(PrenatalImmunizationRecord::class, 'maternalRecordId');
    }

    public function prenatalLabScreening(): HasOne
    {
        return $this->hasOne(PrenatalLabScreeningRecord::class, 'maternalRecordId');
    }

    public function prenatalSupplementation(): HasOne
    {
        // Aligned with snake_case column layout structure matching migration parameters
        return $this->hasOne(PrenatalSupplementationRecord::class, 'maternal_record_id');
    }

    public function intrapartum(): HasOne
    {
        return $this->hasOne(IntrapartumRecord::class, 'maternalRecordId');
    }

    public function postpartum(): HasOne
    {
        return $this->hasOne(PostpartumRecord::class, 'maternalRecordId');
    }
}