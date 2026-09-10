<?php

namespace App\Services\ReversePhoneOSINT;

use Illuminate\Support\Facades\Http;
use Throwable;

class BingSearchProvider implements SearchProviderInterface
{
    public function search(string $query): array
    {
        $key = config('services.bing_search.key');
        if (! $key) {
            return [];
        }
        try {
            $response = Http::withHeaders(['Ocp-Apim-Subscription-Key' => $key])->timeout(8)->get('https://api.bing.microsoft.com/v7.0/search', ['q' => $query, 'count' => 10, 'responseFilter' => 'Webpages']);

            return collect($response->json('webPages.value', []))->map(fn ($item) => ['title' => (string) ($item['name'] ?? ''), 'url' => (string) ($item['url'] ?? ''), 'snippet' => (string) ($item['snippet'] ?? '')])->filter(fn ($item) => filter_var($item['url'], FILTER_VALIDATE_URL))->values()->all();
        } catch (Throwable) {
            return [];
        }
    }
}
