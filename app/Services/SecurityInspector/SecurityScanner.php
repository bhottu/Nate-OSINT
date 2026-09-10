<?php

namespace App\Services\SecurityInspector;

use Illuminate\Support\Facades\Http;

class SecurityScanner
{
    private const MAX_RESPONSE_BYTES = 2_000_000;

    public function __construct(private UrlGuard $guard, private SecurityScoreCalculator $scores) {}

    public function scan(string $url): array
    {
        $target = $this->guard->validate($url);
        $started = microtime(true);
        $currentUrl = $target;
        $redirects = [];
        for ($redirectCount = 0; $redirectCount <= 5; $redirectCount++) {
            $response = Http::withHeaders(['User-Agent' => 'ImageToMap-SecurityInspector/1.0 (passive audit)'])
                ->connectTimeout(5)->timeout(10)->withOptions([
                    'allow_redirects' => false,
                    'on_headers' => function ($response) {
                        if ((int) $response->getHeaderLine('Content-Length') > self::MAX_RESPONSE_BYTES) {
                            throw new \RuntimeException('The response is larger than the passive scan limit.');
                        }
                    },
                ])->get($currentUrl);
            if (! $response->redirect()) {
                break;
            }
            $location = $response->header('Location');
            if (! $location) {
                break;
            }
            $nextUrl = $this->redirectUrl($currentUrl, $location);
            $this->guard->assertSafeRedirect($nextUrl);
            $redirects[] = ['from' => $currentUrl, 'to' => $nextUrl, 'status' => $response->status()];
            $currentUrl = $nextUrl;
        }
        if ($response->redirect()) {
            throw new \RuntimeException('Too many redirects or an incomplete redirect chain was returned.');
        }
        if (strlen((string) $response->body()) > self::MAX_RESPONSE_BYTES) {
            throw new \RuntimeException('The response is larger than the passive scan limit.');
        }
        $finalUrl = $currentUrl;
        $headers = collect($response->headers())->mapWithKeys(fn ($value, $key) => [strtolower($key) => is_array($value) ? implode(', ', $value) : $value])->all();
        $headerChecks = $this->headerChecks($headers);
        $cookies = $this->cookies($headers['set-cookie'] ?? []);
        $dns = $this->dns(parse_url($finalUrl, PHP_URL_HOST));
        $ssl = parse_url($finalUrl, PHP_URL_SCHEME) === 'https' ? $this->ssl($finalUrl) : ['available' => false, 'valid' => false, 'error' => 'Target is not HTTPS'];
        $technology = $this->technology($headers, (string) $response->body());
        $checks = [
            ['name' => 'HTTPS', 'score' => parse_url($finalUrl, PHP_URL_SCHEME) === 'https' ? 20 : 0, 'max' => 20],
            ['name' => 'SSL/TLS', 'score' => ($ssl['valid'] ?? false) ? 18 : 0, 'max' => 20],
            ['name' => 'Security Headers', 'score' => collect($headerChecks)->sum('score'), 'max' => 40],
            ['name' => 'Cookies', 'score' => $this->cookieScore($cookies), 'max' => 10],
            ['name' => 'DNS', 'score' => ($dns['a'] || $dns['aaaa']) ? 6 : 0, 'max' => 10],
        ];

        return [
            'target' => ['url' => $target, 'domain' => parse_url($target, PHP_URL_HOST), 'final_url' => $finalUrl, 'redirects' => $redirects],
            'http' => ['status' => $response->status(), 'https' => parse_url($finalUrl, PHP_URL_SCHEME) === 'https', 'response_time_ms' => (int) round((microtime(true) - $started) * 1000), 'server' => $headers['server'] ?? null, 'content_type' => $headers['content-type'] ?? null, 'http_version' => $response->toPsrResponse()->getProtocolVersion()],
            'headers' => $headerChecks, 'cookies' => $cookies, 'ssl' => $ssl, 'dns' => $dns, 'technology' => $technology,
            'score' => $this->scores->calculate($checks), 'findings' => $this->findings($headerChecks, $ssl, $cookies, $response->status()),
        ];
    }

