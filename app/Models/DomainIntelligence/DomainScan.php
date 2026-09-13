<?php

namespace App\Models\DomainIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DomainScan extends Model
{
    protected $table = 'domain_intel_scans';

    protected $fillable = [
        'domain', 'hostname', 'input', 'status', 'stage', 'progress', 'score',
        'grade', 'posture', 'summary', 'meta', 'error', 'ip_address',
        'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'meta' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'progress' => 'integer',
            'score' => 'integer',
        ];
    }

    public function dnsRecords(): HasMany
    {
        return $this->hasMany(DomainDnsRecord::class, 'scan_id');
    }

    public function subdomains(): HasMany
    {
        return $this->hasMany(DomainSubdomain::class, 'scan_id');
    }

    public function ips(): HasMany
    {
        return $this->hasMany(DomainIp::class, 'scan_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(DomainCertificate::class, 'scan_id');
    }

    public function whois(): HasOne
    {
        return $this->hasOne(DomainWhoisRecord::class, 'scan_id');
    }

    public function technologies(): HasMany
    {
        return $this->hasMany(DomainTechnology::class, 'scan_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(DomainFinding::class, 'scan_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(DomainEvidence::class, 'scan_id');
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(DomainRelationship::class, 'scan_id');
    }

    public function emailSecurity(): HasOne
    {
        return $this->hasOne(DomainEmailSecurity::class, 'scan_id');
    }

    public function httpObservations(): HasMany
    {
        return $this->hasMany(DomainHttpObservation::class, 'scan_id');
    }

    public function tlsObservations(): HasMany
    {
        return $this->hasMany(DomainTlsObservation::class, 'scan_id');
    }
}