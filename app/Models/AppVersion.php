<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    protected $fillable = [
        'version_name',
        'file_path',
        'file_size',
        'checksum',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function downloadUrl(): string
    {
        return asset($this->file_path);
    }
}
