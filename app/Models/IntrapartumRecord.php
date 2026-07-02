<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntrapartumRecord extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'intrapartum_records';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'maternalRecordId',
        'deliveryOutcome',
        'deliveryType',
        'sex',
        'birthWeight',
        'weightClassification',
        'placeOfDelivery',
        'attendantAtBirth',
        'deliveryDate',
        'deliveryTime',
        'remarks',
    ];

    /**
     * Get the parent maternal care record that owns this intrapartum delivery summary.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function maternalCareRecord(): BelongsTo
    {
        return $this->belongsTo(MaternalCareRecord::class, 'maternalRecordId');
    }
}