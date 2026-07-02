<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostpartumRecord extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'postpartum_records';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'maternalRecordId',
        'visit24hDate',
        'visit1wDate',
        'visit2_4wDate',
        'visit4_6wDate',
        'classificationDate',
        'PostpartumClassification',
        'bpSys24h',
        'bpDias24h',
        'bpSys1w',
        'bpDias1w',
        'bpSys2_4w',
        'bpDias2_4w',
        'bpSys4_6w',
        'bpDias4_6w',
        'highBpGeneral',
        'dangerSignsGeneral',
        'referredGeneral',
        'dsBleeding',
        'dsVision',
        'dsAbdominal',
        'dsFever',
        'dsBreathing',
        'referralDateGeneral',
        'completedIfa',
        'ifaCompletionDate',
        'completedVitA',
        'vitACompletionDate',
        'breastfeedingInitiationDate',
        'ironTabs1st',
        'ironDate1st',
        'ironTabs2nd',
        'ironDate2nd',
        'ironTabs3rd',
        'ironDate3rd',
    ];

    /**
     * Get the parent maternal care record that owns this postpartum log row.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function maternalCareRecord(): BelongsTo
    {
        return $this->belongsTo(MaternalCareRecord::class, 'maternalRecordId');
    }
}