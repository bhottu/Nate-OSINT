<?php

namespace App\Services\EmailIntelligence\Sources;

use App\Services\EmailIntelligence\SourceResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * GitHub public search via the official REST API (no authentication required
 * for low-rate search; GITHUB_TOKEN is used automatically when provided).
 *
 * The code search endpoint returns only what is publicly visible on GitHub.
 * A hit means the email string appears in some public code/repository — it is
 * evidence of publication, never proof that the account owner controls the
 * email address (commits can contain arbitrary, historical, or third-party
 * emails).
 */
class GitHubSource implements SourceInterface
{
    public function platform(): string
    {
        return 'GitHub';
    }

    public function kind(): string
    {
        return 'public_code_search';
    }

    public function check(string $normalizedEmail): SourceResult
    {
        $ttl = (int) config('email_intelligence.cache_ttl.github', 600);
        $cacheKey = 'email-intel:github:'.hash('sha256', strtolower($normalizedEmail));

        $payload = Cache::remember($cacheKey, $ttl, fn () => $this->fetch($normalizedEmail));

        if (isset($payload['exception'])) {
            return SourceResult::error($this->platform(), $this->kind(), 'GitHub API could not be reached', 'https://api.github.com/search/code');
        }

        $status = (int) ($payload['status'] ?? 0);

        if ($status === 403 || $status === 429) {
            return SourceResult::error($this->platform(), $this->kind(), 'GitHub API rate limit reached (try again later)', 'https://api.github.com/search/code');
        }

        if ($status === 401) {
            return SourceResult::error($this->platform(), $this->kind(), 'GitHub code search requires authentication — set GITHUB_TOKEN in the environment (token missing or rejected)', 'https://api.github.com/search/code');
        }

        if ($status !== 200) {
            return SourceResult::error($this->platform(), $this->kind(), 'Unexpected response from GitHub (HTTP '.$status.')', 'https://api.github.com/search/code');
        }

        $body = (array) ($payload['body'] ?? []);
        $items = (array) ($body['items'] ?? []);
        $totalCount = (int) ($body['total_count'] ?? 0);

        if ($totalCount === 0 || $items === []) {
            return SourceResult::notFound($this->platform(), $this->kind(), 'https://api.github.com/search/code', 'No public GitHub code matches this email address.');
        }

        $first = (array) $items[0];
        $repo = (array) ($first['repository'] ?? []);
        $owner = (array) ($repo['owner'] ?? []);
        $login = is_string($owner['login'] ?? null) ? $owner['login'] : null;
        $repoName = is_string($repo['full_name'] ?? null) ? $repo['full_name'] : null;
        $path = is_string($first['path'] ?? null) ? $first['path'] : null;

        $htmlUrl = $repoName !== null && $path !== null
            ? 'https://github.com/'.$repoName.'/blob/HEAD/'.str_replace('%2F', '/', rawurlencode($path))
            : ($login !== null ? 'https://github.com/'.$login : 'https://github.com/search?q='.rawurlencode('"'.$normalizedEmail.'"'));

        // Confidence is capped at Medium: public publication of the address is
        // strong evidence of association, but authorship cannot be verified.
        $confidence = $login !== null ? 'Medium' : 'Low';

        $evidence = $totalCount === 1
            ? 'Email address appears in 1 publicly visible code search result on GitHub.'
            : sprintf('Email address appears in %d publicly visible code search results on GitHub (showing the first).', $totalCount);

        return SourceResult::found(
            $this->platform(),
            $this->kind(),
            $login ?? ($repoName ?? 'public code match'),
            $htmlUrl,
            $evidence,
            'https://api.github.com/search/code?q='.rawurlencode('"'.$normalizedEmail.'"'),
            $confidence,
            'Public code search hit only. GitHub commit emails can be arbitrary or historical; the account owner is a possible match, not a verified one.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(string $email): array
    {
        $token = trim((string) env('GITHUB_TOKEN'));
        $headers = [
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => SourceResult::USER_AGENT,
            'X-GitHub-Api-Version' => '2022-11-28',
        ];

        if ($token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout((int) config('email_intelligence.timeouts.request', 10))
                ->connectTimeout((int) config('email_intelligence.timeouts.connect', 5))
                ->get('https://api.github.com/search/code', [
                    'q' => '"'.$email.'"',
                    'per_page' => 1,
                ]);
        } catch (Throwable $e) {
            return ['exception' => true, 'message' => $e->getMessage()];
        }

        return ['status' => $response->status(), 'body' => $response->json()];
    }
}
