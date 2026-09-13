<?php

namespace App\Services\DomainIntelligence\Providers;

use App\Services\DomainIntelligence\Contracts\CtProviderInterface;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Certificate Transparency provider backed by the public crt.sh JSON API.
 */
class CrtShProvider implements CtProviderInterface
{
    public function certificates(string $domain): array
    {
        $base = rtrim((string) config('domain_intelligence.providers.ct.base_url', 'https://crt.sh'), '/');
        $url = $base.'/?q=%25.'.urlencode($domain).'&output=json';

        try {
            $response = Http::timeout((int) config('domain_intelligence.timeouts.request', 12))
                ->connectTimeout((int) config('domain_intelligence.timeouts.connect', 5))
                ->retry(1, 500)
                ->get($url);
        } catch (Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $body = $response->json();
        if (! is_array($body)) {
            return [];
        }

        $certificates = [];
        foreach ($body as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $names = $this->names($entry['name_value'] ?? null);
            $certificates[] = [
                'issuer' => $this->issuerName($entry['issuer_name'] ?? null),
                'common_name' => $entry['common_name'] ?? null,
                'names' => $names,
                'not_before' => $entry['not_before'] ?? null,
                'not_after' => $entry['not_after'] ?? null,
                'serial' => $entry['serial_number'] ?? null,
                'id' => $entry['id'] ?? null,
                'source' => 'Certificate Transparency',
                'source_url' => $base.'/?id='.($entry['id'] ?? ''),
            ];
        }

        return $certificates;
    }

    /**
     * Extract unique hostnames covered by CT entries for a domain.
     *
     * @return array<int, string>
     */
    public function hostnames(string $domain): array
    {
        $names = [];
        foreach ($this->certificates($domain) as $certificate) {
            foreach ($certificate['names'] as $name) {
                $name = strtolower(trim($name));
                if ($name === '' || ! str_ends_with($name, '.'.$domain)) {
                    continue;
                }
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * @return array<int, string>
     */
    private function names(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/[\s]+/', $value) ?: [])));
    }

    private function issuerName(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (preg_match('/CN=([^,\s]+)/', $value, $match)) {
            return $match[1];
        }

        return $value;
    }
}