    private function headerChecks(array $headers): array
    {
        $definitions = [
            'content-security-policy' => ['weight' => 8, 'risk' => 'XSS and injection exposure', 'description' => 'Controls which resources the browser may load.', 'recommendation' => 'Add a restrictive CSP tailored to the application.'],
            'strict-transport-security' => ['weight' => 6, 'risk' => 'Downgrade and interception risk', 'description' => 'Forces browsers to use HTTPS.', 'recommendation' => 'Add HSTS after confirming HTTPS works everywhere.'],
            'x-content-type-options' => ['weight' => 5, 'risk' => 'MIME sniffing risk', 'description' => 'Prevents content type sniffing.', 'recommendation' => 'Set X-Content-Type-Options: nosniff.'],
            'x-frame-options' => ['weight' => 5, 'risk' => 'Clickjacking risk', 'description' => 'Controls whether the page can be framed.', 'recommendation' => 'Set DENY or SAMEORIGIN where appropriate.'],
            'referrer-policy' => ['weight' => 4, 'risk' => 'Referrer data leakage', 'description' => 'Controls URL data sent in Referer headers.', 'recommendation' => 'Set a restrictive Referrer-Policy.'],
            'permissions-policy' => ['weight' => 4, 'risk' => 'Unnecessary browser capability access', 'description' => 'Limits powerful browser features.', 'recommendation' => 'Declare only the permissions the site needs.'],
            'cross-origin-opener-policy' => ['weight' => 3, 'risk' => 'Cross-origin isolation gap', 'description' => 'Separates browsing context groups.', 'recommendation' => 'Consider same-origin for applications that need isolation.'],
            'cross-origin-resource-policy' => ['weight' => 3, 'risk' => 'Cross-origin resource exposure', 'description' => 'Controls who may load resources.', 'recommendation' => 'Set a policy appropriate to the resource.'],
            'cross-origin-embedder-policy' => ['weight' => 2, 'risk' => 'Cross-origin embedding gap', 'description' => 'Controls cross-origin embedded resources.', 'recommendation' => 'Consider require-corp when cross-origin isolation is needed.'],
        ];

        return collect($definitions)->map(function ($definition, $name) use ($headers) {
            $value = $headers[$name] ?? null;

            return ['name' => $name, 'status' => $value ? 'OK' : 'WARN', 'value' => $value, 'score' => $value ? $definition['weight'] : 0, 'max' => $definition['weight'], ...$definition];
        })->values()->all();
    }

    private function redirectUrl(string $currentUrl, string $location): string
    {
        if (filter_var($location, FILTER_VALIDATE_URL)) {
            return $location;
        }
        $parts = parse_url($currentUrl);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }
        $path = dirname($parts['path'] ?? '/');

