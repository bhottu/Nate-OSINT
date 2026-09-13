<?php

namespace App\Models\DomainIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainTlsObservation extends Model
{
    protected $table = 'domain_intel_tls_observations';

    protected $fillable = [
        'scan_id', 'hostname', 'valid', 'issuer', 'subject', 'san',
        'fingerprint', 'tls_version', 'chain', 'hostname_match',
        'not_before', 'not_after', 'days_remaining', 'ct_present',
    ];

    protected function casts(): array
    {
        return [
            'san' => 'array',
            'chain' => 'array',
            'valid' => 'boolean',
            'hostname_match' => 'boolean',
            'ct_present' => 'boolean',
            'not_before' => 'datetime',
            'not_after' => 'datetime',
            'days_remaining' => 'integer',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(DomainScan::class, 'scan_id');
    }
}