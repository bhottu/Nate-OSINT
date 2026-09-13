<?php

namespace App\Services\DomainIntelligence;

use App\Services\DomainIntelligence\Contracts\DnsProviderInterface;
use Illuminate\Support\Facades\Cache;

/**
 * DNS intelligence: resolves and normalizes all supported record types and
 * derives DNS / email security posture from public DNS data only.
 */
class DnsIntelligenceService
{
    public function __construct(private DnsProviderInterface $dns) {}

    /**
     * @return array{records: array<string, array<int, array{name: ?string, value: ?string, ttl: int}>>, security: array<string, mixed>}
     */
    public function analyze(string $hostname): array
    {
        $ttl = (int) config('domain_intelligence.cache_ttl.dns', 300);
        $records = Cache::remember('di.dns.'.strtolower($hostname), $ttl, fn () => $this->dns->resolve($hostname));

        return [
            'records' => $records,
            'security' => $this->security($hostname, $records),
        ];
    }

    /**
     * @param  array<string, array<int, array{name: ?string, value: ?string, ttl: int}>>  $records
     * @return array<string, mixed>
     */
    public function security(string $hostname, array $records): array
    {
        $txt = $records['TXT'] ?? [];
        $spf = collect($txt)->pluck('value')->first(fn ($v) => is_string($v) && str_starts_with(strtolower($v), 'v=spf1'));
        $dmarc = collect($this->dns->records('_dmarc.'.$hostname, 'TXT'))->pluck('value')->first(fn ($v) => is_string($v) && str_starts_with(strtolower($v), 'v=DMARC1'));
        $mx = $records['MX'] ?? [];
        $ns = $records['NS'] ?? [];
        $caa = $records['CAA'] ?? [];
        $soa = $records['SOA'] ?? [];
        $dnskey = $this->dns->records($hostname, 'DS');

        return [
            'dnssec' => [
                'status' => $dnskey !== [] ? 'ENABLED' : 'UNKNOWN',
                'evidence' => $dnskey !== [] ? 'DS/DNSKEY records observed' : null,
            ],
            'caa' => [
                'status' => $caa !== [] ? 'PRESENT' : 'MISSING',
                'evidence' => collect($caa)->pluck('value')->implode('; ') ?: null,
            ],
            'spf' => [
                'status' => $spf !== null ? 'PRESENT' : 'MISSING',
                'value' => $spf,
                'weak' => $spf !== null && $this->hasWeakSpf($spf),
            ],
            'dmarc' => [
                'status' => $dmarc !== null ? $this->dmarcPolicy($dmarc) : 'MISSING',
                'value' => $dmarc,
            ],
            'mx' => [
                'status' => $mx !== [] ? 'CONFIGURED' : 'MISSING',
                'values' => collect($mx)->pluck('value')->values()->all(),
            ],
            'nameservers' => [
                'status' => count($ns) >= 2 ? 'REDUNDANT' : (count($ns) === 1 ? 'SINGLE' : 'MISSING'),
                'values' => collect($ns)->pluck('value')->values()->all(),
            ],
            'soa' => [
                'status' => $soa !== [] ? 'PRESENT' : 'MISSING',
                'value' => $soa[0]['value'] ?? null,
            ],
        ];
    }

    private function hasWeakSpf(string $spf): bool
    {
        return (bool) preg_match('/[+\-~?]all\s*$/i', trim($spf)) && str_contains($spf, '+all');
    }

    private function dmarcPolicy(string $dmarc): string
    {
        if (preg_match('/p=reject/i', $dmarc)) {
            return 'REJECT';
        }
        if (preg_match('/p=quarantine/i', $dmarc)) {
            return 'QUARANTINE';
        }
        if (preg_match('/p=none/i', $dmarc)) {
            return 'NONE';
        }

        return 'PRESENT';
    }
}