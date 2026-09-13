<?php

namespace App\Models\DomainIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainEmailSecurity extends Model
{
    protected $table = 'domain_intel_email_security';

    protected $fillable = [
        'scan_id', 'spf_status', 'spf_value', 'dmarc_status', 'dmarc_value',
        'mx_status', 'dkim_status', 'dkim_selectors',
    ];

    protected function casts(): array
    {
        return ['dkim_selectors' => 'array'];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(DomainScan::class, 'scan_id');
    }
}