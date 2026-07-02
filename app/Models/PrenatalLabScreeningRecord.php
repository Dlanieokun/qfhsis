<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrenatalLabScreeningRecord extends Model
{
    use HasFactory;

    /**
     * Explicitly map the model to the target database table name.
     *
     * @var string
     */
    protected $table = 'prenatal_lab_screening_records';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'maternalRecordId',
        
        // Complete Blood Count (CBC) Parameters
        'cbcDate',
        'cbcResult',
        'cbcRemarks',
        
        // Gestational Diabetes Mellitus (GDM) Parameters
        'gdmDate',
        'gdmResult',
        'gdmRemarks',
        
        // Hepatitis B Screening Parameters
        'hepBDate',
        'hepBResult',
        'hepBRemarks',
        
        // Human Immunodeficiency Virus (HIV) Parameters
        'hivDate',
        'hivResult',
        'hivRemarks',
        
        // Syphilis Diagnostic Parameters
        'syphilisDate',
        'syphilisResult',
        'syphilisRemarks',
    ];

    /**
     * Get the parent MaternalCareRecord that owns this Lab Screening log record.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function maternalCareRecord(): BelongsTo
    {
        return $this->belongsTo(MaternalCareRecord::class, 'maternalRecordId');
    }
}