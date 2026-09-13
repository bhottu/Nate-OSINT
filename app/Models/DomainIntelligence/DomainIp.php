<?php

namespace App\Models\DomainIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainIp extends Model
{
    protected $table = 'domain_intel_ips';

    protected $fillable = [
        'scan_id', 'ip', 'version', 'asn', 'asn_org', 'isp', 'hosting',
        'country', 'region', 'city', 'reverse_dns', 'network', 'prefix',
        'rir', 'source', 'confidence',
    ];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(DomainScan::class, 'scan_id');
    }
}