<?php

namespace App\Services\DomainIntelligence\Contracts;

/**
 * Search engine OSINT abstraction (site: queries and friends).
 */
interface SearchProviderInterface
{
    /**
     * Run a public search query.
     *
     * @param  string  $query  raw query string
     * @return array<int, array{title: ?string, url: ?string, snippet: ?string}>
     */
    public function search(string $query): array;

    public function available(): bool;
}