<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prenatal8AncRecord extends Model
{
    use HasFactory;

    // Custom table name mapping
    protected $table = 'prenatal_8anc_records';

    protected $fillable = [
        'maternalRecordId', 'visit1Date', 'visit1Bp', 'visit2Date', 'visit2Bp',
        'visit3Date', 'visit3Bp', 'visit4Date', 'visit4Bp', 'visit5Date', 'visit5Bp',
        'visit6Date', 'visit6Bp', 'visit7Date', 'visit7Bp', 'visit8Date', 'visit8Bp',
        'completed8Anc', 'highBp', 'dangerSigns', 'dangerSignsDetail',
        'highBpReferred', 'dateReferred', 'classificationStatus', 'classificationDate'
    ];

    public function maternalCareRecord(): BelongsTo
    {
        return $this->belongsTo(MaternalCareRecord::class, 'maternalRecordId');
    }
}