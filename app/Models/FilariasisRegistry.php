<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FilariasisRegistry extends Model
{
    use HasFactory;

    protected $table = 'filariasis_registry_table';

    protected $fillable = [
        'userId',
        'profile_id',
        'date_of_registration',
        'family_serial_number',
        'name',
        'address',
        'date_of_birth',
        'age',
        'age_group',
        'sex',
        'nbe_performed',
        'rdt_performed',
        'date_nbe_rdt',
        'blood_test_result',
        'lymphedema_examined_first_time',
        'has_lymphedema',
        'elephantiasis_examined_first_time',
        'has_elephantiasis',
        'hydrocele_examined_first_time',
        'has_hydrocele',
        'albendazole_date_given',
        'dec_date_given',
        'ivermectin_date_given',
        'remarks',
        'isSynced',
        'newInsert',
        'updatedAt',
    ];

    protected $casts = [
        'nbe_performed' => 'boolean',
        'rdt_performed' => 'boolean',
        'has_lymphedema' => 'boolean',
        'has_elephantiasis' => 'boolean',
        'has_hydrocele' => 'boolean',
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