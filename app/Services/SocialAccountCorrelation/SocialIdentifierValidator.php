<?php

namespace App\Services\SocialAccountCorrelation;

use Illuminate\Support\Facades\Log;

class SocialIdentifierValidator
{
    private const RESERVED_PATHS = ['css', 'charset=utf-8', 'profile.php', 'watch', 'posts', 'post', 'video', 'videos', 'reel', 'reels', 'share', 'sharer', 'accounts'];

    public function __construct(private InvalidSocialIdentifierDetector $invalidDetector) {}

    public function username(?string $candidate, ?string $source = null): ?string
    {
        if (! $candidate) {
            return null;
        }
        $candidate = ltrim(trim($candidate), '@');
        if ($this->invalidDetector->reason($candidate) || ! preg_match('/^[a-zA-Z0-9](?:[a-zA-Z0-9._-]{0,48}[a-zA-Z0-9])?$/', $candidate)) {
            Log::debug('Social correlation candidate rejected', ['reason' => $this->invalidDetector->reason($candidate) ?: 'invalid_identifier', 'platform' => null, 'candidate' => substr($candidate, 0, 80), 'source' => $source]);

            return null;
        }

        return $candidate;
    }

    public function profileUrl(string $url): ?array
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return null;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $platform = match (true) {
            $host === 'instagram.com' || str_ends_with($host, '.instagram.com') => 'Instagram',
            $host === 'tiktok.com' || str_ends_with($host, '.tiktok.com') => 'TikTok',
            $host === 'x.com' || $host === 'twitter.com' || str_ends_with($host, '.twitter.com') => 'X',
            $host === 'facebook.com' || str_ends_with($host, '.facebook.com') => 'Facebook',
            $host === 'youtube.com' || str_ends_with($host, '.youtube.com') => 'YouTube',
            $host === 'linkedin.com' || str_ends_with($host, '.linkedin.com') => 'LinkedIn',
            $host === 'reddit.com' || str_ends_with($host, '.reddit.com') => 'Reddit',
            $host === 'github.com' || str_ends_with($host, '.github.com') => 'GitHub',
            default => null,
        };
        if (! $platform) {
            return null;
        }
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segments = array_values(array_filter(explode('/', $path)));
        $firstSegment = strtolower($segments[0] ?? '');
        $isContentUrl = ($platform === 'X' && in_array('status', array_map('strtolower', $segments), true)) || ($platform === 'YouTube' && in_array($firstSegment, ['watch', 'shorts', 'playlist', 'clip'], true));
        if (! $segments || in_array($firstSegment, self::RESERVED_PATHS, true) || $isContentUrl || ($platform === 'Facebook' && in_array($firstSegment, ['groups', 'watch', 'posts', 'video', 'videos', 'share', 'sharer'], true))) {
            return null;
        }
        if ($platform === 'Facebook' && count($segments) === 1 && ctype_digit(end($segments))) {
            return ['platform' => $platform, 'username' => null, 'identifier' => end($segments), 'identifier_type' => 'numeric_id', 'url' => $url, 'profile_type' => 'UNKNOWN'];
        }
        $username = $this->username(end($segments), $url);
        if (! $username) {
            return null;
        }

        return ['platform' => $platform, 'username' => '@'.$username, 'identifier' => $username, 'identifier_type' => 'username', 'url' => $url, 'profile_type' => $this->facebookType($platform, $segments)];
    }

    private function facebookType(string $platform, array $segments): ?string
    {
        if ($platform !== 'Facebook') {
            return null;
        }

        return strtolower($segments[0]) === 'pages' ? 'PUBLIC_PAGE' : 'PERSONAL_PROFILE';
    }
}
