<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsernameScanResult extends Model
{
    protected $fillable = [
        'scan_id',
        'platform',
        'username',
        'profile_url',
        'status',
        'http_status',
        'response_time_ms',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'http_status' => 'integer',
            'response_time_ms' => 'integer',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(UsernameScan::class, 'scan_id');
    }
}
