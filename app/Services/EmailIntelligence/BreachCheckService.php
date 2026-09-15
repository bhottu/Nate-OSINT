<?php

namespace App\Services\EmailIntelligence;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Optional breach exposure check via the official Have I Been Pwned v3 API.
 *
 * - Enabled only when the operator configures HIBP_API_KEY in the environment.
 * - Never displays passwords or leaked record contents; only public breach
 *   metadata (service name, domain, date, data categories) is surfaced.
 * - The submitted email is never written to cache keys: only its SHA-256 hash.
 */
class BreachCheckService
{
    private const USER_AGENT = 'NateOSINT-EmailIntelligence/1.0 (passive public lookup; research tool)';

    public function configured(): bool
    {
        return (bool) config('email_intelligence.hibp.api_key');
    }

    /**
     * @return array{status: string, configured: bool, total: int, breaches: array<int, array<string, mixed>>, error: ?string}
     */
    public function check(string $email): array
    {
        if (! $this->configured()) {
            return [
                'status' => 'NOT CONFIGURED',
                'configured' => false,
                'total' => 0,
                'breaches' => [],
                'error' => 'Breach check is not configured',
            ];
        }

        $cacheKey = 'email-intel:hibp:'.hash('sha256', strtolower(trim($email)));
        $ttl = (int) config('email_intelligence.cache_ttl.hibp', 3600);

        return Cache::remember($cacheKey, $ttl, fn () => $this->request(trim($email)));
    }

    /**
     * @return array{status: string, configured: bool, total: int, breaches: array<int, array<string, mixed>>, error: ?string}
     */
    private function request(string $email): array
    {
        $base = rtrim((string) config('email_intelligence.hibp.base_url', 'https://haveibeenpwned.com/api/v3'), '/');

        try {
            $response = Http::withHeaders([
                'hibp-api-version' => '2',
                'User-Agent' => self::USER_AGENT,
            ])
                ->withToken((string) config('email_intelligence.hibp.api_key'))
                ->timeout((int) config('email_intelligence.hibp.timeout', 10))
                ->connectTimeout((int) config('email_intelligence.timeouts.connect', 5))
                ->get($base.'/breachedaccount/'.rawurlencode($email), ['truncateResponse' => 'false']);
        } catch (Throwable) {
            return [
                'status' => 'ERROR',
                'configured' => true,
                'total' => 0,
                'breaches' => [],
                'error' => 'Breach service could not be reached',
            ];
        }

        // 404 per the official API means "not found in any breach".
        if ($response->status() === 404) {
            return [
                'status' => 'NO PUBLIC BREACH RECORDS',
                'configured' => true,
                'total' => 0,
                'breaches' => [],
                'error' => null,
            ];
        }

        if ($response->status() === 401) {
            return ['status' => 'ERROR', 'configured' => true, 'total' => 0, 'breaches' => [], 'error' => 'Breach API key rejected (unauthorized)'];
        }

        if ($response->status() === 403) {
            return ['status' => 'ERROR', 'configured' => true, 'total' => 0, 'breaches' => [], 'error' => 'Breach API key lacks the required subscription'];
        }

        if ($response->status() === 429) {
            return ['status' => 'ERROR', 'configured' => true, 'total' => 0, 'breaches' => [], 'error' => 'Breach API rate limit reached'];
        }

        if (! $response->successful()) {
            return ['status' => 'ERROR', 'configured' => true, 'total' => 0, 'breaches' => [], 'error' => 'Breach API returned an unexpected response'];
        }

        $raw = $response->json();
        if (! is_array($raw)) {
            return ['status' => 'ERROR', 'configured' => true, 'total' => 0, 'breaches' => [], 'error' => 'Breach API returned an invalid response'];
        }

        $max = (int) config('email_intelligence.limits.max_breaches_listed', 20);
        $breaches = collect($raw)
            ->filter(fn ($item) => is_array($item))
            ->map(fn ($item) => [
                'name' => (string) ($item['Name'] ?? 'Unknown service'),
                'domain' => (string) ($item['Domain'] ?? '') ?: null,
                'breach_date' => (string) ($item['BreachDate'] ?? '') ?: null,
                'data_classes' => array_values(array_map(strval(...), (array) ($item['DataClasses'] ?? []))),
            ])
            ->take($max)
            ->values()
            ->all();

        return [
            'status' => 'COMPLETED',
            'configured' => true,
            'total' => count($raw),
            'breaches' => $breaches,
            'error' => null,
        ];
    }
}
