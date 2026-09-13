<?php

namespace App\Services\DomainIntelligence;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * TLS / SSL intelligence via a passive certificate handshake.
 * No exploitation, no protocol downgrade probing.
 */
class TlsIntelligenceService
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(string $hostname, int $port = 443): array
    {
        $ttl = (int) config('domain_intelligence.cache_ttl.tls', 3600);

        return Cache::remember('di.tls.'.strtolower($hostname).':'.$port, $ttl, function () use ($hostname, $port) {
            return $this->handshake($hostname, $port);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function handshake(string $hostname, int $port): array
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'capture_peer_cert_chain' => true,
            'verify_peer' => true,
            'verify_peer_name' => true,
            'SNI_enabled' => true,
            'peer_name' => $hostname,
        ]]);

        $socket = @stream_socket_client(
            'ssl://'.$hostname.':'.$port,
            $errno,
            $error,
            (int) config('domain_intelligence.timeouts.connect', 5),
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($socket === false) {
            return [
                'hostname' => $hostname,
                'valid' => false,
                'error' => $error !== '' ? $error : 'TLS connection failed',
                'issuer' => null,
                'subject' => null,
                'san' => [],
                'fingerprint' => null,
                'tls_version' => null,
                'chain' => [],
                'hostname_match' => false,
                'not_before' => null,
                'not_after' => null,
                'days_remaining' => null,
                'ct_present' => false,
            ];
        }

        try {
            $params = stream_context_get_params($socket);
            $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate'] ?? '') ?: [];
            $chain = [];
            foreach ($params['options']['ssl']['peer_certificate_chain'] ?? [] as $chainCert) {
                $parsed = openssl_x509_parse($chainCert) ?: [];
                $chain[] = $parsed['subject']['CN'] ?? ($parsed['issuer']['CN'] ?? null);
            }

            $san = $this->san($cert);
            $notAfter = isset($cert['validTo_time_t']) ? date('c', $cert['validTo_time_t']) : null;
            $notBefore = isset($cert['validFrom_time_t']) ? date('c', $cert['validFrom_time_t']) : null;
            $daysRemaining = isset($cert['validTo_time_t'])
                ? (int) floor(($cert['validTo_time_t'] - time()) / 86400)
                : null;

            $fingerprint = null;
            $rawCert = $params['options']['ssl']['peer_certificate'] ?? false;
            if ($rawCert) {
                openssl_x509_export($rawCert, $export);
                $fingerprint = $export ? hash('sha256', base64_decode(preg_replace('/-+[A-Z]+-+|\s+/', '', $export) ?: '', true) ?: '') : null;
            }

            return [
                'hostname' => $hostname,
                'valid' => true,
                'error' => null,
                'issuer' => $cert['issuer']['CN'] ?? null,
                'subject' => $cert['subject']['CN'] ?? null,
                'san' => $san,
                'fingerprint' => $fingerprint,
                'tls_version' => null,
                'chain' => array_values(array_filter($chain)),
                'hostname_match' => $this->hostnameMatches($hostname, $san, $cert['subject']['CN'] ?? null),
                'not_before' => $notBefore,
                'not_after' => $notAfter,
                'days_remaining' => $daysRemaining,
                'ct_present' => true,
            ];
        } catch (Throwable $exception) {
            return [
                'hostname' => $hostname,
                'valid' => false,
                'error' => $exception->getMessage(),
                'issuer' => null,
                'subject' => null,
                'san' => [],
                'fingerprint' => null,
                'tls_version' => null,
                'chain' => [],
                'hostname_match' => false,
                'not_before' => null,
                'not_after' => null,
                'days_remaining' => null,
                'ct_present' => false,
            ];
        } finally {
            fclose($socket);
        }
    }

    /**
     * @param  array<string, mixed>  $cert
     * @return array<int, string>
     */
    private function san(array $cert): array
    {
        $raw = $cert['extensions']['subjectAltName'] ?? null;
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function hostnameMatches(string $hostname, array $san, ?string $cn): bool
    {
        $candidates = array_map(
            fn (string $entry) => strtolower(preg_replace('/^DNS:/i', '', trim($entry)) ?? ''),
            $san,
        );
        if ($cn) {
            $candidates[] = strtolower($cn);
        }

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            if ($candidate === $hostname) {
                return true;
            }
            if (str_starts_with($candidate, '*.')) {
                $base = substr($candidate, 2);
                if (substr_count($hostname, '.') === substr_count($base, '.') + 1
                    && str_ends_with($hostname, '.'.$base)) {
                    return true;
                }
            }
        }

        return false;
    }
}