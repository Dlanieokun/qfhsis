<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MorbidityRecord extends Model
{
    use HasFactory;

    /**
     * One row = one ICD code's counts, for one facility/user, for one reporting month.
     * `report_month` is stored as 'YYYY-MM' (e.g. "2026-08") so it sorts and filters cleanly.
     */
    protected $fillable = [
        'user_id',
        'report_month',
        'region',
        'province',
        'municipality',
        'barangay',
        'icdCode',

        'age0to6daysMale',
        'age0to6daysFemale',
        'age7to28daysMale',
        'age7to28daysFemale',
        'age29daysto11moMale',
        'age29daysto11moFemale',
        'age1to4yrsMale',
        'age1to4yrsFemale',
        'age5to9yrsMale',
        'age5to9yrsFemale',
        'age10to14yrsMale',
        'age10to14yrsFemale',
        'age15to19yrsMale',
        'age15to19yrsFemale',
        'age20to24yrsMale',
        'age20to24yrsFemale',
        'age25to29yrsMale',
        'age25to29yrsFemale',
        'age30to34yrsMale',
        'age30to34yrsFemale',
        'age35to39yrsMale',
        'age35to39yrsFemale',
        'age40to44yrsMale',
        'age40to44yrsFemale',
        'age45to49yrsMale',
        'age45to49yrsFemale',
        'age50to54yrsMale',
        'age50to54yrsFemale',
        'age55to59yrsMale',
        'age55to59yrsFemale',
        'age60plusMale',
        'age60plusFemale',
    ];

    protected $casts = [
        'age0to6daysMale' => 'integer',
        'age0to6daysFemale' => 'integer',
        'age7to28daysMale' => 'integer',
        'age7to28daysFemale' => 'integer',
        'age29daysto11moMale' => 'integer',
        'age29daysto11moFemale' => 'integer',
        'age1to4yrsMale' => 'integer',
        'age1to4yrsFemale' => 'integer',
        'age5to9yrsMale' => 'integer',
        'age5to9yrsFemale' => 'integer',
        'age10to14yrsMale' => 'integer',
        'age10to14yrsFemale' => 'integer',
        'age15to19yrsMale' => 'integer',
        'age15to19yrsFemale' => 'integer',
        'age20to24yrsMale' => 'integer',
        'age20to24yrsFemale' => 'integer',
        'age25to29yrsMale' => 'integer',
        'age25to29yrsFemale' => 'integer',
        'age30to34yrsMale' => 'integer',
        'age30to34yrsFemale' => 'integer',
        'age35to39yrsMale' => 'integer',
        'age35to39yrsFemale' => 'integer',
        'age40to44yrsMale' => 'integer',
        'age40to44yrsFemale' => 'integer',
        'age45to49yrsMale' => 'integer',
        'age45to49yrsFemale' => 'integer',
        'age50to54yrsMale' => 'integer',
        'age50to54yrsFemale' => 'integer',
        'age55to59yrsMale' => 'integer',
        'age55to59yrsFemale' => 'integer',
        'age60plusMale' => 'integer',
        'age60plusFemale' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}