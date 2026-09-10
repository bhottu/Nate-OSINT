<?php

namespace App\Services\ReversePhoneOSINT;

use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class SearchQueryBuilder
{
    public function build(string $e164, ?string $region = null): array
    {
        $util = PhoneNumberUtil::getInstance();
        $number = $util->parse($e164, $region ?: 'ID');
        $international = $util->format($number, PhoneNumberFormat::INTERNATIONAL);
        $national = $util->format($number, PhoneNumberFormat::NATIONAL);
        $nationalDashed = preg_replace('/\s+/', '-', trim($national));
        $digits = preg_replace('/\D+/', '', $e164);

        return array_values(array_unique([$e164, $digits, $international, $national, $nationalDashed, '"'.$e164.'"', '"'.$international.'"', '"'.$national.'"', '"'.$nationalDashed.'"']));
    }
}
