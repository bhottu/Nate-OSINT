<?php

namespace App\Services\EmailIntelligence\Sources;

use App\Services\EmailIntelligence\SourceResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Gravatar profile check using the public, documented REST profile API.
 *
 * Semantics (from the official docs): 200 = profile exists, 404 = no profile.
 * A Gravatar profile only proves the hash was registered on Gravatar — it
 * never proves the submitted email is active or who controls it.
 */
class GravatarSource implements SourceInterface
{
    public function platform(): string
    {
        return 'Gravatar';
    }

    public function kind(): string
    {
        return 'profile_api';
    }

    public function check(string $normalizedEmail): SourceResult
    {
        $hash = hash('sha256', strtolower(trim($normalizedEmail)));
        $url = 'https://api.gravatar.com/v3/profiles/'.$hash;
        $ttl = (int) config('email_intelligence.cache_ttl.gravatar', 1800);

        $payload = Cache::remember('email-intel:gravatar:'.substr($hash, 0, 32), $ttl, function () use ($url) {
            try {
                $response = Http::acceptJson()
                    ->withHeaders(['User-Agent' => SourceResult::USER_AGENT])
                    ->timeout((int) config('email_intelligence.timeouts.request', 10))
                    ->connectTimeout((int) config('email_intelligence.timeouts.connect', 5))
                    ->get($url);
            } catch (Throwable $e) {
                return ['exception' => true, 'message' => $e->getMessage()];
            }

            return ['status' => $response->status(), 'body' => $response->json()];
        });

        if (! empty($payload['exception'])) {
            return SourceResult::error($this->platform(), $this->kind(), 'Gravatar could not be reached', $url);
        }

        $status = (int) ($payload['status'] ?? 0);

        if ($status === 200) {
            $body = (array) ($payload['body'] ?? []);
            $display = is_string($body['display_name'] ?? null) ? $body['display_name'] : null;

            return SourceResult::found(
                $this->platform(),
                $this->kind(),
                $display ?? 'gravatar profile',
                'https://gravatar.com/'.$hash.'?d=404',
                'Gravatar profile exists for this email hash (SHA-256). Registration implies the address was used with Gravatar at some point; activity level and current control are not verifiable.',
                $url,
                'High',
                $display ? "Gravatar profile registered under this email hash (display name: {$display})." : 'Gravatar profile registered under this email hash.'
            );
        }

        if ($status === 404) {
            return SourceResult::notFound($this->platform(), $this->kind(), $url, 'No public Gravatar profile exists for this email hash.');
        }

        if ($status === 429) {
            return SourceResult::error($this->platform(), $this->kind(), 'Request was rate limited by the service', $url);
        }

        return SourceResult::error($this->platform(), $this->kind(), 'Unexpected response from Gravatar (HTTP '.$status.')', $url);
    }
}
