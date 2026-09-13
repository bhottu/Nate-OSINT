<?php

namespace App\Models\DomainIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainFinding extends Model
{
    protected $table = 'domain_intel_findings';

    protected $fillable = [
        'scan_id', 'title', 'severity', 'category', 'description', 'evidence',
        'source', 'affected_asset', 'confidence', 'remediation',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(DomainScan::class, 'scan_id');
    }
}