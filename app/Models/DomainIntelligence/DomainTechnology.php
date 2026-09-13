<?php

namespace App\Models\DomainIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainTechnology extends Model
{
    protected $table = 'domain_intel_technologies';

    protected $fillable = ['scan_id', 'technology', 'category', 'evidence', 'confidence'];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(DomainScan::class, 'scan_id');
    }
}