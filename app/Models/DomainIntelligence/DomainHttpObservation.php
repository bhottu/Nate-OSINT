<?php

namespace App\Models\DomainIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainHttpObservation extends Model
{
    protected $table = 'domain_intel_http_observations';

    protected $fillable = [
        'scan_id', 'hostname', 'scheme', 'status_code', 'https', 'redirects',
        'server', 'content_type', 'content_length', 'location', 'cache',
        'security_headers', 'cookies', 'response_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'redirects' => 'array',
            'cache' => 'array',
            'security_headers' => 'array',
            'cookies' => 'array',
            'https' => 'boolean',
            'status_code' => 'integer',
            'content_length' => 'integer',
            'response_time_ms' => 'integer',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(DomainScan::class, 'scan_id');
    }
}