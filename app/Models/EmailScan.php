<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailScan extends Model
{
    protected $fillable = [
        'public_id',
        'email',
        'normalized_email',
        'local_part',
        'domain',
        'status',
        'total_sources_checked',
        'total_potential_matches',
        'total_unverified',
        'total_unable_to_verify',
        'provider',
        'disposable',
        'technical_report',
        'breach_report',
        'scan_time_seconds',
    ];

    protected function casts(): array
    {
        return [
            'total_sources_checked' => 'integer',
            'total_potential_matches' => 'integer',
            'total_unverified' => 'integer',
            'total_unable_to_verify' => 'integer',
            'technical_report' => 'array',
            'breach_report' => 'array',
            'scan_time_seconds' => 'float',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(EmailScanResult::class, 'email_scan_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected static function booted(): void
    {
        static::creating(function (EmailScan $scan): void {
            if (empty($scan->public_id)) {
                do {
                    $token = \Illuminate\Support\Str::random(12);
                } while (static::where('public_id', $token)->exists());

                $scan->public_id = $token;
            }
        });
    }
}
