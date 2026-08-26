<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaternalDeath extends Model
{
    use HasFactory;

    protected $table = 'maternal_deaths';

    protected $fillable = [
        'profile_id',
        'date_of_registration',
        'full_name',
        'complete_address',
        'age',
        'age_group',
        'place_of_occurrence',
        'cause_of_death',
        'remarks',
        'synced',
        'sync_timestamp',
    ];

    protected $casts = [
        'age' => 'integer',
        'synced' => 'boolean',
        'sync_timestamp' => 'integer',
    ];

    public function householdProfile(): BelongsTo
    {
        return $this->belongsTo(HouseholdProfile::class, 'profile_id');
    }
}