<?php

namespace App\Services\PhoneIntelligence;

use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Throwable;

class PhoneNormalizer
{
    public function normalize(string $raw, ?string $region = null): ?array
    {
        $raw = trim(html_entity_decode($raw));
        if (strlen($raw) < 7 || strlen($raw) > 30) {
            return null;
        }
        try {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $number = $phoneUtil->parse($raw, $region ?: 'ID');
            $isPossible = $phoneUtil->isPossibleNumber($number);
            $isValid = $phoneUtil->isValidNumber($number);
            if (! $isPossible) {
                return null;
            }
            $type = $phoneUtil->getNumberType($number);
            $typeName = match ($type) {
                1 => 'Mobile', 2 => 'Fixed line', 3, 4 => 'Fixed line / mobile', 5 => 'Toll free', 6 => 'Premium rate', default => 'Unknown',
            };
            $regionCode = $phoneUtil->getRegionCodeForNumber($number) ?: null;

            return ['number' => $phoneUtil->format($number, PhoneNumberFormat::E164), 'raw' => $raw, 'country' => $regionCode, 'country_code' => '+'.$number->getCountryCode(), 'region' => $regionCode, 'type' => $typeName, 'status' => $isValid ? 'VALID FORMAT' : 'POSSIBLE FORMAT'];
        } catch (Throwable) {
            return null;
        }
    }
}
