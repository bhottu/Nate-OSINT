<?php

namespace App\Models\DomainIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainDnsRecord extends Model
{
    protected $table = 'domain_intel_dns_records';

    protected $fillable = [
        'scan_id', 'hostname', 'type', 'name', 'value', 'ttl', 'source', 'observed_at',
    ];

    protected function casts(): array
    {
        return ['observed_at' => 'datetime', 'ttl' => 'integer'];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(DomainScan::class, 'scan_id');
    }
}