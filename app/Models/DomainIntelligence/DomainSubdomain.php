<?php

namespace App\Models\DomainIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainSubdomain extends Model
{
    protected $table = 'domain_intel_subdomains';

    protected $fillable = [
        'scan_id', 'hostname', 'source', 'first_seen', 'last_seen', 'dns_status',
        'a', 'aaaa', 'cname', 'http_status', 'title', 'technologies',
        'ip', 'asn', 'cdn', 'confidence',
    ];

    protected function casts(): array
    {
        return [
            'a' => 'array',
            'aaaa' => 'array',
            'cname' => 'array',
            'technologies' => 'array',
            'first_seen' => 'datetime',
            'last_seen' => 'datetime',
            'http_status' => 'integer',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(DomainScan::class, 'scan_id');
    }
}