        return $origin.rtrim($path, '/').'/'.$location;
    }

    private function cookies(array|string $setCookies): array
    {
        $setCookies = is_array($setCookies) ? $setCookies : [$setCookies];

        return collect($setCookies)->map(function ($cookie) {
            preg_match('/^([^=]+)=([^;]*)(.*)$/', $cookie, $match);
            $attributes = strtolower($match[3] ?? '');

            return ['name' => $match[1] ?? 'unknown', 'secure' => str_contains($attributes, 'secure'), 'http_only' => str_contains($attributes, 'httponly'), 'same_site' => preg_match('/samesite=([^;]+)/i', $attributes, $same) ? $same[1] : null, 'domain' => preg_match('/domain=([^;]+)/i', $attributes, $domain) ? $domain[1] : null, 'path' => preg_match('/path=([^;]+)/i', $attributes, $path) ? $path[1] : null, 'expires' => preg_match('/expires=([^;]+)/i', $attributes, $expires) ? trim($expires[1]) : null, 'max_age' => preg_match('/max-age=([^;]+)/i', $attributes, $maxAge) ? $maxAge[1] : null, 'raw' => $cookie];
        })->values()->all();
    }

    private function cookieScore(array $cookies): int
    {
        if (! count($cookies)) {
            return 10;
        }

        return (int) round(collect($cookies)->avg(fn ($cookie) => ($cookie['secure'] ? 1 : 0) + ($cookie['http_only'] ? 1 : 0) + ($cookie['same_site'] ? 1 : 0)) / 3 * 10);
    }

    private function dns(?string $host): array
    {
        if (! $host) {
            return [];
        } $records = dns_get_record($host, DNS_A | DNS_AAAA | DNS_CNAME | DNS_MX | DNS_NS | DNS_TXT) ?: [];

        return ['a' => collect($records)->where('type', 'A')->pluck('ip')->values()->all(), 'aaaa' => collect($records)->where('type', 'AAAA')->pluck('ipv6')->values()->all(), 'cname' => collect($records)->where('type', 'CNAME')->pluck('target')->values()->all(), 'mx' => collect($records)->where('type', 'MX')->pluck('target')->values()->all(), 'ns' => collect($records)->where('type', 'NS')->pluck('target')->values()->all(), 'txt' => collect($records)->where('type', 'TXT')->pluck('txt')->values()->all(), 'spf' => collect($records)->where('type', 'TXT')->pluck('txt')->first(fn ($txt) => str_starts_with(strtolower($txt), 'v=spf1')), 'dmarc' => dns_get_record('_dmarc.'.$host, DNS_TXT)[0]['txt'] ?? null];
    }

    private function ssl(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        $context = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $socket = @stream_socket_client('ssl://'.$host.':443', $errno, $error, 5, STREAM_CLIENT_CONNECT, $context);
        if (! $socket) {
            return ['available' => false, 'valid' => false, 'error' => $error ?: 'Certificate connection failed'];
        } $params = stream_context_get_params($socket);
        $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate'] ?? '');

        return ['available' => true, 'valid' => true, 'issuer' => $cert['issuer']['CN'] ?? null, 'subject' => $cert['subject']['CN'] ?? null, 'san' => $cert['extensions']['subjectAltName'] ?? null, 'expiration' => isset($cert['validTo_time_t']) ? date('c', $cert['validTo_time_t']) : null, 'days_remaining' => isset($cert['validTo_time_t']) ? max(0, (int) floor(($cert['validTo_time_t'] - time()) / 86400)) : null];
    }

    private function technology(array $headers, string $body): array
    {
        $text = strtolower($body);
        $haystack = strtolower(json_encode($headers).' '.$body);
        $known = ['Laravel' => 'laravel_session|laravel', 'WordPress' => 'wp-content|wordpress', 'React' => 'data-reactroot|react', 'Vue' => 'data-v-|vue', 'Next.js' => '__next|next.js', 'Nginx' => 'nginx', 'Apache' => 'apache', 'Cloudflare' => 'cloudflare', 'PHP' => 'php'];

        return collect($known)->filter(fn ($pattern) => preg_match('/'.$pattern.'/i', $haystack))->keys()->values()->all();
    }

    private function findings(array $headers, array $ssl, array $cookies, int $status): array
    {
        $findings = collect($headers)->filter(fn ($header) => $header['status'] === 'WARN')->map(fn ($header) => ['category' => 'Security Headers', 'severity' => $header['max'] >= 6 ? 'HIGH' : 'MEDIUM', 'title' => 'Missing '.strtoupper($header['name']), 'description' => $header['description'], 'evidence' => 'Header not present in response.', 'recommendation' => $header['recommendation']])->values();
        if (! ($ssl['valid'] ?? false)) {
            $findings->push(['category' => 'SSL/TLS', 'severity' => 'HIGH', 'title' => 'TLS certificate could not be verified', 'description' => 'The certificate was not available or valid during the passive check.', 'evidence' => $ssl['error'] ?? 'Certificate unavailable.', 'recommendation' => 'Install and maintain a valid certificate chain.']);
        } foreach ($cookies as $cookie) {
            if (! $cookie['secure'] || ! $cookie['http_only'] || ! $cookie['same_site']) {
                $findings->push(['category' => 'Cookies', 'severity' => 'MEDIUM', 'title' => 'Cookie missing security attributes', 'description' => 'A response cookie does not include all recommended browser protections.', 'evidence' => $cookie['name'], 'recommendation' => 'Review Secure, HttpOnly, and SameSite settings.']);
            }
        }

        return $findings->all();
    }
}
