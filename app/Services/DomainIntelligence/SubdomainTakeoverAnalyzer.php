<?php

namespace App\Services\DomainIntelligence;

use App\Services\DomainIntelligence\Contracts\DnsProviderInterface;

/**
 * Passive subdomain takeover indicator analysis.
 *
 * Only inspects DNS state and well-known deprovisioned service fingerprints.
 * Never attempts an actual takeover, registration, or claim.
 */
class SubdomainTakeoverAnalyzer
{
    /**
     * Fingerprints of deprovisioned services observable in HTTP bodies.
     */
    private const FINGERPRINTS = [
        'GitHub Pages' => '/there isn\'t a github pages site here/i',
        'AWS S3' => '/NoSuchBucket/i',
        'Azure' => '/404 Web Site not found/i',
        'Heroku' => '/no-such-app|There isn\'t a Heroku app here/i',
        'Shopify' => '/Sorry, this shop is currently unavailable/i',
        'Fastly' => '/Fastly error: unknown domain/i',
        'Squarespace' => '/website expired/i',
        'Tumblr' => '/whatever you were looking for doesn\'t currently exist at this address/i',
        'Zendesk' => '/Help Center Closed/i',
        'Surge.sh' => '/project not found/i',
    ];

    /**
     * CNAME targets that commonly indicate a third-party service.
     */
    private const SERVICE_TARGETS = [
        'github.io' => 'GitHub Pages',
        's3.amazonaws.com' => 'AWS S3',
        'azurewebsites.net' => 'Azure',
        'cloudapp.azure.com' => 'Azure',
        'herokuapp.com' => 'Heroku',
        'myshopify.com' => 'Shopify',
        'fastly.net' => 'Fastly',
        'squarespace.com' => 'Squarespace',
        'tumblr.com' => 'Tumblr',
        'zendesk.com' => 'Zendesk',
        'surge.sh' => 'Surge.sh',
    ];

    public function __construct(private DnsProviderInterface $dns) {}

    /**
     * @param  array<int, array<string, mixed>>  $subdomains  discovered subdomain entries
     * @return array<int, array{hostname: string, provider: ?string, reason: string, confidence: string, evidence: string, remediation: string}>
     */
    public function analyze(array $subdomains): array
    {
        $indicators = [];

        foreach ($subdomains as $subdomain) {
            $hostname = (string) ($subdomain['hostname'] ?? '');
            $cname = collect($subdomain['cname'] ?? [])->first();
            $a = collect($subdomain['a'] ?? []);
            $dnsStatus = $subdomain['dns_status'] ?? null;

            if ($hostname === '') {
                continue;
            }

            // Dangling CNAME: points at a third-party service but does not resolve.
            if ($cname && $a->isEmpty() && $dnsStatus === 'UNRESOLVED') {
                $provider = $this->providerFor($cname);
                $indicators[] = [
                    'hostname' => $hostname,
                    'provider' => $provider,
                    'reason' => 'Dangling CNAME to an external service that no longer resolves.',
                    'confidence' => $provider !== null ? 'HIGH-CONFIDENCE TAKEOVER INDICATOR' : 'POSSIBLE TAKEOVER INDICATOR',
                    'evidence' => 'CNAME '.$hostname.' -> '.$cname.' (no A record, target unresolved)',
                    'remediation' => 'Remove the dangling DNS record or re-provision the service.',
                ];

                continue;
            }

            // CNAME to a known service with no A record: weaker indicator.
            if ($cname && $a->isEmpty()) {
                $provider = $this->providerFor($cname);
                if ($provider !== null) {
                    $indicators[] = [
                        'hostname' => $hostname,
                        'provider' => $provider,
                        'reason' => 'CNAME to a third-party service without a resolving A record.',
                        'confidence' => 'POSSIBLE TAKEOVER INDICATOR',
                        'evidence' => 'CNAME '.$hostname.' -> '.$cname.' (no A record)',
                        'remediation' => 'Verify the service is still provisioned for this hostname.',
                    ];
                }
            }
        }

        return $indicators;
    }

    /**
     * Inspect an HTTP body for deprovisioned service fingerprints.
     */
    public function fingerprintBody(string $body): ?string
    {
        foreach (self::FINGERPRINTS as $provider => $pattern) {
            if (preg_match($pattern, $body)) {
                return $provider;
            }
        }

        return null;
    }

    private function providerFor(string $target): ?string
    {
        foreach (self::SERVICE_TARGETS as $suffix => $provider) {
            if (str_ends_with(strtolower($target), $suffix)) {
                return $provider;
            }
        }

        return null;
    }
}