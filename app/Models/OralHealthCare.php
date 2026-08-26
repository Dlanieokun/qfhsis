<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OralHealthCare extends Model
{
    use HasFactory;

    protected $table = 'oral_health_care';

    protected $fillable = [
        'profile_id',
        'userId',
        'date_of_visit',
        'family_serial',
        'name',
        'address',
        'date_of_birth',
        'age_months',
        'sex',
        // RPOC 0-11 months
        'rpoc0_oral_screening',
        'rpoc0_risk_assessment',
        'rpoc0_oral_hygiene',
        'rpoc0_counseling',
        'rpoc0_fluoride_varnish',
        'complete_rpoc0',
        'age_years',
        'age_group1st',
        'age_group2nd',
        // RPOC 1yr+ / Pregnant
        'oral_screening1st',
        'oral_screening2nd',
        'risk_assessment1st',
        'risk_assessment2nd',
        'oral_prophylaxis1st',
        'oral_prophylaxis2nd',
        'fluoride_varnish1st',
        'fluoride_varnish2nd',
        'counseling1st',
        'counseling2nd',
        'complete_rpoc1st',
        'complete_rpoc2nd',
        'service_location1st',
        'service_location2nd',
        'remarks',
        'isSynced',
        'newInsert',
        'updatedAt',
    ];

    protected $casts = [
        'rpoc0_oral_screening' => 'boolean',
        'rpoc0_risk_assessment' => 'boolean',
        'rpoc0_oral_hygiene' => 'boolean',
        'rpoc0_counseling' => 'boolean',
        'rpoc0_fluoride_varnish' => 'boolean',
        'complete_rpoc0' => 'integer',
        'complete_rpoc1st' => 'integer',
        'complete_rpoc2nd' => 'integer',
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