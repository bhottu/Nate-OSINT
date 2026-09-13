<?php

namespace App\Services\DomainIntelligence\Contracts;

/**
 * Abstraction over DNS resolution so the engine can be tested without
 * touching the network and so alternative resolvers can be plugged in.
 */
interface DnsProviderInterface
{
    /**
     * Resolve all supported record types for a hostname.
     *
     * @param  string  $hostname  normalized hostname
     * @param  array<int, string>  $types  record types to query (A, AAAA, MX, ...)
     * @return array<string, array<int, array{name: ?string, value: ?string, ttl: int}>>
     */
    public function resolve(string $hostname, array $types = ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT', 'SOA', 'CAA', 'SRV', 'NAPTR']): array;

    /**
     * Resolve a single record type.
     *
     * @return array<int, array{name: ?string, value: ?string, ttl: int}>
     */
    public function records(string $hostname, string $type): array;

    /**
     * Reverse DNS (PTR) lookup for an IP address.
     */
    public function reverseDns(string $ip): ?string;
}