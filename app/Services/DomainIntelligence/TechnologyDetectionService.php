<?php

namespace App\Services\DomainIntelligence;

use DOMDocument;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Technology detection from HTTP headers, cookies and parsed HTML metadata.
 *
 * Evidence based: every detection records the exact signal that produced it.
 * HTML is parsed with DOMDocument (no blind regex over raw markup).
 */
class TechnologyDetectionService
{
    /**
     * @return array<int, array{technology: string, category: string, evidence: string, confidence: string}>
     */
    public function detect(array $headers, string $body, array $cookies = []): array
    {
        $found = [];
        $headerString = strtolower(implode(' ', array_map(
            fn ($v, $k) => strtolower($k).': '.(is_array($v) ? implode(' ', $v) : $v),
            $headers,
            array_keys($headers),
        )));

        $signatures = [
            ['Laravel', 'Framework', 'MEDIUM', '/laravel_session|xsrf-token/i'],
            ['WordPress', 'CMS', 'HIGH', '/wp-content|wp-includes|x-powered-by:\s*wordpress/i'],
            ['Drupal', 'CMS', 'HIGH', '/drupal|generator["\']?\s*content=["\']?drupal/i'],
            ['Next.js', 'Framework', 'HIGH', '/__next|_next\/static/i'],
            ['Nuxt', 'Framework', 'MEDIUM', '/__nuxt|_nuxt\//i'],
            ['React', 'JavaScript Library', 'MEDIUM', '/data-reactroot|react(-dom)?[.@-]|__react/i'],
            ['Vue.js', 'JavaScript Library', 'MEDIUM', '/data-v-[0-9a-f]{8}|vue(\.runtime)?[.@-]/i'],
            ['Alpine.js', 'JavaScript Library', 'MEDIUM', '/alpinejs|x-data=/i'],
            ['jQuery', 'JavaScript Library', 'MEDIUM', '/jquery[.@-]/i'],
            ['Cloudflare', 'CDN', 'HIGH', '/server:\s*cloudflare|cf-ray|__cfduid|cf-cache-status/i'],
            ['Nginx', 'Web Server', 'HIGH', '/server:\s*nginx/i'],
            ['Apache', 'Web Server', 'HIGH', '/server:\s*apache/i'],
            ['Microsoft IIS', 'Web Server', 'HIGH', '/server:\s*microsoft-iis/i'],
            ['PHP', 'Programming Language', 'MEDIUM', '/x-powered-by:\s*php|phpsessid/i'],
            ['ASP.NET', 'Framework', 'MEDIUM', '/x-powered-by:\s*asp\.net|asp\.net_sessionid/i'],
            ['Google Analytics', 'Analytics', 'MEDIUM', '/google-analytics\.com|gtag\(/i'],
            ['Google Tag Manager', 'Analytics', 'MEDIUM', '/googletagmanager\.com/i'],
            ['Vercel', 'Hosting Platform', 'HIGH', '/server:\s*vercel|x-vercel-id/i'],
            ['Shopify', 'CMS', 'HIGH', '/x-shopid|shopify/i'],
            ['Squarespace', 'CMS', 'HIGH', '/squarespace/i'],
        ];

        foreach ($signatures as [$technology, $category, $confidence, $pattern]) {
            if (preg_match($pattern, $headerString) || preg_match($pattern, $body)) {
                $found[] = [
                    'technology' => $technology,
                    'category' => $category,
                    'evidence' => $this->evidence($pattern, $headerString, $body),
                    'confidence' => $confidence,
                ];
            }
        }

        foreach ($cookies as $cookie) {
            $name = strtolower((string) ($cookie['name'] ?? ''));
            if ($name === 'laravel_session') {
                $found[] = ['technology' => 'Laravel', 'category' => 'Framework', 'evidence' => 'cookie: laravel_session', 'confidence' => 'MEDIUM'];
            }
            if ($name === 'phpsessid') {
                $found[] = ['technology' => 'PHP', 'category' => 'Programming Language', 'evidence' => 'cookie: PHPSESSID', 'confidence' => 'MEDIUM'];
            }
        }

        return $this->dedupe($found);
    }

    /**
     * Extract <meta name="generator"> values using a real HTML parser.
     *
     * @return array<int, array{technology: string, category: string, evidence: string, confidence: string}>
     */
    public function metaGenerators(string $body): array
    {
        if (trim($body) === '' || ! str_contains(strtolower($body), '<meta')) {
            return [];
        }

        $generators = [];
        try {
            $previous = libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML(mb_substr($body, 0, 500_000), LIBXML_NOWARNING | LIBXML_NOERROR);
            libxml_use_internal_errors($previous);

            foreach ($dom->getElementsByTagName('meta') as $meta) {
                if (strtolower((string) $meta->getAttribute('name')) === 'generator') {
                    $content = trim($meta->getAttribute('content'));
                    if ($content !== '') {
                        $generators[] = [
                            'technology' => $content,
                            'category' => 'Generator',
                            'evidence' => 'meta generator: '.$content,
                            'confidence' => 'HIGH',
                        ];
                    }
                }
            }
        } catch (Throwable) {
            return $generators;
        }

        return $generators;
    }

    /**
     * Convenience helper that performs a guarded HTTP GET and detects tech.
     *
     * @return array<int, array{technology: string, category: string, evidence: string, confidence: string}>
     */
    public function detectFromUrl(string $url): array
    {
        try {
            $response = Http::timeout((int) config('domain_intelligence.timeouts.request', 12))
                ->connectTimeout((int) config('domain_intelligence.timeouts.connect', 5))
                ->withHeaders(['User-Agent' => 'NateOSINT-DomainIntel/1.0 (passive)'])
                ->get($url);
        } catch (Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        return $this->detect($response->headers(), (string) $response->body());
    }

    private function evidence(string $pattern, string $headerString, string $body): string
    {
        if (preg_match($pattern, $headerString, $match)) {
            return 'header signal: '.trim($match[0]);
        }
        if (preg_match($pattern, $body, $match)) {
            return 'html signal: '.trim($match[0]);
        }

        return 'pattern match';
    }

    /**
     * @param  array<int, array<string, mixed>>  $found
     * @return array<int, array<string, mixed>>
     */
    private function dedupe(array $found): array
    {
        $seen = [];
        $result = [];
        foreach ($found as $entry) {
            $key = strtolower($entry['technology']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $entry;
        }

        return $result;
    }
}