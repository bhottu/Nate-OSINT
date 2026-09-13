<?php

namespace App\Models\DomainIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainCertificate extends Model
{
    protected $table = 'domain_intel_certificates';

    protected $fillable = [
        'scan_id', 'hostname', 'issuer', 'subject', 'san', 'wildcard',
        'valid_from', 'valid_until', 'serial', 'fingerprint', 'source',
    ];

    protected function casts(): array
    {
        return [
            'san' => 'array',
            'wildcard' => 'boolean',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(DomainScan::class, 'scan_id');
    }
}