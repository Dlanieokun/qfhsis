<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrenatalSupplementationRecord extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'prenatal_supplementation_records';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'maternal_record_id',
        
        // Section 1: Deworming Status parameters tracking
        'received_deworming',
        'deworming_date',

        // Section 2: Iron Folic Acid (IFA) Tablets (#) and Dates (d)
        'ifa_v1_num',
        'ifa_v1_date',
        'ifa_v2_num',
        'ifa_v2_date',
        'ifa_v3_num',
        'ifa_v3_date',
        'ifa_v4_num',
        'ifa_v4_date',
        'ifa_v5_num',
        'ifa_v5_date',
        'ifa_v6_num',
        'ifa_v6_date',
        'completed_ifa',
        'ifa_completed_date',

        // Section 3: Multiple Micronutrient (MM) Tablets (#) and Dates (d)
        'mm_v1_num',
        'mm_v1_date',
        'mm_v2_num',
        'mm_v2_date',
        'mm_v3_num',
        'mm_v3_date',
        'mm_v4_num',
        'mm_v4_date',
        'mm_v5_num',
        'mm_v5_date',
        'mm_v6_num',
        'mm_v6_date',
        'completed_mm',
        'mm_completed_date',

        // Section 4: Calcium Carbonate (CC) Tablets (#) and Dates (d)
        'cc_v2_num',
        'cc_v2_date',
        'cc_v3_num',
        'cc_v3_date',
        'cc_v4_num',
        'cc_v4_date',
        'completed_cc',
        'cc_completed_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'received_deworming' => 'boolean',
        'completed_ifa'     => 'boolean',
        'completed_mm'      => 'boolean',
        'completed_cc'      => 'boolean',
    ];

    /**
     * Get the parent maternal care record that owns this supplementation log.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function maternalCareRecord(): BelongsTo
    {
        return $this->belongsTo(MaternalCareRecord::class, 'maternal_record_id');
    }
}