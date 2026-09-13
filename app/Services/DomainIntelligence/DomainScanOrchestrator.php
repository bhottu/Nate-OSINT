<?php

namespace App\Services\DomainIntelligence;

use App\Models\DomainIntelligence\DomainScan;
use App\Services\DomainIntelligence\Support\DomainNormalizationService;
use App\Services\DomainIntelligence\Support\SsrfGuard;
use Throwable;

/**
 * Orchestrates a full domain intelligence scan: normalization, DNS, email
 * security, subdomains, certificates, IP/ASN, TLS and HTTP observations,
 * then persists everything and derives findings + score.
 */
class DomainScanOrchestrator
{
    public function __construct(
        private DomainNormalizationService $normalizer,
        private DnsIntelligenceService $dns,
        private EmailSecurityService $email,
        private SubdomainDiscoveryService $subdomains,
        private CertificateTransparencyService $certificates,
        private IpIntelligenceService $ip,
        private HttpIntelligenceService $http,
        private TlsIntelligenceService $tls,
        private DomainFindingService $findings,
        private DomainScoringService $scoring,
        private DomainGraphService $graph,
        private SsrfGuard $guard,
    ) {}

    public function scan(string $input, ?int $userId = null): DomainScan
    {
        $normalized = $this->normalizer->normalize($input);
        $hostname = $normalized['hostname'];
        $this->guard->assertHostSafe($hostname);

        $scan = DomainScan::create([
            'domain' => $hostname,
            'hostname' => $hostname,
            'input' => $input,
            'status' => 'RUNNING',
            'stage' => 'DNS',
            'progress' => 5,
            'started_at' => now(),
        ]);

        $context = [
            'hostname' => $hostname,
            'dns' => [],
            'email' => [],
            'subdomains' => [],
            'certificates' => [],
            'ips' => [],
            'tls' => [],
            'http' => [],
            'takeover' => [],
            'errors' => [],
        ];

        try {
            $context['dns'] = $this->dns->analyze($hostname);
            $context['email'] = $this->email->analyze($hostname, $context['dns']['records'] ?? []);
        } catch (Throwable $exception) {
            $context['errors'][] = 'DNS: '.$exception->getMessage();
        }

        $scan->update(['stage' => 'SUBDOMAINS', 'progress' => 30]);

        try {
            $context['subdomains'] = $this->subdomains->discover($hostname)->all();
        } catch (Throwable $exception) {
            $context['errors'][] = 'Subdomains: '.$exception->getMessage();
        }

        $scan->update(['stage' => 'CERTIFICATES', 'progress' => 50]);

        try {
            $context['certificates'] = $this->certificates->analyze($hostname)['certificates'];
        } catch (Throwable $exception) {
            $context['errors'][] = 'Certificates: '.$exception->getMessage();
        }

        $ips = $this->collectIps($context);
        $scan->update(['stage' => 'IP INTELLIGENCE', 'progress' => 65]);

        try {
            $context['ips'] = $this->ip->enrich($ips);
        } catch (Throwable $exception) {
            $context['errors'][] = 'IP intelligence: '.$exception->getMessage();
        }

        $scan->update(['stage' => 'TLS', 'progress' => 80]);

        try {
            $context['tls'] = $this->tls->inspect($hostname);
        } catch (Throwable $exception) {
            $context['errors'][] = 'TLS: '.$exception->getMessage();
        }

        $scan->update(['stage' => 'HTTP', 'progress' => 90]);

        try {
            $context['http'] = $this->http->observe('https://'.$hostname);
        } catch (Throwable $exception) {
            $context['errors'][] = 'HTTP: '.$exception->getMessage();
        }

        $context['takeover'] = app(SubdomainTakeoverAnalyzer::class)->analyze($context['subdomains']);

        $collected = $this->findings->collect($context);
        $score = $this->scoring->calculate($collected, $context);

        $this->persist($scan, $context, $collected);

        $scan->update([
            'status' => 'COMPLETED',
            'stage' => 'DONE',
            'progress' => 100,
            'score' => $score['value'],
            'grade' => $score['grade'],
            'posture' => $score['posture'],
            'summary' => [
                'subdomains' => count($context['subdomains']),
                'certificates' => count($context['certificates']),
                'ips' => count($context['ips']),
                'findings' => count($collected),
                'errors' => $context['errors'],
            ],
        ]);

        return $scan->fresh();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function collectIps(array $context): array
    {
        $ips = [];
        foreach ($context['dns']['records']['A'] ?? [] as $record) {
            if (! empty($record['value'])) {
                $ips[] = (string) $record['value'];
            }
        }
        foreach ($context['dns']['records']['AAAA'] ?? [] as $record) {
            if (! empty($record['value'])) {
                $ips[] = (string) $record['value'];
            }
        }
        foreach ($context['subdomains'] ?? [] as $subdomain) {
            if (! empty($subdomain['ip'])) {
                $ips[] = (string) $subdomain['ip'];
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * @param  array<int, array<string, mixed>>  $collected
     */
    private function persist(DomainScan $scan, array $context, array $collected): void
    {
        $scan->dnsRecords()->delete();
        foreach ($context['dns']['records'] ?? [] as $type => $records) {
            foreach ($records as $record) {
                $scan->dnsRecords()->create([
                    'hostname' => $context['hostname'],
                    'type' => $type,
                    'name' => $record['name'] ?? null,
                    'value' => $record['value'] ?? null,
                    'ttl' => $record['ttl'] ?? 0,
                    'source' => 'DNS',
                    'observed_at' => now(),
                ]);
            }
        }

        $scan->emailSecurity()->delete();
        $scan->emailSecurity()->create([
            'spf_status' => $context['email']['spf']['status'] ?? 'UNKNOWN',
            'spf_value' => $context['email']['spf']['value'] ?? null,
            'dmarc_status' => $context['email']['dmarc']['status'] ?? 'UNKNOWN',
            'dmarc_value' => $context['email']['dmarc']['value'] ?? null,
            'mx_status' => $context['email']['mx']['status'] ?? 'UNKNOWN',
            'dkim_status' => $context['email']['dkim']['status'] ?? 'UNKNOWN',
            'dkim_selectors' => $context['email']['dkim']['selectors'] ?? [],
        ]);

        $scan->subdomains()->delete();
        foreach ($context['subdomains'] as $subdomain) {
            $scan->subdomains()->create([
                'hostname' => $subdomain['hostname'],
                'source' => $subdomain['source'],
                'confidence' => $subdomain['confidence'],
                'dns_status' => $subdomain['dns_status'],
                'ip' => $subdomain['ip'],
                'a' => $subdomain['a'],
                'cname' => $subdomain['cname'],
                'first_seen' => now(),
                'last_seen' => now(),
            ]);
        }

        $scan->certificates()->delete();
        foreach ($context['certificates'] as $certificate) {
            $scan->certificates()->create([
                'hostname' => $context['hostname'],
                'issuer' => $certificate['issuer'] ?? null,
                'subject' => $certificate['common_name'] ?? null,
                'san' => $certificate['names'] ?? [],
                'wildcard' => str_contains(implode(' ', $certificate['names'] ?? []), '*.'),
                'valid_from' => isset($certificate['not_before']) ? date('c', strtotime((string) $certificate['not_before'])) : null,
                'valid_until' => isset($certificate['not_after']) ? date('c', strtotime((string) $certificate['not_after'])) : null,
                'serial' => $certificate['serial'] ?? null,
                'source' => 'Certificate Transparency',
            ]);
        }

        $scan->findings()->delete();
        foreach ($collected as $finding) {
            $scan->findings()->create($finding);
        }
    }
}