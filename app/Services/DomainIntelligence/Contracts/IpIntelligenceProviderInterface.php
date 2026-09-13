<?php

namespace App\Services\DomainIntelligence\Contracts;

/**
 * IP / ASN enrichment abstraction.
 */
interface IpIntelligenceProviderInterface
{
    /**
     * Enrich an IP address with ASN, organisation and geo data.
     *
     * @return array<string, mixed>|null null when the provider is unavailable
     */
    public function lookup(string $ip): ?array;
}