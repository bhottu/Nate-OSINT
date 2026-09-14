<?php

namespace App\Services\UsernameHunter;

use Illuminate\Support\Facades\Http;
use Throwable;

class UsernameHunterService
{
    private const TIMEOUT = 8;

    private const CONNECT_TIMEOUT = 5;

    private const MAX_REDIRECTS = 3;

    private const USER_AGENT = 'NateOSINT-UsernameHunter/1.1 (passive public lookup; research tool)';

    private array $platforms = [
        'GitHub' => [
            'url' => 'https://github.com/{username}',
            'type' => 'direct',
            'found_status' => [200],
            'not_found_status' => [404],
        ],
        'Reddit' => [
            'url' => 'https://www.reddit.com/user/{username}',
            'type' => 'direct',
            'found_status' => [200],
            'not_found_status' => [404, 302],
        ],
        'YouTube' => [
            'url' => 'https://www.youtube.com/@{username}',
            'type' => 'direct',
            'found_status' => [200],
            'not_found_status' => [404],
        ],
        'GitLab' => [
            'url' => 'https://gitlab.com/{username}',
            'type' => 'direct',
            'found_status' => [200],
            'not_found_status' => [404],
        ],
        'Pinterest' => [
            'url' => 'https://www.pinterest.com/{username}',
            'type' => 'direct',
            'found_status' => [200],
            'not_found_status' => [404],
        ],
        'Medium' => [
            'url' => 'https://medium.com/@{username}',
            'type' => 'direct',
            'found_status' => [200],
            'not_found_status' => [404],
        ],
        'Telegram' => [
            'url' => 'https://t.me/{username}',
            'type' => 'direct',
            'found_status' => [200],
            'not_found_status' => [404],
        ],
        'Twitch' => [
            'url' => 'https://www.twitch.tv/{username}',
            'type' => 'direct',
            'found_status' => [200],
            'not_found_status' => [404],
        ],
        'Instagram' => [
            'url' => 'https://www.instagram.com/{username}/',
            'type' => 'redirect_check',
            'found_status' => [200],
            'not_found_status' => [302, 404],
        ],
        'TikTok' => [
            'url' => 'https://www.tiktok.com/@{username}',
            'type' => 'redirect_check',
            'found_status' => [200],
            'not_found_status' => [302, 404],
        ],
        'X' => [
            'url' => 'https://x.com/{username}',
            'type' => 'redirect_check',
            'found_status' => [200],
            'not_found_status' => [302, 404],
        ],
        'Facebook' => [
            'url' => 'https://www.facebook.com/{username}',
            'type' => 'redirect_check',
            'found_status' => [200],
            'not_found_status' => [302, 404],
        ],
        'LinkedIn' => [
            'url' => 'https://www.linkedin.com/in/{username}',
            'type' => 'redirect_check',
            'found_status' => [200],
            'not_found_status' => [404, 302],
        ],
        'Threads' => [
            'url' => 'https://www.threads.net/@{username}',
            'type' => 'redirect_check',
            'found_status' => [200],
            'not_found_status' => [302, 404],
        ],
    ];

    public function platforms(): array
    {
        return array_keys($this->platforms);
    }

    public function scan(string $username): array
    {
        $started = microtime(true);
        $results = [];

        foreach ($this->platforms as $platform => $config) {
            $results[] = $this->checkPlatform($platform, $config, $username);
        }

        $totalChecked = count($results);
        $found = count(array_filter($results, fn ($r) => $r['status'] === 'FOUND'));
        $notFound = count(array_filter($results, fn ($r) => $r['status'] === 'NOT FOUND'));
        $unknown = count(array_filter($results, fn ($r) => $r['status'] === 'UNKNOWN'));

        return [
            'username' => $username,
            'results' => $results,
            'summary' => [
                'total_checked' => $totalChecked,
                'found' => $found,
                'not_found' => $notFound,
                'unknown' => $unknown,
                'scan_time_seconds' => round(microtime(true) - $started, 3),
            ],
        ];
    }

    private function checkPlatform(string $platform, array $config, string $username): array
    {
        $url = str_replace('{username}', $username, $config['url']);
        $started = microtime(true);

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ])
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->timeout(self::TIMEOUT)
                ->withOptions([
                    'allow_redirects' => $config['type'] === 'redirect_check' ? ['max' => self::MAX_REDIRECTS] : false,
                ])
                ->get($url);

            $httpStatus = $response->status();
            $responseTimeMs = (int) round((microtime(true) - $started) * 1000);

            $status = $this->determineStatus($httpStatus, $config, $response);

            return [
                'platform' => $platform,
                'username' => $username,
                'profile_url' => $url,
                'status' => $status,
                'http_status' => $httpStatus,
                'response_time_ms' => $responseTimeMs,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            $responseTimeMs = (int) round((microtime(true) - $started) * 1000);

            return [
                'platform' => $platform,
                'username' => $username,
                'profile_url' => $url,
                'status' => 'UNKNOWN',
                'http_status' => null,
                'response_time_ms' => $responseTimeMs,
                'error' => $this->sanitizeError($exception->getMessage()),
            ];
        }
    }

    private function determineStatus(int $httpStatus, array $config, $response): string
    {
        if (in_array($httpStatus, $config['found_status'], true)) {
            if ($config['type'] === 'redirect_check') {
                $finalUrl = $response->effectiveUri() ?? null;
                if ($finalUrl && str_contains($finalUrl->getPath(), '/login')) {
                    return 'UNKNOWN';
                }
                if ($finalUrl && str_contains($finalUrl->getPath(), '/signup')) {
                    return 'UNKNOWN';
                }
            }

            return 'FOUND';
        }

        if (in_array($httpStatus, $config['not_found_status'], true)) {
            return 'NOT FOUND';
        }

        if ($httpStatus === 429 || $httpStatus === 403) {
            return 'UNKNOWN';
        }

        return 'UNKNOWN';
    }

    private function sanitizeError(string $error): string
    {
        $error = preg_replace('/https?:\/\/\S+/', '[url]', $error);

        return substr($error, 0, 200) ?: 'Connection failed';
    }
}
