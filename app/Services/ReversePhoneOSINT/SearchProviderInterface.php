<?php

namespace App\Services\ReversePhoneOSINT;

interface SearchProviderInterface
{
    /** @return array<int, array{title:string,url:string,snippet:string}> */
    public function search(string $query): array;
}
