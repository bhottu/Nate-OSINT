<?php

namespace App\Services\PhoneIntelligence;

class ConfidenceCalculator
{
    public function calculate(string $sourceType, bool $officialHost = true): string
    {
        if ($officialHost && in_array($sourceType, ['contact', 'about', 'support', 'location', 'footer'], true)) {
            return 'HIGH';
        }
        if ($sourceType === 'structured-data') {
            return 'MEDIUM';
        }

        return 'LOW';
    }
}
