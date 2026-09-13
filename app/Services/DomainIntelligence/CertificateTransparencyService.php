<?php

namespace App\Services\DomainIntelligence;

use App\Services\DomainIntelligence\Contracts\CtProviderInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Certificate Transparency intelligence: certificates observed in public CT
 * logs plus the hostnames they reveal.
 */
class CertificateTransparencyService
{
    public function __construct(private CtProviderInterface $ct) {}

    /**
     * @return array{certificates: array<int, array<string, mixed>>, hostnames: array<int, string>}
     */
    public function analyze(string $domain): array
    {
        $ttl = (int) config('domain_intelligence.cache_ttl.ct', 1800);
        $key = 'di.ct.'.strtolower($domain);

        $payload = Cache::remember($key, $ttl, function () use ($domain) {
            $certificates = $this->ct->certificates($domain);
            $hostnames = [];
            foreach ($certificates as $certificate) {
                foreach ($certificate['names'] ?? [] as $name) {
                    $name = strtolower(trim((string) $name));
                    if ($name !== '' && str_ends_with($name, '.'.$domain)) {
                        $hostnames[$name] = true;
                    }
                }
            }

            return [
                'certificates' => $certificates,
                'hostnames' => array_keys($hostnames),
            ];
        });

        return $payload;
    }

    /**
     * @return array<int, string>
     */
    public function discoveredHostnames(string $domain): array
    {
        return $this->analyze($domain)['hostnames'];
    }
}