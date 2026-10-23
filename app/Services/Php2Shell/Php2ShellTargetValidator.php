<?php

namespace App\Services\Php2Shell;

use RuntimeException;

/**
 * Validasi dan perlindungan target SSRF untuk PHP2Shell Scanner.
 *
 * Memvalidasi alamat tujuan jaringan SEBELUM koneksi dibuat dan memvalidasi
 * ulang setiap alamat tujuan redirect. Tidak mempercayai validasi dari browser.
 */
class Php2ShellTargetValidator
{
    public const ALLOWED_SCHEMES = ['http', 'https'];

    private const BLOCKED_HOSTNAMES = [
        'localhost',
        'localhost.localdomain',
        'metadata',
        'metadata.google.internal',
        'metadata.google.com',
        'instance-data',
        'ipv4localhost',
        'ipv6localhost',
    ];

    private array $allowedPorts;
    private int $maxUrlLength;

    public function __construct(array $allowedPorts = [80, 443], int $maxUrlLength = 2048)
    {
        $this->allowedPorts = $allowedPorts;
        $this->maxUrlLength = $maxUrlLength;
    }

    public function validate(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            throw new RuntimeException('URL tidak boleh kosong.');
        }

        if (strlen($url) > $this->maxUrlLength) {
            throw new RuntimeException('URL terlalu panjang.');
        }

        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('URL tidak valid.');
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new RuntimeException('Hanya skema HTTP dan HTTPS yang diizinkan.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Kredensial dalam URL tidak diizinkan.');
        }

        $host = strtolower($parts['host']);
        if ($host === '') {
            throw new RuntimeException('URL tidak memiliki host.');
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (! in_array($port, $this->allowedPorts, true)) {
            throw new RuntimeException('Port tidak diizinkan.');
        }

        $this->assertHostSafe($host);

        $normalized = $scheme . '://' . $host;
        if (isset($parts['port']) && $parts['port'] !== ($scheme === 'https' ? 443 : 80)) {
            $normalized .= ':' . $parts['port'];
        }
        $normalized .= $parts['path'] ?? '/';
        if (isset($parts['query'])) {
            $normalized .= '?' . $parts['query'];
        }
        if (isset($parts['fragment'])) {
            $normalized .= '#' . $parts['fragment'];
        }

        return $normalized;
    }

    public function assertHostSafe(string $host): void
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            throw new RuntimeException('Host kosong.');
        }

        if (in_array($host, self::BLOCKED_HOSTNAMES, true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.internal')
            || str_ends_with($host, '.local')) {
            throw new RuntimeException('Host lokal, internal, atau metadata tidak diizinkan.');
        }

        foreach ($this->resolveIps($host) as $ip) {
            $this->assertIpSafe($ip);
        }
    }

    public function assertRedirectSafe(string $location, string $current): string
    {
        $next = $this->resolveRedirectUrl($location, $current);

        $this->validate($next);

        return $next;
    }

    private function resolveRedirectUrl(string $location, string $current): string
    {
        if (filter_var($location, FILTER_VALIDATE_URL) !== false) {
            return $location;
        }

        $parts = parse_url($current);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }

        return $origin . rtrim(dirname($parts['path'] ?? '/'), '/') . '/' . ltrim($location, './');
    }

    private function resolveIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        foreach ($records as $record) {
            if (! empty($record['ip'])) {
                $ips[] = $record['ip'];
            }
            if (! empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    private function assertIpSafe(string $ip): void
    {
        $binary = @inet_pton($ip);
        if ($binary !== false && strlen($binary) === 16
            && str_starts_with($binary, "\0\0\0\0\0\0\0\0\0\0\xff\xff")) {
            $ip = inet_ntop(substr($binary, 12));
        }

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new RuntimeException('Alamat IP tidak valid.');
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new RuntimeException('Alamat IP privat, reserved, atau internal tidak diizinkan.');
        }

        if ($this->isMulticast($ip)) {
            throw new RuntimeException('Alamat multicast tidak diizinkan.');
        }

        $blockedMetadata = ['169.254.169.254', 'fd00:ec2::254', '100.100.100.200'];
        if (in_array($ip, $blockedMetadata, true)) {
            throw new RuntimeException('Endpoint cloud metadata tidak diizinkan.');
        }
    }

    private function isMulticast(string $ip): bool
    {
        if (str_contains($ip, ':')) {
            return str_starts_with($ip, 'ff');
        }

        $binary = @inet_pton($ip);
        if ($binary === false || strlen($binary) !== 4) {
            return false;
        }

        return (ord($binary[0]) & 0xF0) === 0xE0;
    }
}
