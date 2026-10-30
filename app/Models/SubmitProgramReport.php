<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmitProgramReport extends Model
{
    // The migration creates the singular table name.
    protected $table = 'submit_program_report';

    protected $fillable = [
        'user_id',
        'form',
        'month',
        'year',
        'region_code',
        'province_code',
        'municipality_code',
        'barangay_codes',
        'status',
        'remarks',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'barangay_codes' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}