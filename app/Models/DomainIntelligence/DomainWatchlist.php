<?php

namespace App\Models\DomainIntelligence;

use Illuminate\Database\Eloquent\Model;

class DomainWatchlist extends Model
{
    protected $table = 'domain_intel_watchlists';

    protected $fillable = [
        'domain', 'frequency', 'active', 'last_checked_at', 'last_score', 'last_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_checked_at' => 'datetime',
            'last_snapshot' => 'array',
            'last_score' => 'integer',
        ];
    }
}