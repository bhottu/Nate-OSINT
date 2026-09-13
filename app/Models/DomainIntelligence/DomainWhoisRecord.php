<?php

namespace App\Models\DomainIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainWhoisRecord extends Model
{
    protected $table = 'domain_intel_whois_records';

    protected $fillable = [
        'scan_id', 'registrar', 'registrar_url', 'status', 'created_date',
        'expiration_date', 'updated_date', 'nameservers', 'registry',
        'dnssec', 'abuse_contact', 'privacy_protected', 'raw', 'source',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'array',
            'nameservers' => 'array',
            'raw' => 'array',
            'dnssec' => 'boolean',
            'privacy_protected' => 'boolean',
            'created_date' => 'datetime',
            'expiration_date' => 'datetime',
            'updated_date' => 'datetime',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(DomainScan::class, 'scan_id');
    }
}