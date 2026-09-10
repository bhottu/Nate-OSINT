<?php

namespace App\Services\PhoneIntelligence;

class PhoneParser
{
    public function __construct(private PhoneNormalizer $normalizer) {}

    public function parse(string $html): array
    {
        $candidates = [];
        preg_match_all('/href=["\']tel:([^"\']+)["\']/i', $html, $telMatches);
        $candidates = array_merge($candidates, $telMatches[1] ?? []);
        $visibleHtml = preg_replace('/<a\b[^>]*>.*?<\/a>/is', ' ', $html);
        $text = trim(strip_tags($visibleHtml ?: $html));
        preg_match_all('/(?<![\w])(?:\+\d[\d\s().-]{6,28}|\(?\d{2,4}\)?[\s.-]\d{3,4}[\s.-]\d{3,5})(?![\w])/', $text, $textMatches);
        $candidates = array_merge($candidates, $textMatches[0] ?? []);
        $phones = [];
        foreach ($candidates as $raw) {
            $phone = $this->normalizer->normalize($raw);
            if ($phone) {
                $key = preg_replace('/\D+/', '', $phone['number']);
                if (! isset($phones[$key])) {
                    $phones[$key] = $phone;
                }
            }
        }

        return array_values($phones);
    }
}
