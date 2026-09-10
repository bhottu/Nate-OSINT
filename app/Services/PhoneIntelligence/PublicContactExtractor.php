<?php

namespace App\Services\PhoneIntelligence;

use App\Services\SecurityInspector\UrlGuard;
use Illuminate\Support\Facades\Http;
use Throwable;

class PublicContactExtractor
{
    private const MAX_PAGES = 6;

    private const MAX_BYTES = 1_500_000;

    public function __construct(private UrlGuard $guard, private PhoneParser $parser, private PhoneNormalizer $normalizer, private ConfidenceCalculator $confidence) {}

    public function extract(string $url): array
    {
        $base = rtrim($this->guard->validate($url), '/');
        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.$parts['host'];
        $queue = array_values(array_unique([$base, ...array_map(fn ($path) => $origin.$path, ['/contact', '/contact-us', '/about', '/support', '/help', '/company'])]));
        $visited = [];
        $results = [];
        $officialHost = strtolower($parts['host']);
        $robots = $this->robots($origin);
        while ($queue && count($visited) < self::MAX_PAGES) {
            $page = array_shift($queue);
            if (isset($visited[$page]) || ! $this->sameOrigin($page, $officialHost) || $this->blockedByRobots($page, $robots)) {
                continue;
            }
            $visited[$page] = true;
            try {
                $response = Http::withHeaders(['User-Agent' => 'ImageToMap-PhoneIntelligence/1.0 (public business contact audit)'])->connectTimeout(4)->timeout(8)->withOptions(['allow_redirects' => false])->get($page);
                if ($response->redirect()) {
                    $location = $response->header('Location');
                    if ($location) {
                        $this->guard->assertSafeRedirect($location);
                    }

                    continue;
                }
                if ($response->failed() || strlen($response->body()) > self::MAX_BYTES || ! str_contains(strtolower($response->header('Content-Type', '')), 'text/html')) {
                    continue;
                }
                $html = $response->body();
                $sourceType = $this->sourceType($page, $html);
                foreach ($this->parser->parse($html) as $phone) {
                    $results[] = $this->enrich($phone, $page, $sourceType, $officialHost);
                }
                foreach ($this->structuredData($html) as $phone) {
                    $results[] = $this->enrich($phone, $page, 'structured-data', $officialHost);
                }
                foreach ($this->links($html, $origin, $officialHost) as $link) {
                    $queue[] = $link;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return array_values(collect($results)->keyBy('number')->values()->all());
    }

    private function enrich(array $phone, string $source, string $sourceType, string $officialHost): array
    {
        return [...$phone, 'source' => $source, 'source_type' => $sourceType, 'confidence' => $this->confidence->calculate($sourceType, strtolower((string) parse_url($source, PHP_URL_HOST)) === $officialHost), 'first_discovered' => now()->toIso8601String(), 'last_checked' => now()->toIso8601String()];
    }

    private function structuredData(string $html): array
    {
        preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches);
        $phones = [];
        foreach ($matches[1] ?? [] as $json) {
            $data = json_decode(html_entity_decode($json), true);
            foreach ($this->flatten($data) as $item) {
                if (is_array($item) && isset($item['telephone'])) {
                    $phone = $this->normalizer->normalize((string) $item['telephone']);
                    if ($phone) {
                        $phones[] = $phone;
                    }
                }
            }
        }

        return $phones;
    }

    private function flatten(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        } $items = [$data];
        foreach ($data as $value) {
            if (is_array($value)) {
                $items = array_merge($items, $this->flatten($value));
            }
        }

return $items;
    }

    private function links(string $html, string $origin, string $host): array
    {
        preg_match_all('/href=["\']([^"\'#]+)["\']/i', $html, $matches);

        return collect($matches[1] ?? [])->map(fn ($link) => filter_var($link, FILTER_VALIDATE_URL) ? $link : $origin.'/'.ltrim($link, '/'))->filter(fn ($link) => strtolower((string) parse_url($link, PHP_URL_HOST)) === $host && in_array(pathinfo(parse_url($link, PHP_URL_PATH) ?: '/', PATHINFO_EXTENSION), ['', 'html', 'htm', 'php'], true))->unique()->values()->all();
    }

    private function sourceType(string $url, string $html): string
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        foreach (['contact' => 'contact', 'about' => 'about', 'support' => 'support', 'help' => 'support', 'location' => 'location', 'branch' => 'location'] as $needle => $type) {
            if (str_contains($path, $needle)) {
                return $type;
            }
        }

return str_contains(strtolower($html), 'footer') ? 'footer' : 'website';
    }

    private function sameOrigin(string $url, string $host): bool
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST)) === $host && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    private function robots(string $origin): array
    {
        try {
            return preg_split('/\R/', Http::timeout(3)->get($origin.'/robots.txt')->body()) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    private function blockedByRobots(string $url, array $robots): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        foreach ($robots as $line) {
            if (preg_match('/^Disallow:\s*(\S+)/i', trim($line), $match) && $match[1] !== '/' && str_starts_with($path, $match[1])) {
                return true;
            }
        }

return false;
    }
}
