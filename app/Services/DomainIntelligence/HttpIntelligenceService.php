<?php

namespace App\Services\DomainIntelligence;

use App\Services\DomainIntelligence\Support\SsrfGuard;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * HTTP intelligence: safe, guarded observation of status, headers, redirects,
 * security headers and cookies. No crawling, forms, or exploitation.
 */
class HttpIntelligenceService
{
    private const MAX_RESPONSE_BYTES = 2_000_000;

    public function __construct(private SsrfGuard $guard) {}

    /**
     * @return array<string, mixed>
     */
    public function observe(string $url): array
    {
        $this->guard->assertUrlSafe($url);

        $started = microtime(true);
        $current = $url;
        $redirects = [];
        $response = null;

        $maxRedirects = (int) config('domain_intelligence.limits.max_redirects', 5);
        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            try {
                $response = Http::withHeaders(['User-Agent' => 'NateOSINT-DomainIntel/1.0 (passive)'])
                    ->connectTimeout((int) config('domain_intelligence.timeouts.connect', 5))
                    ->timeout((int) config('domain_intelligence.timeouts.request', 12))
                    ->withOptions(['allow_redirects' => false])
                    ->get($current);
            } catch (Throwable $exception) {
                return $this->failure($url, $exception->getMessage(), $redirects);
            }

            if (! $response->redirect()) {
                break;
            }

            $location = (string) $response->header('Location');
            if ($location === '') {
                break;
            }

            $next = $this->absoluteUrl($current, $location);
            try {
                $this->guard->assertUrlSafe($next);
            } catch (Throwable $exception) {
                return $this->failure($url, 'Redirect blocked: '.$exception->getMessage(), $redirects);
            }

            $redirects[] = ['from' => $current, 'to' => $next, 'status' => $response->status()];
            $current = $next;
        }

        if ($response === null) {
            return $this->failure($url, 'No response received.', $redirects);
        }

        $headers = collect($response->headers())
            ->mapWithKeys(fn ($value, $key) => [strtolower($key) => is_array($value) ? implode(', ', $value) : $value])
            ->all();

        $body = (string) $response->body();
        if (strlen($body) > self::MAX_RESPONSE_BYTES) {
            $body = '';
        }

        return [
            'url' => $url,
            'final_url' => $current,
            'status_code' => $response->status(),
            'https' => str_starts_with($current, 'https://'),
            'redirects' => $redirects,
            'server' => $headers['server'] ?? null,
            'content_type' => $headers['content-type'] ?? null,
            'content_length' => isset($headers['content-length']) ? (int) $headers['content-length'] : null,
            'location' => $headers['location'] ?? null,
            'cache' => [
                'cache_control' => $headers['cache-control'] ?? null,
                'etag' => $headers['etag'] ?? null,
                'last_modified' => $headers['last-modified'] ?? null,
            ],
            'security_headers' => $this->securityHeaders($headers),
            'cookies' => $this->cookies($headers['set-cookie'] ?? null),
            'response_time_ms' => (int) round((microtime(true) - $started) * 1000),
            'body' => $body,
            'error' => null,
        ];
    }

    /**
     * @return array<string, array{status: string, value: ?string}>
     */
    public function securityHeaders(array $headers): array
    {
        $definitions = [
            'strict-transport-security' => 'Forces browsers to use HTTPS.',
            'content-security-policy' => 'Controls which resources the browser may load.',
            'x-content-type-options' => 'Prevents MIME type sniffing.',
            'x-frame-options' => 'Controls whether the page can be framed.',
            'referrer-policy' => 'Controls URL data sent in Referer headers.',
            'permissions-policy' => 'Limits powerful browser features.',
            'cross-origin-opener-policy' => 'Separates browsing context groups.',
            'cross-origin-resource-policy' => 'Controls who may load resources.',
        ];

        $result = [];
        foreach ($definitions as $name => $description) {
            $value = $headers[$name] ?? null;
            $result[$name] = [
                'status' => $value !== null && $value !== '' ? 'PRESENT' : 'MISSING',
                'value' => $value,
                'description' => $description,
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cookies(?string $setCookieHeader): array
    {
        if (! $setCookieHeader) {
            return [];
        }

        $cookies = [];
        foreach (preg_split('/(?<=\b\w)=(?=[^;]*;)/', $setCookieHeader) ?: [$setCookieHeader] as $chunk) {
            preg_match('/^([^=]+)=([^;]*)(.*)$/', trim($chunk), $match);
            if (! isset($match[1])) {
                continue;
            }
            $attributes = strtolower($match[3] ?? '');
            $cookies[] = [
                'name' => $match[1],
                'secure' => str_contains($attributes, 'secure'),
                'http_only' => str_contains($attributes, 'httponly'),
                'same_site' => preg_match('/samesite=([^;]+)/i', $attributes, $same) ? $same[1] : null,
                'domain' => preg_match('/domain=([^;]+)/i', $attributes, $domain) ? $domain[1] : null,
            ];
        }

        return $cookies;
    }

    private function absoluteUrl(string $current, string $location): string
    {
        if (filter_var($location, FILTER_VALIDATE_URL)) {
            return $location;
        }

        $parts = parse_url($current);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        return $origin.rtrim(dirname($parts['path'] ?? '/'), '/').'/'.$location;
    }

    /**
     * @param  array<int, array<string, mixed>>  $redirects
     * @return array<string, mixed>
     */
    private function failure(string $url, string $message, array $redirects): array
    {
        return [
            'url' => $url,
            'final_url' => $url,
            'status_code' => null,
            'https' => str_starts_with($url, 'https://'),
            'redirects' => $redirects,
            'server' => null,
            'content_type' => null,
            'content_length' => null,
            'location' => null,
            'cache' => [],
            'security_headers' => [],
            'cookies' => [],
            'response_time_ms' => null,
            'body' => '',
            'error' => $message,
        ];
    }
}