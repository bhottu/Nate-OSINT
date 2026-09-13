<?php

namespace App\Services\DomainIntelligence\Contracts;

/**
 * Registration data access (RDAP / WHOIS) abstraction.
 */
interface RdapProviderInterface
{
    /**
     * Fetch registration data for a domain.
     *
     * @return array<string, mixed>|null normalized registration data or null when unavailable
     */
    public function domain(string $domain): ?array;

    /**
     * Fetch network / ASN data for an IP address.
     *
     * @return array<string, mixed>|null
     */
    public function ip(string $ip): ?array;
}