<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmitProgramReport extends Model
{
    use HasFactory;

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
    ];

    protected $casts = [
        'barangay_codes' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}