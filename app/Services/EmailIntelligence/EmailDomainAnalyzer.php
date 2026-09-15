<?php

namespace App\Services\EmailIntelligence;

use App\Services\DomainIntelligence\Contracts\DnsProviderInterface;
use App\Services\DomainIntelligence\Contracts\IpIntelligenceProviderInterface;
use App\Services\DomainIntelligence\Contracts\RdapProviderInterface;
use App\Services\DomainIntelligence\EmailSecurityService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Technical analysis of the email's domain using public DNS, RDAP, and IP
 * intelligence. Fully passive: no SMTP handshake, no connectivity probes to
 * mail servers, and no conclusion about whether a mailbox actually exists.
 */
class EmailDomainAnalyzer
{
    public function __construct(
        private DnsProviderInterface $dns,
        private EmailSecurityService $emailSecurity,
        private RdapProviderInterface $rdap,
        private IpIntelligenceProviderInterface $ipIntel,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function analyze(string $domain): array
    {
        $records = $this->resolveCached($domain);
        $security = $this->emailSecurity->analyze($domain, $records);

        $mxValues = $security['mx']['values'] ?? [];
        $provider = $this->detectProvider($mxValues);
        $domainStatus = $this->domainStatus($records);
        $mailServers = $this->mailServerDetails($mxValues);
        $dnssec = $this->dnssecStatus($domain);
        $rdapOrg = $this->registrationOrganization($domain);
        $hosting = $this->hostingInfo($records);

        return [
            'domain' => $domain,
            'domain_status' => $domainStatus,
            'mx' => $security['mx'],
            'spf' => $security['spf'],
            'dkim' => $security['dkim'],
            'dmarc' => $security['dmarc'],
            'dnssec' => $dnssec,
            'email_provider' => $provider,
            'mail_servers' => $mailServers,
            'organization' => $rdapOrg,
            'hosting' => $hosting,
            'risk_notes' => $this->riskNotes($security, $domainStatus),
        ];
    }

    /**
     * @return array<string, array<int, array{name: ?string, value: ?string, ttl: int}>>
     */
    private function resolveCached(string $domain): array
    {
        $ttl = (int) config('email_intelligence.cache_ttl.dns', 300);
        $key = 'email-intel:dns:'.hash('sha256', strtolower($domain));

        return Cache::remember($key, $ttl, function () use ($domain) {
            return $this->dns->resolve($domain, ['A', 'AAAA', 'MX', 'NS', 'TXT', 'SOA']);
        });
    }

    /**
     * Identify the email provider from MX record targets. Purely indicative:
     * MX hosts describe who receives mail, not who owns the domain.
     *
     * @param  array<int, string>  $mxValues
     */
    private function detectProvider(array $mxValues): string
    {
        if ($mxValues === []) {
            return 'None detected (no MX records)';
        }

        $joined = strtolower(implode(' ', $mxValues));

        return match (true) {
            str_contains($joined, 'aspmx.l.google.com') || str_contains($joined, 'googlemail.com') || str_contains($joined, 'google.com') => 'Google Workspace / Gmail',
            str_contains($joined, 'mail.protection.outlook.com') || str_contains($joined, 'olc.protection.outlook.com') => 'Microsoft 365 / Outlook',
            str_contains($joined, 'icloud.com') || str_contains($joined, 'mail.me.com') => 'Apple iCloud Mail',
            str_contains($joined, 'yahoodns.net') || str_contains($joined, 'ymail.com') => 'Yahoo Mail',
            str_contains($joined, 'pphosted.com') || str_contains($joined, 'ppe-hosted.com') || str_contains($joined, 'proofpoint') => 'Proofpoint (security gateway)',
            str_contains($joined, 'mimecast.com') => 'Mimecast (security gateway)',
            str_contains($joined, 'zoho') => 'Zoho Mail',
            str_contains($joined, 'protonmail.ch') || str_contains($joined, 'proton.me') => 'Proton Mail',
            str_contains($joined, 'yandex') => 'Yandex Mail',
            str_contains($joined, 'mail.ru') => 'Mail.ru',
            str_contains($joined, 'qq.com') || str_contains($joined, 'tencent') => 'Tencent QQ Mail',
            str_contains($joined, '163.com') || str_contains($joined, '126.com') || str_contains($joined, 'netease') => 'NetEase Mail',
            str_contains($joined, 'messagingengine.com') || str_contains($joined, 'fastmail') => 'Fastmail',
            str_contains($joined, 'secureserver.net') || str_contains($joined, 'godaddy') => 'GoDaddy Email',
            str_contains($joined, 'mailgun.org') || str_contains($joined, 'sendgrid') || str_contains($joined, 'amazonses.com') => 'Transactional email service',
            str_contains($joined, 'ovh.net') => 'OVH Mail',
            str_contains($joined, 'gmx.net') || str_contains($joined, 'web.de') || str_contains($joined, '1und1') => 'GMX / United Internet',
            default => 'Self-hosted / custom mail server',
        };
    }

    /**
     * @param  array<string, array<int, array{name: ?string, value: ?string, ttl: int}>>  $records
     */
    private function domainStatus(array $records): string
    {
        $hasNs = ($records['NS'] ?? []) !== [];
        $hasSoa = ($records['SOA'] ?? []) !== [];
        $hasA = ($records['A'] ?? []) !== [];

        if ($hasNs || $hasSoa || $hasA) {
            return 'ACTIVE (resolvable)';
        }

        return 'INACTIVE OR UNRESOLVABLE';
    }

    /**
     * Resolve A/AAAA records for the first few MX targets (passive DNS only).
     *
     * @param  array<int, string>  $mxValues
     * @return array<int, array{host: string, priority: ?int, ipv4: array<int, string>, ipv6: array<int, string>}>
     */
    private function mailServerDetails(array $mxValues): array
    {
        $maxIps = (int) config('email_intelligence.limits.max_mail_server_ips', 6);
        $details = [];
        $collected = 0;

        foreach (array_slice($mxValues, 0, 3) as $mxValue) {
            $parts = preg_split('/\s+/', trim($mxValue)) ?: [];
            $priority = is_numeric($parts[0] ?? null) ? (int) $parts[0] : null;
            $host = $priority !== null ? ($parts[1] ?? '') : ($parts[0] ?? '');
            if ($host === '' || ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                continue;
            }

            $ipv4 = array_values(array_filter(array_column($this->dns->records($host, 'A'), 'value')));
            $ipv6 = array_values(array_filter(array_column($this->dns->records($host, 'AAAA'), 'value')));

            $details[] = [
                'host' => $host,
                'priority' => $priority,
                'ipv4' => $ipv4,
                'ipv6' => $ipv6,
            ];

            $collected += count($ipv4) + count($ipv6);
            if ($collected >= $maxIps) {
                break;
            }
        }

        return $details;
    }

    /**
     * DNSSEC status via the parent-side DS record, queried through Google's
     * public DNS-over-HTTPS JSON API (native resolvers cannot answer DS).
     *
     * @return array{status: string, note: string}
     */
    private function dnssecStatus(string $domain): array
    {
        $base = rtrim((string) config('email_intelligence.doh.base_url', 'https://dns.google/resolve'), '/');

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('email_intelligence.doh.timeout', 5))
                ->connectTimeout((int) config('email_intelligence.timeouts.connect', 5))
                ->get($base, ['name' => $domain, 'type' => 'DS']);

            if (! $response->successful()) {
                return ['status' => 'UNKNOWN', 'note' => 'DNSSEC check could not be completed.'];
            }

            $body = $response->json();
            $answers = is_array($body) ? (array) ($body['Answer'] ?? []) : [];
            $hasDs = collect($answers)->contains(fn ($a) => is_array($a) && (int) ($a['type'] ?? 0) === 43);

            if ($hasDs) {
                return ['status' => 'YES', 'note' => 'DS record published at the parent zone (DNSSEC signed).'];
            }

            if (is_array($body) && (int) ($body['Status'] ?? -1) === 0) {
                return ['status' => 'NO', 'note' => 'No DS record at the parent zone (unsigned or DNSSEC not enabled).'];
            }

            return ['status' => 'UNKNOWN', 'note' => 'DNSSEC status could not be determined.'];
        } catch (Throwable) {
            return ['status' => 'UNKNOWN', 'note' => 'DNSSEC check could not be completed.'];
        }
    }

    /**
     * Registrant organization from public RDAP data, when disclosed.
     *
     * @return array{status: string, name: ?string, registrar: ?string, source: string}
     */
    private function registrationOrganization(string $domain): array
    {
        $rdap = $this->rdap->domain($domain);
        if ($rdap === null) {
            return ['status' => 'UNKNOWN', 'name' => null, 'registrar' => null, 'source' => 'rdap.org'];
        }

        $name = null;
        $registrar = null;
        foreach ((array) ($rdap['entities'] ?? []) as $entity) {
            if (! is_array($entity)) {
                continue;
            }

            $roles = (array) ($entity['roles'] ?? []);
            $vcardFields = (array) ($entity['vcardArray'][1] ?? []);
            $org = null;
            foreach ($vcardFields as $field) {
                if (is_array($field) && ($field[0] ?? '') === 'org' && isset($field[3])) {
                    $org = is_array($field[3]) ? implode(' ', array_map(strval(...), $field[3])) : (string) $field[3];
                }
            }

            if ($org === null) {
                continue;
            }

            if (in_array('registrar', $roles, true)) {
                $registrar = $org;
            } elseif ($name === null) {
                $name = $org;
            }
        }

        return [
            'status' => ($name !== null || $registrar !== null) ? 'DISCLOSED' : 'NOT DISCLOSED',
            'name' => $name,
            'registrar' => $registrar,
            'source' => 'rdap.org',
        ];
    }

    /**
     * ASN/hosting/country for the domain's primary web IP (approximate by nature).
     *
     * @param  array<string, array<int, array{name: ?string, value: ?string, ttl: int}>>  $records
     * @return array{status: string, asn: ?string, org: ?string, country: ?string, ip: ?string}
     */
    private function hostingInfo(array $records): array
    {
        $ip = (string) (($records['A'][0]['value'] ?? '') ?: '');

        if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['status' => 'UNKNOWN', 'asn' => null, 'org' => null, 'country' => null, 'ip' => null];
        }

        $intel = $this->ipIntel->lookup($ip);
        if ($intel === null) {
            return ['status' => 'UNKNOWN', 'asn' => null, 'org' => null, 'country' => null, 'ip' => $ip];
        }

        return [
            'status' => 'RESOLVED',
            'asn' => $intel['asn'],
            'org' => $intel['asn_org'] ?? $intel['isp'],
            'country' => $intel['country_code'],
            'ip' => $ip,
        ];
    }

    /**
     * Objective, DNS-observable risk notes. No speculation about reputation.
     *
     * @param  array<string, mixed>  $security
     * @return array<int, string>
     */
    private function riskNotes(array $security, string $domainStatus): array
    {
        $notes = [];

        if (($security['spf']['status'] ?? '') === 'MISSING') {
            $notes[] = 'No SPF record: the domain does not declare which servers may send its mail.';
        } elseif (($security['spf']['weak'] ?? false) === true) {
            $notes[] = 'SPF uses "+all": any server may claim to send mail for this domain (misconfiguration).';
        }

        if (($security['dmarc']['status'] ?? '') === 'MISSING') {
            $notes[] = 'No DMARC policy: recipients receive no instruction for handling spoofed mail.';
        }

        if (($security['mx']['status'] ?? '') === 'MISSING' && $domainStatus === 'ACTIVE (resolvable)') {
            $notes[] = 'Domain is active but publishes no MX records: it likely cannot receive email.';
        }

        if ($domainStatus !== 'ACTIVE (resolvable)') {
            $notes[] = 'Domain does not resolve: email to it is very likely undeliverable.';
        }

        return $notes;
    }
}
