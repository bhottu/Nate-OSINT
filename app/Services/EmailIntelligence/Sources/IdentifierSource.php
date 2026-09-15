<?php

namespace App\Services\EmailIntelligence\Sources;

use App\Services\EmailIntelligence\EmailNormalizer;
use App\Services\EmailIntelligence\SourceResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Keyless, unauthenticated profile-URL probes for platforms that expose
 * deterministic public profile pages.
 *
 * Semantics used (publicly documented or widely accepted):
 *  - 200  -> profile page exists (treated as LOW confidence only: some
 *            platforms render generic pages for unknown handles).
 *  - 404  -> profile does not exist under this identifier.
 *  - 403  -> blocked / anti-bot; reported as UNABLE TO VERIFY.
 *
 * Nothing here logs in, brute-forces, bypasses protections, or claims that a
 * matching handle belongs to the owner of the submitted email.
 */
class IdentifierSource implements SourceInterface
{
    /** platform => [profile URL pattern, strict 200 semantics] */
    private const PLATFORMS = [
        'GitHub' => ['https://github.com/:id', true],
        'GitLab' => ['https://gitlab.com/:id', true],
        'Reddit' => ['https://www.reddit.com/user/:id/', false],
        'Medium' => ['https://medium.com/@:id', false],
        'Twitch' => ['https://www.twitch.tv/:id', false],
        'Telegram' => ['https://t.me/:id', true],
        'Pinterest' => ['https://www.pinterest.com/:id/', false],
        'YouTube' => ['https://www.youtube.com/@:id', false],
    ];

    private const MAX_PLATFORMS = 12;

    public function __construct(private EmailNormalizer $normalizer) {}

    public function platform(): string
    {
        return 'Public profiles';
    }

    public function kind(): string
    {
        return 'identifier_probe';
    }

    public function check(string $normalizedEmail): SourceResult
    {
        // The orchestrator invokes checkAll(); a single check() makes no sense.
        return SourceResult::notFound($this->platform(), $this->kind(), null, 'Not applicable — identifier probes run per platform.');
    }

    /**
     * Probe deterministic profile URLs derived from the email local part.
     * Cached statuses are reused; only cache misses are sent, concurrently,
     * in a single bounded pool.
     *
     * @return array<int, SourceResult>
     */
    public function checkAll(string $normalizedEmail): array
    {
        $parsed = $this->normalizer->parse($normalizedEmail);

        if (($parsed['is_role_address'] ?? false) === true) {
            return [SourceResult::notFound(
                'Public profiles',
                'identifier_probe',
                null,
                'Role mailbox (e.g. info@, admin@): the local part is not a personal identifier, so profile probing was skipped.'
            )];
        }

        $candidates = array_slice($parsed['identifier_candidates'] ?? [], 0, 2);
        if ($candidates === []) {
            return [SourceResult::notFound('Public profiles', 'identifier_probe', null, 'No usable identifier could be derived from the email local part.')];
        }

        $probes = [];
        foreach (array_slice(self::PLATFORMS, 0, self::MAX_PLATFORMS) as $platform => [$pattern, $strict]) {
            foreach ($candidates as $index => $identifier) {
                $probes[] = [
                    'platform' => $platform,
                    'pattern' => $pattern,
                    'strict' => $strict,
                    'identifier' => $identifier,
                    'derived' => $index > 0,
                    'url' => str_replace(':id', rawurlencode($identifier), $pattern),
                ];
            }
        }

        $ttl = (int) config('email_intelligence.cache_ttl.gravatar', 1800);
        $statuses = [];
        $misses = [];

        foreach ($probes as $i => $probe) {
            $key = 'email-intel:probe:'.hash('sha256', $probe['url']);
            $cached = Cache::get($key);
            if ($cached !== null) {
                $statuses[$i] = (int) $cached;
            } else {
                $misses[$i] = $key;
            }
        }

        if ($misses !== []) {
            $responses = Http::pool(function ($pool) use ($probes, $misses) {
                foreach ($misses as $i => $key) {
                    $pool->as((string) $i)
                        ->withHeaders([
                            'User-Agent' => SourceResult::USER_AGENT,
                            'Accept' => 'text/html,application/xhtml+xml',
                        ])
                        ->timeout((int) config('email_intelligence.timeouts.request', 10))
                        ->connectTimeout((int) config('email_intelligence.timeouts.connect', 5))
                        ->get($probes[$i]['url']);
                }
            });

            foreach ($misses as $i => $key) {
                $response = $responses[$i] ?? null;
                $status = $response instanceof Response ? $response->status() : 0;
                Cache::put($key, $status, $ttl);
                $statuses[$i] = $status;
            }
        }

        $results = [];
        foreach ($probes as $i => $probe) {
            $results[] = $this->interpret($probe, $statuses[$i] ?? 0);
        }

        return $results;
    }

    /**
     * @param  array{platform: string, pattern: string, strict: bool, identifier: string, derived: bool, url: string}  $probe
     */
    private function interpret(array $probe, int $status): SourceResult
    {
        $platform = $probe['platform'];
        $url = $probe['url'];

        if ($status === 0) {
            return SourceResult::error($platform, 'identifier_probe', 'Request timed out or connection failed', $url);
        }

        if ($status === 200) {
            $note = $probe['strict']
                ? 'Platform returns this profile page only when the handle exists. The handle was derived from the email local part; association with the email owner is unverified.'
                : 'Profile page responded, but this platform may render pages for unknown handles as well. Treat as inconclusive unless corroborated.';

            if ($probe['derived']) {
                $note .= ' (Variant identifier: alternate form of the local part.)';
            }

            return SourceResult::found(
                $platform,
                'identifier_probe',
                $probe['identifier'],
                $url,
                sprintf('Public profile page responding at %s using identifier derived from the email local part.', $url),
                $url,
                'Low',
                $note
            );
        }

        if ($status === 404 || $status === 410) {
            return SourceResult::notFound($platform, 'identifier_probe', $url, 'No public profile exists under this identifier (HTTP 404).');
        }

        if ($status === 401 || $status === 403 || $status === 429) {
            return SourceResult::unableToVerify($platform, 'identifier_probe', $url, 'Platform blocked or rate-limited the unauthenticated request; unable to verify through public sources.');
        }

        return SourceResult::unableToVerify($platform, 'identifier_probe', $url, 'Unexpected HTTP status '.$status.'; unable to verify through public sources.');
    }

    /** Number of platforms this source probes (for progress counters). */
    public static function platformCount(): int
    {
        return min(count(self::PLATFORMS), self::MAX_PLATFORMS);
    }
}
