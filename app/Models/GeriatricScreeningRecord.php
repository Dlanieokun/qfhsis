<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeriatricScreeningRecord extends Model
{
    use HasFactory;

    protected $table = 'geriatric_screening_records';
    protected $primaryKey = 'record_no';

    protected $fillable = [
        'user_id',
        'profile_id',
        'date_of_screening',
        'family_serial_number',
        'name',
        'address',
        'date_of_birth',
        'age',
        'sex',
        'results',              // comma-separated, e.g. "A,C"
        'care_plan_provided',
        'ppv_received_at60',
        'ppv_date_given',
        'influenza_date_given',
        'remarks',
        'is_synced',
        'new_insert',
        'updated_at_ts',
    ];

    protected $casts = [
        'care_plan_provided' => 'boolean',
        'ppv_received_at60' => 'boolean',
        'is_synced' => 'boolean',
        'new_insert' => 'boolean',
    ];

    public function householdProfile(): BelongsTo
    {
        return $this->belongsTo(HouseholdProfile::class, 'profile_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
