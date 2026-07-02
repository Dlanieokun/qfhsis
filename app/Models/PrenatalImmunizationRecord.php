<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrenatalImmunizationRecord extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'prenatal_immunization_records';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'maternalRecordId',
        'td1Date',
        'td2Date',
        'td3Date',
        'td4Date',
        'td5Date',
    ];

    /**
     * Get the parent maternal care record that owns this immunization log.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function maternalCareRecord(): BelongsTo
    {
        return $this->belongsTo(MaternalCareRecord::class, 'maternalRecordId');
    }
}