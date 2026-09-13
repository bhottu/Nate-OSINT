<?php

namespace App\Models\DomainIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainEvidence extends Model
{
    protected $table = 'domain_intel_evidences';

    protected $fillable = [
        'scan_id', 'source_type', 'source_name', 'source_url', 'observed_at',
        'raw_value', 'normalized_value', 'confidence',
    ];

    protected function casts(): array
    {
        return ['observed_at' => 'datetime'];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(DomainScan::class, 'scan_id');
    }
}