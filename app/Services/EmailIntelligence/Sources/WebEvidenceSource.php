<?php

namespace App\Services\EmailIntelligence\Sources;

use App\Services\EmailIntelligence\SourceResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Public web evidence via the DuckDuckGo HTML endpoint (html.duckduckgo.com).
 * This is a public search results page — parsed conservatively, never
 * scraped aggressively, and treated as LOW-confidence indicative evidence.
 *
 * Each hit is explicitly a "possible match": a search engine result proves
 * that a page contains the email, not that any account belongs to its owner.
 */
class WebEvidenceSource implements SourceInterface
{
    private const ENDPOINT = 'https://html.duckduckgo.com/html/';

    public function platform(): string
    {
        return 'Web search';
    }

    public function kind(): string
    {
        return 'web_search';
    }

    public function check(string $normalizedEmail): SourceResult
    {
        $max = (int) config('email_intelligence.limits.max_web_evidence', 10);

        try {
            $response = Http::asForm()
                ->withHeaders([
                    'User-Agent' => SourceResult::USER_AGENT,
                    'Accept' => 'text/html',
                ])
                ->timeout((int) config('email_intelligence.timeouts.request', 10))
                ->connectTimeout((int) config('email_intelligence.timeouts.connect', 5))
                ->post(self::ENDPOINT, ['q' => '"'.$normalizedEmail.'"']);
        } catch (Throwable) {
            return SourceResult::error($this->platform(), $this->kind(), 'Search endpoint could not be reached', self::ENDPOINT);
        }

        if ($response->status() === 403 || $response->status() === 429) {
            return SourceResult::unableToVerify($this->platform(), $this->kind(), self::ENDPOINT, 'Search request was blocked or rate limited; unable to verify through public sources.');
        }

        if (! $response->successful()) {
            return SourceResult::error($this->platform(), $this->kind(), 'Unexpected response from search endpoint (HTTP '.$response->status().')', self::ENDPOINT);
        }

        $html = $response->body();
        if ($html === '' || Str::contains($html, 'No  results', ignoreCase: true) || Str::contains($html, 'no results', ignoreCase: true)) {
            return SourceResult::notFound($this->platform(), $this->kind(), self::ENDPOINT, 'No public web results referencing this email address.');
        }

        $hits = $this->extractResults($html, $normalizedEmail, $max);

        if ($hits === []) {
            return SourceResult::notFound($this->platform(), $this->kind(), self::ENDPOINT, 'No public web results referencing this email address.');
        }

        $primary = $hits[0];
        $evidence = sprintf(
            'Public web search returns %d page(s) referencing this email address (showing up to %d). Sources: %s.',
            count($hits),
            $max,
            implode('; ', array_map(fn ($h) => $h['host'], $hits))
        );

        return SourceResult::found(
            $this->platform(),
            $this->kind(),
            $primary['host'],
            $primary['url'],
            $evidence,
            self::ENDPOINT,
            'Low',
            'Search-engine hits only. A page containing the email does not imply any account relationship; listings may be directories, spam dumps, or unrelated documents.'
        );
    }

    /**
     * Extract result links from the DuckDuckGo HTML page.
     *
     * @return array<int, array{url: string, host: string}>
     */
    private function extractResults(string $html, string $email, int $max): array
    {
        $hits = [];
        if (preg_match_all('/<a[^>]+class="[^"]*result__a[^"]*"[^>]+href="([^"]+)"/i', $html, $matches)) {
            foreach ($matches[1] as $href) {
                $url = html_entity_decode($href, ENT_QUOTES | ENT_HTML5);
                // DuckDuckGo wraps outbound links in a redirect: //duckduckgo.com/l/?uddg=<encoded>
                if (preg_match('/uddg=([^&]+)/', $url, $m)) {
                    $url = urldecode($m[1]);
                }
                if (! str_starts_with($url, 'http')) {
                    continue;
                }
                $host = parse_url($url, PHP_URL_HOST) ?: '';
                if ($host === '') {
                    continue;
                }
                $hits[] = ['url' => $url, 'host' => strtolower((string) $host)];
                if (count($hits) >= $max) {
                    break;
                }
            }
        }

        // Fallback: crude containment check when markup differs.
        if ($hits === [] && Str::contains($html, $email)) {
            $hits[] = ['url' => 'https://duckduckgo.com/?q='.rawurlencode('"'.$email.'"'), 'host' => 'duckduckgo.com'];
        }

        return $hits;
    }
}
