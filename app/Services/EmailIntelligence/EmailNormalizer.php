<?php

namespace App\Services\EmailIntelligence;

/**
 * Validates, normalizes, and classifies email addresses.
 *
 * Normalization is conservative: only transformations that are guaranteed by
 * provider rules (case, plus-addressing tags, Gmail dot removal) are applied.
 * The result never asserts that an email is active or owned by anyone.
 */
class EmailNormalizer
{
    private const COMMON_PROVIDERS = [
        'gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.co.uk', 'yahoo.co.id', 'yahoo.fr', 'yahoo.de',
        'outlook.com', 'hotmail.com', 'hotmail.co.uk', 'hotmail.co.id', 'live.com', 'live.co.id', 'msn.com',
        'icloud.com', 'me.com', 'mac.com', 'aol.com', 'protonmail.com', 'protonmail.ch', 'proton.me', 'pm.me',
        'gmx.com', 'gmx.net', 'gmx.de', 'web.de', 'mail.com', 'zoho.com', 'yandex.ru', 'yandex.com',
        'qq.com', '163.com', '126.com', 'yeah.net', 'sina.com', 'foxmail.com', 'fastmail.com', 'hushmail.com',
        'tutanota.com', 'tuta.io', 'hey.com', 'mail.ru', 'inbox.ru', 'list.ru', 'bk.ru',
    ];

    /** Well-known disposable/temporary mailbox services (publicly documented). */
    private const DISPOSABLE_DOMAINS = [
        'mailinator.com', 'yopmail.com', 'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.biz',
        'sharklasers.com', 'grr.la', 'spam4.me', '10minutemail.com', '10minutemail.net', '20minutemail.com',
        'temp-mail.org', 'temp-mail.io', 'tempmail.com', 'tempmail.net', 'tempmailo.com', 'tempmail.plus',
        'trashmail.com', 'trash-mail.com', 'throwawaymail.com', 'getnada.com', 'dispostable.com',
        'maildrop.cc', 'mailnesia.com', 'mintemail.com', 'mohmal.com', 'emailondeck.com', 'fakeinbox.com',
        'mailcatch.com', 'mytemp.email', 'burnermail.io', '33mail.com', 'mail.tm', 'mail.gw', 'mailsac.com',
        'tempinbox.com', 'instantemailaddress.com', 'throwam.com', 'mailsiphon.com', 'zetmail.com',
        '1secmail.com', '1secmail.net', 'esiix.com', 'wwjmp.com',
    ];

    /** Generic role mailboxes: these local parts are not personal identifiers. */
    private const ROLE_LOCAL_PARTS = [
        'admin', 'administrator', 'info', 'contact', 'support', 'sales', 'help', 'hello', 'mail', 'email',
        'office', 'team', 'noreply', 'no-reply', 'donotreply', 'postmaster', 'webmaster', 'hostmaster',
        'billing', 'careers', 'jobs', 'hr', 'security', 'abuse', 'root', 'sysadmin', 'service', 'marketing',
        'press', 'media', 'legal', 'privacy', 'feedback', 'newsletter', 'notify', 'alerts', 'orders',
    ];

    /**
     * Parse, validate, and normalize a submitted email address.
     *
     * @return array{valid: bool, email: string, normalized: string, local_part: string,
     *               domain: string, is_common_provider: bool, is_disposable: string,
     *               is_role_address: bool, identifier_candidates: array<int, string>, notes: array<int, string>}
     */
    public function parse(string $input): array
    {
        $email = trim($input);
        $notes = [];

        $valid = $this->isValid($email);

        $local = $email;
        $domain = '';
        if ($valid) {
            $at = strrpos($email, '@');
            $local = substr($email, 0, (int) $at);
            $domain = rtrim(strtolower(substr($email, (int) $at + 1)), '.');

            if (strlen($local) > 64) {
                $valid = false;
                $notes[] = 'Local part exceeds the 64-character limit.';
            }
        }

        // Strip plus-addressing tags (user+tag@domain -> user@domain).
        $plus = strpos($local, '+');
        if ($plus !== false && $plus > 0) {
            $local = substr($local, 0, $plus);
            $notes[] = 'Plus-addressing tag removed during normalization.';
        }

        $local = strtolower($local);
        $isCommon = in_array($domain, self::COMMON_PROVIDERS, true);

        // Gmail ignores dots in the local part and treats googlemail.com as gmail.com.
        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $compact = str_replace('.', '', $local);
            if ($compact !== $local) {
                $notes[] = 'Dots removed from Gmail local part (Gmail ignores dots).';
            }
            $local = $compact;
            $domain = 'gmail.com';
        }

        $normalized = $local.'@'.$domain;
        $isDisposable = $this->disposableStatus($domain, $isCommon);

        return [
            'valid' => $valid,
            'email' => $email,
            'normalized' => $normalized,
            'local_part' => $local,
            'domain' => $domain,
            'is_common_provider' => $isCommon,
            'is_disposable' => $isDisposable,
            'is_role_address' => in_array($local, self::ROLE_LOCAL_PARTS, true),
            'identifier_candidates' => $this->identifierCandidates($local),
            'notes' => $notes,
        ];
    }

    public function isValid(string $email): bool
    {
        if ($email === '' || strlen($email) > 254 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $at = strrpos($email, '@');
        if ($at === false) {
            return false;
        }

        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);

        if (str_contains($local, '..') || str_starts_with($local, '.') || str_ends_with($local, '.')) {
            return false;
        }

        return $domain !== '' && str_contains($domain, '.') && ! str_contains($domain, '..');
    }

    /**
     * Username candidates derived from the email local part, used only for
     * low-confidence public identifier lookups.
     *
     * @return array<int, string>
     */
    public function identifierCandidates(string $localPart): array
    {
        $compact = str_replace('.', '', $localPart);
        $sanitized = preg_replace('/[^a-z0-9._-]/', '', $localPart) ?? '';

        $candidates = [];
        foreach ([$compact, $sanitized] as $candidate) {
            $candidate = trim($candidate, '-_');
            if ($candidate !== '' && strlen($candidate) >= 2 && strlen($candidate) <= 64) {
                $candidates[] = $candidate;
            }
        }

        return array_values(array_unique($candidates));
    }

    /** 'YES', 'NO', or 'UNKNOWN' — never guessed for unrecognized custom domains. */
    private function disposableStatus(string $domain, bool $isCommonProvider): string
    {
        if (in_array($domain, self::DISPOSABLE_DOMAINS, true)) {
            return 'YES';
        }

        return $isCommonProvider ? 'NO' : 'UNKNOWN';
    }
}
