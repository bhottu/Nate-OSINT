<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailScanResult extends Model
{
    protected $fillable = [
        'email_scan_id',
        'platform',
        'identifier',
        'profile_url',
        'status',
        'confidence',
        'evidence',
        'source_url',
        'response_time',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'response_time' => 'integer',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(EmailScan::class, 'email_scan_id');
    }
}
