<?php

namespace App\Services\ReversePhoneOSINT;

class ResultCorrelator
{
    public function correlate(array $results, string $target): array
    {
        $groups = [];
        foreach ($results as $result) {
            $url = $result['url'];
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $category = $this->category($host, $url);
            $key = md5($host.'|'.$category);
            $groups[$key] ??= ['name' => $this->title($result['title'], $host), 'phone_number' => $target, 'platform' => $this->platform($host), 'profile_url' => $url, 'source_url' => $url, 'category' => $category, 'evidence' => $result['snippet'], 'confidence' => 'LOW', 'sources' => []];
            $groups[$key]['sources'][] = $url;
            if ($category === 'business' || $category === 'website') {
                $groups[$key]['confidence'] = count($groups[$key]['sources']) > 1 ? 'HIGH' : 'MEDIUM';
            }
        }

        return array_values($groups);
    }

    private function category(string $host, string $url): string
    {
        $text = $host.' '.strtolower($url);
        if (preg_match('/facebook|instagram|linkedin|x\.com|twitter|tiktok/', $text)) {
            return 'social';
        }
        if (preg_match('/shop|store|market|tokopedia|shopee|amazon|ebay/', $text)) {
            return 'marketplace';
        }
        if (preg_match('/directory|yellowpages|listing|yelp/', $text)) {
            return 'business';
        }
        if (preg_match('/forum|reddit|quora/', $text)) {
            return 'other';
        }

        return 'website';
    }

    private function platform(string $host): string
    {
        return preg_replace('/^www\./', '', $host) ?: 'Public website';
    }

    private function title(string $title, string $host): string
    {
        return trim($title) ?: $this->platform($host);
    }
}
