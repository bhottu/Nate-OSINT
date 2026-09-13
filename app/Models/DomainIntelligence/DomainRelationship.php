<?php

namespace App\Models\DomainIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainRelationship extends Model
{
    protected $table = 'domain_intel_relationships';

    protected $fillable = [
        'scan_id', 'from_node', 'from_type', 'to_node', 'to_type',
        'relationship', 'evidence', 'confidence',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(DomainScan::class, 'scan_id');
    }
}