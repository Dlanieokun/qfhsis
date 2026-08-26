<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EyesScreening extends Model
{
    use HasFactory;

    protected $table = 'eyes_screenings';

    protected $fillable = [
        'profile_id',
        'userId',
        'date_screening',
        'family_serial',
        'name',
        'address',
        'date_of_birth',
        'age',
        'age_group',
        'sex',
        'screened',
        'eye_disease_code',
        'date_referred',
        'remarks',
        'isSynced',
        'newInsert',
        'updatedAt',
    ];

    protected $casts = [
        'screened' => 'integer',
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