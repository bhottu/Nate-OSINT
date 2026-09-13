<?php

namespace App\Services\DomainIntelligence\Contracts;

/**
 * Certificate Transparency log access abstraction.
 */
interface CtProviderInterface
{
    /**
     * Fetch certificate entries observed for a domain.
     *
     * @return array<int, array<string, mixed>> list of normalized certificate entries
     */
    public function certificates(string $domain): array;
}