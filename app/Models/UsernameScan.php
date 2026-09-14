<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UsernameScan extends Model
{
    protected $fillable = [
        'username',
        'normalized_username',
        'total_checked',
        'total_found',
        'status',
        'scan_time_seconds',
    ];

    protected function casts(): array
    {
        return [
            'total_checked' => 'integer',
            'total_found' => 'integer',
            'scan_time_seconds' => 'float',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(UsernameScanResult::class, 'scan_id');
    }
}
