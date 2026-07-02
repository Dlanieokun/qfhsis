<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FhsisReport extends Model
{
    protected $fillable = [
        'user_id', 'reporting_year', 'reporting_quarter', 
        'total_pregnant_tracked', 'completed_4_anc_visits', 
        'fully_immunized_children', 'infants_exclusive_breastfed', 'status'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
