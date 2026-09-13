<?php

namespace App\Services\DomainIntelligence\Support;

use RuntimeException;

/**
 * Normalizes and validates user supplied domain / URL input.
 *
 * Accepted: example.com, www.example.com, https://example.com/path,
 *           HTTPS://WWW.Example.COM/, IDN names, punycode names.
 * Rejected: localhost, bare/private/reserved IPs, malformed hosts,
 *           credentials in URL, non-http schemes.
 */
class DomainNormalizationService
{
    private const BLOCKED_HOSTS = [
        'localhost',
        'localhost.localdomain',
        'metadata.google.internal',
        'metadata.google.com',
    ];

    /**
     * @return array{input: string, hostname: string, domain: string, subdomain: ?string,
     *               tld: ?string, unicode: ?string, punycode: ?string, is_idn: bool}
     */
    public function normalize(string $input): array
    {
        $raw = trim($input);
        if ($raw === '') {
            throw new RuntimeException('Enter a domain name or URL.');
        }

        if (mb_strlen($raw) > 2048) {
            throw new RuntimeException('The input is too long.');
        }

        $candidate = $raw;
        if (! preg_match('#^[a-z][a-z0-9+.\-]*://#i', $candidate)) {
            $candidate = 'http://'.$candidate;
        }

        // Reject URLs that carry credentials (host confusion / SSRF vectors).
        if (preg_match('#://[^/@]*@#', $candidate)) {
            throw new RuntimeException('Credentials in the target are not allowed.');
        }

        $parts = parse_url($candidate);
        $scheme = strtolower($parts['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('Only http and https targets are supported.');
        }

        $host = $parts['host'] ?? null;
        if (! $host) {
            throw new RuntimeException('Enter a valid domain name.');
        }

        $host = strtolower(rtrim($host, '.'));

        // Reject raw IP addresses: domains are expected here and this removes
        // an SSRF surface at the same time.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            throw new RuntimeException('Enter a domain name, not an IP address.');
        }

        if ($this->isBlockedHost($host)) {
            throw new RuntimeException('Private, local, and metadata hosts are not allowed.');
        }

        if (! $this->isValidHostname($host)) {
            throw new RuntimeException('That does not look like a valid hostname.');
        }

        $isIdn = false;
        $unicode = null;
        $punycode = $host;
        if (preg_match('/[^\x00-\x7F]/', $host)) {
            $isIdn = true;
            $unicode = $host;
            $ascii = function_exists('idn_to_ascii')
                ? idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46)
                : false;
            if ($ascii === false || $ascii === null) {
                throw new RuntimeException('The internationalized domain name could not be converted.');
            }
            $punycode = strtolower($ascii);
        }

        $domain = $this->rootDomain($punycode);
        $subdomain = $punycode !== $domain
            ? (substr($punycode, 0, strlen($punycode) - strlen($domain) - 1) ?: null)
            : null;

        return [
            'input' => $raw,
            'hostname' => $punycode,
            'domain' => $domain,
            'subdomain' => $subdomain,
            'tld' => $this->tld($domain),
            'unicode' => $unicode,
            'punycode' => $punycode,
            'is_idn' => $isIdn,
        ];
    }

    private function isBlockedHost(string $host): bool
    {
        foreach (self::BLOCKED_HOSTS as $blocked) {
            if ($host === $blocked || str_ends_with($host, '.'.$blocked)) {
                return true;
            }
        }

        return str_ends_with($host, '.local') || str_ends_with($host, '.internal');
    }

    private function isValidHostname(string $host): bool
    {
        if (strlen($host) > 253) {
            return false;
        }

        foreach (explode('.', $host) as $label) {
            if ($label === '' || strlen($label) > 63) {
                return false;
            }
            if (! preg_match('/^(?!-)[a-z0-9-]+(?<!-)$/', $label)) {
                return false;
            }
        }

        return (bool) preg_match('/\.[a-z]{2,}$/', $host);
    }

    /**
     * Best-effort registrable domain extraction using a small public-suffix
     * fallback list. Never fabricates data; multi-part suffixes are handled
     * with a curated list of common two-part suffixes.
     */
    public function rootDomain(string $host): string
    {
        $labels = explode('.', $host);
        $count = count($labels);

        if ($count <= 2) {
            return $host;
        }

        $twoPartTlds = ['co.uk', 'org.uk', 'gov.uk', 'ac.uk', 'com.au', 'net.au',
            'org.au', 'co.nz', 'com.br', 'com.cn', 'co.jp', 'co.in', 'co.id',
            'com.sg', 'com.my', 'co.za', 'com.tr', 'com.mx', 'com.ar', 'co.kr'];

        $lastTwo = implode('.', array_slice($labels, -2));
        if (in_array($lastTwo, $twoPartTlds, true) && $count >= 3) {
            return implode('.', array_slice($labels, -3));
        }

        return $lastTwo;
    }

    private function tld(string $domain): ?string
    {
        $labels = explode('.', $domain);
        if (count($labels) < 2) {
            return null;
        }

        return implode('.', array_slice($labels, 1)) ?: null;
    }
}