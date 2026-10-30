<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacilityWorkforceData extends Model
{
    protected $table = 'facility_workforce_data';

    protected $fillable = [
        'year',
        'region_code',
        'province_code',
        'municipality_code',
        'barangay_key',
        'barangay_codes',
        'data',
        'updated_by',
    ];

    protected $casts = [
        'barangay_codes' => 'array',
        'data' => 'array',
    ];
}
