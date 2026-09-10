<?php

namespace App\Services\SocialAccountCorrelation;

use App\Services\SecurityInspector\UrlGuard;
use Illuminate\Support\Facades\Http;
use Throwable;

class CrossLinkDetector
{
    private const MAX_BYTES = 1_000_000;

    public function __construct(private UrlGuard $guard, private SocialIdentifierValidator $validator) {}

    public function inspect(?string $url): array
    {
        if (! $url) {
            return [];
        }
        try {
            $safeUrl = $this->guard->validate($url);
            $response = Http::timeout(8)->withOptions(['allow_redirects' => false])->get($safeUrl);
            if ($response->failed() || strlen($response->body()) > self::MAX_BYTES) {
                return [];
            }
            preg_match_all('/href=["\']([^"\']+)["\']/i', $response->body(), $matches);

            return collect($matches[1] ?? [])->map(fn ($link) => $this->absolute($safeUrl, trim(html_entity_decode($link))))->map(fn ($link) => $this->validator->profileUrl($link))->filter()->unique('url')->values()->map(fn ($profile) => ['url' => $profile['url'], 'platform' => $profile['platform'], 'username' => $profile['username'], 'identifier' => $profile['identifier'] ?? null, 'identifier_type' => $profile['identifier_type'] ?? 'username', 'profile_type' => $profile['profile_type'], 'evidence_type' => 'DIRECT CROSS-LINK', 'explanation' => 'The seed profile publicly links to this validated social profile.'])->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function absolute(string $base, string $link): string
    {
        return filter_var($link, FILTER_VALIDATE_URL) ? $link : rtrim($base, '/').'/'.ltrim($link, '/');
    }
}
