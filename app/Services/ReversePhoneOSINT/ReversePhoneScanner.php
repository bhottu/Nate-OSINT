<?php

namespace App\Services\ReversePhoneOSINT;

use App\Services\PhoneIntelligence\PhoneNormalizer;
use RuntimeException;

class ReversePhoneScanner
{
    public function __construct(private PhoneNormalizer $normalizer, private SearchQueryBuilder $queries, private SearchProviderInterface $provider, private ResultCorrelator $correlator) {}

    public function scan(string $raw): array
    {
        $phone = $this->normalizer->normalize($raw);
        if (! $phone) {
            throw new RuntimeException('Enter a valid phone number.');
        }
        $results = [];
        foreach ($this->queries->build($phone['number'], $phone['country']) as $query) {
            foreach ($this->provider->search($query) as $result) {
                $results[] = $result;
            }
        }
        $footprint = $this->correlator->correlate($results, $phone['number']);

        return ['target' => ['phone' => $phone['number'], 'country' => $phone['country'], 'country_code' => $phone['country_code'], 'type' => $phone['type']], 'search_variants' => $this->queries->build($phone['number'], $phone['country']), 'public_footprint' => $footprint, 'status' => 'COMPLETE', 'scope' => 'Publicly indexed sources only. No private ownership lookup.'];
    }
}
