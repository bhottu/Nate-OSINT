<?php

namespace App\Services\DomainIntelligence;

/**
 * Findings engine: turns raw observations into structured, evidence-backed
 * findings with severity, confidence and remediation guidance.
 */
class DomainFindingService
{
    /**
     * @param  array<string, mixed>  $context  collected intelligence
     * @return array<int, array{title: string, severity: string, category: string, description: string,
     *                    evidence: string, source: string, affected_asset: ?string, confidence: string, remediation: string}>
     */
    public function collect(array $context): array
    {
        $findings = [];

        $this->dnsFindings($context, $findings);
        $this->emailFindings($context, $findings);
        $this->tlsFindings($context, $findings);
        $this->httpFindings($context, $findings);
        $this->takeoverFindings($context, $findings);
        $this->registrationFindings($context, $findings);

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function dnsFindings(array $context, array &$findings): void
    {
        $security = $context['dns']['security'] ?? [];

        if (($security['caa']['status'] ?? '') === 'MISSING') {
            $findings[] = $this->finding(
                'CAA RECORD NOT PRESENT',
                'LOW',
                'DNS Security',
                'No CAA records were observed. Any certificate authority may issue certificates for this domain.',
                'CAA lookup returned no records.',
                'DNS',
                $context['hostname'] ?? null,
                'HIGH',
                'Consider publishing CAA records to restrict which CAs may issue certificates.',
            );
        }

        if (($security['nameservers']['status'] ?? '') === 'SINGLE') {
            $findings[] = $this->finding(
                'SINGLE NAMESERVER',
                'MEDIUM',
                'DNS Resilience',
                'Only one nameserver was observed. A single nameserver is a resilience risk.',
                'NS: '.implode(', ', $security['nameservers']['values'] ?? []),
                'DNS',
                $context['hostname'] ?? null,
                'HIGH',
                'Configure at least two nameservers on separate networks.',
            );
        }

        if (($security['soa']['status'] ?? '') === 'MISSING') {
            $findings[] = $this->finding(
                'SOA RECORD NOT OBSERVED',
                'INFO',
                'DNS Configuration',
                'No SOA record was returned for the zone apex.',
                'SOA lookup returned no records.',
                'DNS',
                $context['hostname'] ?? null,
                'MEDIUM',
                'Verify the zone is correctly delegated and served.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function emailFindings(array $context, array &$findings): void
    {
        $email = $context['email'] ?? [];

        if (($email['spf']['status'] ?? '') === 'MISSING') {
            $findings[] = $this->finding(
                'SPF RECORD NOT PRESENT',
                'MEDIUM',
                'Email Security',
                'No SPF record was observed, so receivers cannot verify authorised senders.',
                'No v=spf1 TXT record found.',
                'DNS TXT',
                $context['hostname'] ?? null,
                'HIGH',
                'Publish an SPF record listing authorised sending hosts.',
            );
        }

        if (($email['spf']['weak'] ?? false) === true) {
            $findings[] = $this->finding(
                'SPF PERMISSIVE (+all)',
                'HIGH',
                'Email Security',
                'The SPF record ends with +all, which authorises every host to send email for the domain.',
                (string) ($email['spf']['value'] ?? ''),
                'DNS TXT',
                $context['hostname'] ?? null,
                'HIGH',
                'Replace +all with -all or ~all.',
            );
        }

        if (in_array($email['dmarc']['status'] ?? '', ['MISSING', 'NONE'], true)) {
            $findings[] = $this->finding(
                'DMARC POLICY NOT ENFORCING',
                'MEDIUM',
                'Email Security',
                'DMARC is missing or set to p=none, weakening protection against spoofing.',
                (string) ($email['dmarc']['value'] ?? 'No DMARC record found.'),
                'DNS TXT',
                $context['hostname'] ?? null,
                'HIGH',
                'Consider quarantine/reject after validating legitimate senders.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function tlsFindings(array $context, array &$findings): void
    {
        $tls = $context['tls'] ?? [];

        if (($tls['valid'] ?? false) === false) {
            $findings[] = $this->finding(
                'TLS CERTIFICATE NOT VERIFIED',
                'HIGH',
                'TLS Security',
                'A valid TLS certificate could not be verified during the passive check.',
                (string) ($tls['error'] ?? 'Certificate unavailable.'),
                'TLS',
                $context['hostname'] ?? null,
                'HIGH',
                'Install and maintain a valid certificate chain.',
            );

            return;
        }

        $days = $tls['days_remaining'] ?? null;
        $threshold = (int) config('domain_intelligence.thresholds.certificate_expiring_days', 30);
        if (is_int($days) && $days < 0) {
            $findings[] = $this->finding(
                'CERTIFICATE EXPIRED',
                'HIGH',
                'TLS Security',
                'The TLS certificate has expired.',
                'Expired '.abs($days).' days ago.',
                'TLS',
                $context['hostname'] ?? null,
                'HIGH',
                'Renew the certificate immediately.',
            );
        } elseif (is_int($days) && $days <= $threshold) {
            $findings[] = $this->finding(
                'CERTIFICATE EXPIRING SOON',
                'MEDIUM',
                'TLS Security',
                'The TLS certificate expires within the configured threshold.',
                $days.' days remaining.',
                'TLS',
                $context['hostname'] ?? null,
                'HIGH',
                'Renew the certificate before expiry.',
            );
        }

        if (($tls['hostname_match'] ?? true) === false) {
            $findings[] = $this->finding(
                'HOSTNAME MISMATCH',
                'HIGH',
                'TLS Security',
                'The certificate does not match the requested hostname.',
                'SAN/CN did not include '.$context['hostname'],
                'TLS',
                $context['hostname'] ?? null,
                'HIGH',
                'Issue a certificate covering the hostname.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function httpFindings(array $context, array &$findings): void
    {
        $headers = $context['http']['security_headers'] ?? [];

        foreach ($headers as $name => $header) {
            if (($header['status'] ?? '') === 'MISSING') {
                $findings[] = $this->finding(
                    'MISSING SECURITY HEADER: '.strtoupper($name),
                    'LOW',
                    'HTTP Security',
                    (string) ($header['description'] ?? 'A recommended security header is not present.'),
                    'Header not present in response.',
                    'HTTP',
                    $context['hostname'] ?? null,
                    'HIGH',
                    'Add the header with a value appropriate for the application.',
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function takeoverFindings(array $context, array &$findings): void
    {
        foreach ($context['takeover'] ?? [] as $indicator) {
            $findings[] = $this->finding(
                'SUBDOMAIN TAKEOVER INDICATOR: '.strtoupper((string) $indicator['hostname']),
                'HIGH',
                'Subdomain Takeover',
                (string) $indicator['reason'],
                (string) $indicator['evidence'],
                'DNS',
                $indicator['hostname'] ?? null,
                (string) $indicator['confidence'],
                (string) $indicator['remediation'],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function registrationFindings(array $context, array &$findings): void
    {
        $whois = $context['whois'] ?? [];
        $expiration = $whois['expiration_date'] ?? null;
        $threshold = (int) config('domain_intelligence.thresholds.domain_expiry_warning_days', 30);

        if (is_string($expiration) && $expiration !== '') {
            $timestamp = strtotime($expiration);
            if ($timestamp !== false) {
                $days = (int) floor(($timestamp - time()) / 86400);
                if ($days < 0) {
                    $findings[] = $this->finding(
                        'DOMAIN REGISTRATION EXPIRED',
                        'HIGH',
                        'Registration',
                        'The domain registration has expired according to RDAP data.',
                        'Expired '.abs($days).' days ago.',
                        'RDAP',
                        $context['hostname'] ?? null,
                        'HIGH',
                        'Renew the domain registration.',
                    );
                } elseif ($days <= $threshold) {
                    $findings[] = $this->finding(
                        'DOMAIN EXPIRING SOON',
                        'MEDIUM',
                        'Registration',
                        'The domain registration expires within the configured threshold.',
                        $days.' days remaining.',
                        'RDAP',
                        $context['hostname'] ?? null,
                        'HIGH',
                        'Renew the registration before expiry.',
                    );
                }
            }
        }
    }

    /**
     * @return array{title: string, severity: string, category: string, description: string,
     *               evidence: string, source: string, affected_asset: ?string, confidence: string, remediation: string}
     */
    private function finding(
        string $title,
        string $severity,
        string $category,
        string $description,
        string $evidence,
        string $source,
        ?string $affectedAsset,
        string $confidence,
        string $remediation,
    ): array {
        return [
            'title' => $title,
            'severity' => $severity,
            'category' => $category,
            'description' => $description,
            'evidence' => $evidence,
            'source' => $source,
            'affected_asset' => $affectedAsset,
            'confidence' => $confidence,
            'remediation' => $remediation,
        ];
    }
}