<?php

namespace App\Services\DomainIntelligence\Providers;

use App\Services\DomainIntelligence\Contracts\DnsProviderInterface;

/**
 * DNS provider backed by PHP's native resolver (dns_get_record).
 */
class NativeDnsProvider implements DnsProviderInterface
{
    private const TYPE_MAP = [
        'A' => DNS_A,
        'AAAA' => DNS_AAAA,
        'CNAME' => DNS_CNAME,
        'MX' => DNS_MX,
        'NS' => DNS_NS,
        'TXT' => DNS_TXT,
        'SOA' => DNS_SOA,
        'SRV' => DNS_SRV,
        'NAPTR' => DNS_NAPTR,
        'PTR' => DNS_PTR,
    ];

    public function resolve(string $hostname, array $types = ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT', 'SOA', 'CAA', 'SRV', 'NAPTR']): array
    {
        $result = [];
        foreach ($types as $type) {
            $result[$type] = $this->records($hostname, $type);
        }

        return $result;
    }

    public function records(string $hostname, string $type): array
    {
        $flag = self::TYPE_MAP[strtoupper($type)] ?? null;
        if ($flag === null) {
            return [];
        }

        $raw = @dns_get_record($hostname, $flag);
        if ($raw === false || ! is_array($raw)) {
            return [];
        }

        $records = [];
        foreach ($raw as $entry) {
            $value = $this->extractValue($entry, strtoupper($type));
            if ($value === null || $value === '') {
                continue;
            }
            $records[] = [
                'name' => $entry['host'] ?? $hostname,
                'value' => $value,
                'ttl' => (int) ($entry['ttl'] ?? 0),
            ];
        }

        return $records;
    }

    public function reverseDns(string $ip): ?string
    {
        $host = @gethostbyaddr($ip);

        return is_string($host) && $host !== '' && $host !== $ip ? $host : null;
    }

    private function extractValue(array $entry, string $type): ?string
    {
        return match ($type) {
            'A' => $entry['ip'] ?? null,
            'AAAA' => $entry['ipv6'] ?? null,
            'CNAME', 'NS', 'PTR' => $entry['target'] ?? null,
            'MX' => isset($entry['target'], $entry['pri']) ? $entry['pri'].' '.$entry['target'] : ($entry['target'] ?? null),
            'TXT' => $entry['txt'] ?? null,
            'SOA' => isset($entry['mname'], $entry['rname']) ? $entry['mname'].' '.$entry['rname'] : null,
            'CAA' => $this->caaValue($entry),
            'SRV' => isset($entry['target']) ? sprintf('%s %s %s %s', $entry['pri'] ?? '', $entry['weight'] ?? '', $entry['port'] ?? '', $entry['target']) : null,
            'NAPTR' => $entry['replacement'] ?? ($entry['target'] ?? null),
            default => $entry['target'] ?? ($entry['ip'] ?? ($entry['txt'] ?? null)),
        };
    }

    private function caaValue(array $entry): ?string
    {
        $flags = $entry['flags'] ?? '0';
        $tag = $entry['tag'] ?? 'issue';
        $value = $entry['value'] ?? null;

        return $value !== null ? $flags.' '.$tag.' "'.$value.'"' : null;
    }
}