<?php

namespace App\Services\PhoneIntelligence;

class PhoneScanner
{
    public function __construct(private PublicContactExtractor $extractor) {}

    public function scan(?string $businessName, ?string $companyName, ?string $domain, ?string $url): array
    {
        $target = trim((string) ($url ?: $domain));
        if (! preg_match('/^https?:\/\//i', $target)) {
            $target = 'https://'.$target;
        }
        $host = parse_url($target, PHP_URL_HOST);
        $phones = $this->extractor->extract($target);

        return ['target' => ['url' => $target, 'domain' => $host, 'business' => $businessName, 'company' => $companyName], 'phones' => $phones, 'scanned_pages' => null, 'status' => 'SCAN COMPLETE', 'scope' => 'Public business contact information only.'];
    }
}
