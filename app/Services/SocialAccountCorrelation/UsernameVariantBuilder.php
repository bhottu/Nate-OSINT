<?php

namespace App\Services\SocialAccountCorrelation;

class UsernameVariantBuilder
{
    public function build(string $username): array
    {
        $clean = ltrim(trim($username), '@');
        $base = preg_replace('/[^a-zA-Z0-9._-]/', '', $clean);
        $withoutSeparators = preg_replace('/[._-]+/', '', $base);

        return array_values(array_unique(array_filter([
            $base,
            $withoutSeparators,
            $base.'_official',
            $base.'.id',
            $base.'_id',
            $base.'123',
        ])));
    }
}
