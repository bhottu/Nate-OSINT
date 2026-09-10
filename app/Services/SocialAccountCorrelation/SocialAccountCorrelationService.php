<?php

namespace App\Services\SocialAccountCorrelation;

use App\Services\ReversePhoneOSINT\SearchProviderInterface;
use App\Services\SecurityInspector\UrlGuard;
use RuntimeException;

class SocialAccountCorrelationService
{
    private const MAX_QUERIES = 20;

    private const MAX_RESULTS = 30;

    public function __construct(private UsernameVariantBuilder $variants, private SearchProviderInterface $provider, private CrossLinkDetector $crossLinks, private EvidenceScorer $scorer, private IdentityGraphBuilder $graph, private SocialIdentifierValidator $identifierValidator, private UrlGuard $guard) {}

    public function analyze(string $input, string $platform): array
    {
        $seed = $this->seed($input, $platform);
        $searchResults = [];
        foreach (array_slice($this->queries($seed['username'], $platform), 0, self::MAX_QUERIES) as $query) {
            foreach ($this->provider->search($query) as $result) {
                $searchResults[] = $result;
            }
        }
        $profiles = $this->profiles($searchResults, $seed);
        foreach ($this->crossLinks->inspect($seed['url']) as $link) {
            if ($link['platform'] !== $seed['platform']) {
                $profiles[] = $this->profileFromLink($link, $seed);
            }
        }
        $profiles = array_values(collect($profiles)->keyBy(fn ($profile) => strtolower($profile['platform'].'|'.$profile['username'].'|'.$profile['profile_url']))->values()->all());

        $strongest = collect($profiles)->sortByDesc('score')->first();

        return ['seed' => $seed, 'search_variants' => $this->queries($seed['username'], $platform), 'platforms' => $this->platformStatus($profiles), 'profiles' => $profiles, 'correlation' => ['score' => $strongest['score'] ?? 0, 'confidence' => $strongest['confidence'] ?? 'INSUFFICIENT EVIDENCE'], 'graph' => $this->graph->build($seed, $profiles), 'status' => 'ANALYSIS COMPLETE', 'disclaimer' => 'Correlation results use publicly accessible information only and are not definitive proof of identity or account ownership.'];
    }

    private function seed(string $input, string $platform): array
    {
        $input = trim($input);
        if (filter_var($input, FILTER_VALIDATE_URL)) {
            $url = $this->guard->validate($input);
            $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
            $username = ltrim((string) last(explode('/', $path)), '@');
        } else {
            $username = ltrim($input, '@');
            $url = null;
        }
        $username = $this->identifierValidator->username($username, $url);
        if (! $username) {
            throw new RuntimeException('Enter a valid public username or profile URL.');
        }

        return ['platform' => $platform, 'username' => $username, 'url' => $url];
    }

    private function queries(string $username, string $platform): array
    {
        $platforms = ['Instagram', 'GitHub', 'Facebook', 'TikTok', 'X', 'YouTube', 'LinkedIn'];
        $variants = array_slice($this->variants->build($username), 0, 10);
        $queries = [];
        foreach ($platforms as $targetPlatform) {
            foreach (array_slice($variants, 0, $targetPlatform === $platform ? 3 : 2) as $variant) {
                $host = $targetPlatform === 'X' ? 'x.com' : strtolower($targetPlatform).'.com';
                $queries[] = '"'.$variant.'" site:'.$host;
            }
        }

        return array_values(array_unique($queries));
    }

    private function profiles(array $results, array $seed): array
    {
        return collect(array_slice($results, 0, self::MAX_RESULTS))->map(function ($result) use ($seed) {
            $validatedProfile = $this->identifierValidator->profileUrl($result['url']);
            $platform = $validatedProfile['platform'] ?? null;
            if (! $platform || $platform === $seed['platform']) {
                return null;
            }
            $username = $validatedProfile['username'] ?? null;
            $identifier = $validatedProfile['identifier'] ?? null;
            if (! $username && ! $identifier) {
                return null;
            }
            $usernameValue = ltrim((string) ($username ?? ''), '@');
            $evidence = [];
            if (strcasecmp($usernameValue, $seed['username']) === 0) {
                $evidence[] = ['type' => 'EXACT USERNAME', 'source_platform' => $platform, 'target_platform' => $platform, 'source_url' => $result['url'], 'target_url' => $result['url'], 'description' => 'Public result uses the same username.'];
            } else {
                $evidence[] = ['type' => 'STRONG USERNAME VARIATION', 'source_platform' => $platform, 'target_platform' => $platform, 'source_url' => $result['url'], 'target_url' => $result['url'], 'description' => 'Public result uses a username variation.'];
            }
            if (str_contains(strtolower($result['snippet'].' '.$result['title']), strtolower($seed['username']))) {
                $evidence[] = ['type' => 'MATCHING BIO', 'source_platform' => $platform, 'target_platform' => $platform, 'source_url' => $result['url'], 'target_url' => $result['url'], 'description' => 'Public indexed text references the seed identifier.'];
            }
            $score = $this->scorer->score($evidence);

            return ['platform' => $platform, 'username' => $username, 'identifier' => $identifier, 'identifier_type' => $validatedProfile['identifier_type'] ?? 'username', 'profile_url' => $result['url'], 'source_url' => $result['url'], 'evidence' => $score['evidence'], 'evidence_records' => $score['evidence_records'], 'explanation' => 'Public search result; association requires manual verification.', 'score' => $score['score'], 'confidence' => $score['confidence'], 'discovered' => now()->toIso8601String()];
        })->filter()->values()->all();
    }

    private function profileFromLink(array $link, array $seed): array
    {
        $evidence = [['type' => 'DIRECT CROSS-LINK', 'source_platform' => $seed['platform'], 'target_platform' => $link['platform'], 'source_url' => $seed['url'], 'target_url' => $link['url'], 'description' => $link['explanation']]];
        $score = $this->scorer->score($evidence);

        return ['platform' => $link['platform'], 'username' => $link['username'], 'identifier' => $link['identifier'] ?? ltrim((string) $link['username'], '@'), 'identifier_type' => $link['identifier_type'] ?? 'username', 'profile_url' => $link['url'], 'source_url' => $seed['url'], 'evidence' => $score['evidence'], 'evidence_records' => $score['evidence_records'], 'explanation' => $link['explanation'], 'score' => $score['score'], 'confidence' => $score['confidence'], 'discovered' => now()->toIso8601String()];
    }

    private function platform(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return match (true) {
            str_contains($host, 'instagram') => 'Instagram', str_contains($host, 'tiktok') => 'TikTok', str_contains($host, 'youtube') => 'YouTube', str_contains($host, 'twitter') || $host === 'x.com' => 'X', str_contains($host, 'facebook') => 'Facebook', str_contains($host, 'linkedin') => 'LinkedIn', str_contains($host, 'reddit') => 'Reddit', str_contains($host, 'github') => 'GitHub', default => null
        };
    }

    private function platformStatus(array $profiles): array
    {
        $platforms = ['Instagram', 'GitHub', 'Facebook', 'TikTok', 'X', 'YouTube', 'LinkedIn'];

        return collect($platforms)->mapWithKeys(function (string $platform) use ($profiles) {
            $count = count(array_filter($profiles, fn ($profile) => $profile['platform'] === $platform));

            return [strtolower($platform) => ['status' => $count ? 'FOUND' : 'NO PUBLIC DATA', 'count' => $count]];
        })->all();
    }

    private function username(string $url, string $title): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return ltrim((string) (last(explode('/', $path)) ?: preg_replace('/\s+.*/', '', $title)), '@');
    }
}
