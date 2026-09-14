<?php

namespace App\Services\UsernameHunter;

class UsernameValidator
{
    private const MIN_LENGTH = 1;

    private const MAX_LENGTH = 64;

    private const PATTERN = '/^[a-zA-Z0-9._-]+$/';

    private const BLOCKED = ['admin', 'root', 'null', 'undefined', 'support', 'help', 'system', 'official', 'verified'];

    public function normalize(string $input): string
    {
        $username = trim($input);
        $username = ltrim($username, '@');

        return strtolower($username);
    }

    public function validate(string $input): ?string
    {
        $username = $this->normalize($input);

        if (mb_strlen($username) < self::MIN_LENGTH || mb_strlen($username) > self::MAX_LENGTH) {
            return null;
        }

        if (! preg_match(self::PATTERN, $username)) {
            return null;
        }

        if (in_array($username, self::BLOCKED, true)) {
            return null;
        }

        if (str_starts_with($username, '.') || str_starts_with($username, '_') || str_starts_with($username, '-')) {
            return null;
        }

        if (str_ends_with($username, '.') || str_ends_with($username, '_') || str_ends_with($username, '-')) {
            return null;
        }

        if (substr_count($username, '..') > 0 || substr_count($username, '__') > 0 || substr_count($username, '--') > 0) {
            return null;
        }

        return $username;
    }
}
