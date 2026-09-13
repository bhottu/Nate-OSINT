<?php

namespace App\Services\DomainIntelligence\Providers;

use App\Services\DomainIntelligence\Contracts\SearchProviderInterface;

/**
 * Placeholder search provider. Search engine OSINT stays disabled until a
 * real provider is configured via environment variables — the engine never
 * fabricates results.
 */
class NullSearchProvider implements SearchProviderInterface
{
    public function search(string $query): array
    {
        return [];
    }

    public function available(): bool
    {
        return false;
    }
}