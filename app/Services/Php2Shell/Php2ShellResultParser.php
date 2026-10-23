<?php

namespace App\Services\Php2Shell;

/**
 * Ekstraksi dan penyaringan informasi hasil yang ditampilkan.
 * Kredensial/secret tidak pernah disimpan dalam evidence.
 */
class Php2ShellResultParser
{
    public const EXCERPT_LIMIT = 2048;

    private const SENSITIVE_HEADER_NAMES = [
        'authorization',
        'cookie',
        'proxy-authorization',
        'set-cookie',
        'x-api-key',
    ];

    public function poweredBy(array $headers): ?string
    {
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'x-powered-by') {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * Ambil cuplikan tubuh respons yang aman ditampilkan (tanpa secret).
     */
    public function excerpt(?string $body, int $limit = self::EXCERPT_LIMIT): ?string
    {
        if (! is_string($body) || $body === '') {
            return null;
        }

        $sanitized = $this->sanitizeText($body);
        $sanitized = preg_replace('/\s+/', ' ', $sanitized);

        if (strlen($sanitized) > $limit) {
            $sanitized = substr($sanitized, 0, $limit) . '…';
        }

        return $sanitized;
    }

    /**
     * Hilangkan nilai header yang mungkin mengandung secret dari evidence.
     */
    public function sanitizeHeaders(array $headers): array
    {
        $clean = [];
        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string) $name), self::SENSITIVE_HEADER_NAMES, true)) {
                $clean[$name] = '[redacted]';
            } else {
                $clean[$name] = is_array($value) ? implode(', ', $value) : $value;
            }
        }

        return $clean;
    }

    private function sanitizeText(string $text): string
    {
        // Hapus pola yang sering digunakan untuk bearer token / API key sederhana.
        $text = preg_replace('/(Bearer\s+)[^\s]+/i', '$1[redacted]', $text);
        $text = preg_replace('/(api[_-]?key["\']?\s*[:=]\s*)[^\s,&]+/i', '$1[redacted]', $text);
        $text = preg_replace('/(password["\']?\s*[:=]\s*)[^\s,&]+/i', '$1[redacted]', $text);

        return $text;
    }
}
